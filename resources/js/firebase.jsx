import { getApp, getApps, initializeApp } from 'firebase/app';
import { deleteToken, getMessaging, getToken, onMessage } from 'firebase/messaging';

function envValue(key) {
    const value = import.meta.env[key];
    return typeof value === 'string' ? value.trim() : '';
}

export const CANONICAL_SW_SCRIPT = '/service-worker.js';
export const CANONICAL_SW_SCOPE = '/';
export const EXPECTED_SW_VERSION = 'controltb-sw-v8';

export const firebaseWebConfig = {
    apiKey: envValue('VITE_FIREBASE_API_KEY'),
    authDomain: envValue('VITE_FIREBASE_AUTH_DOMAIN'),
    projectId: envValue('VITE_FIREBASE_PROJECT_ID'),
    storageBucket: envValue('VITE_FIREBASE_STORAGE_BUCKET'),
    messagingSenderId: envValue('VITE_FIREBASE_MESSAGING_SENDER_ID'),
    appId: envValue('VITE_FIREBASE_APP_ID'),
};

export const firebaseVapidKey = envValue('VITE_FIREBASE_VAPID_KEY');

function assertFirebaseWebConfig() {
    const missing = Object.entries(firebaseWebConfig)
        .filter(([, value]) => !value)
        .map(([key]) => key);

    if (missing.length > 0 || !firebaseVapidKey) {
        throw new Error(
            `[FCM] Firebase web config incomplete. Missing: ${[...missing, !firebaseVapidKey ? 'vapidKey' : null].filter(Boolean).join(', ')}`,
        );
    }
}

export function parseFcmPayload(payload) {
    const data = payload?.data || {};
    const notif = payload?.notification || {};
    const title = notif.title || data.title || 'Control TB';
    const body = notif.body || data.body || '';
    const icon = data.icon || notif.icon || '/menkes192.png';
    const badge = data.badge || '/menkes192.png';
    const tag = data.tag || notif.tag || 'controltb-reminder';
    const url = data.url || data.link || '/dashboard';
    const traceId = data.push_trace_id || '';
    const type = data.type || '';
    const historyId = data.history_id || '';
    const payloadMode = data.payload_mode || '';
    const ackRaw = data.ack_raw || '';
    const ackParsed = data.ack_parsed || '';
    const ackReceived = data.ack_received || '';
    const ackDisplayed = data.ack_displayed || '';
    const ackClicked = data.ack_clicked || '';
    const ackFailed = data.ack_failed || '';

    return {
        title,
        body,
        icon,
        badge,
        tag,
        url,
        traceId,
        type,
        historyId,
        payloadMode,
        ackRaw,
        ackParsed,
        ackReceived,
        ackDisplayed,
        ackClicked,
        ackFailed,
    };
}

export async function hashToken(token) {
    if (!token || typeof crypto === 'undefined' || !crypto.subtle) {
        return '';
    }

    const bytes = new TextEncoder().encode(token);
    const digest = await crypto.subtle.digest('SHA-256', bytes);

    return Array.from(new Uint8Array(digest))
        .map((b) => b.toString(16).padStart(2, '0'))
        .join('');
}

export function fingerprintFromHash(hash) {
    return (hash || '').slice(0, 12);
}

function xsrfHeaders() {
    const headers = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (meta) {
        headers['X-CSRF-TOKEN'] = meta;
    }
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    if (match) {
        headers['X-XSRF-TOKEN'] = decodeURIComponent(match[1]);
    }
    return headers;
}

function detectPlatformSafe() {
    const ua = navigator.userAgent || '';
    if (/Android/i.test(ua)) {
        return 'android';
    }
    if (/iPhone|iPad|iPod/i.test(ua)) {
        return 'ios';
    }
    return 'desktop';
}

export async function reportPushDiagnostic(event, extras = {}) {
    try {
        await fetch('/push/client-diagnostic', {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: xsrfHeaders(),
            body: JSON.stringify({
                event,
                push_trace_id: extras.push_trace_id || extras.traceId || null,
                token_fingerprint: extras.token_fingerprint || extras.fingerprint || null,
                message: String(extras.message || '').slice(0, 200),
                platform: extras.platform || detectPlatformSafe(),
            }),
        });
    } catch {
        // diagnostics must never break notification flow
    }
}

let app = null;
let messaging = null;
let initPromise = null;
let foregroundBound = false;
let lastGetTokenRegistration = { scriptURL: '', scope: '' };

function rememberGetTokenRegistration(registration) {
    lastGetTokenRegistration = {
        scriptURL: registration?.active?.scriptURL || registration?.installing?.scriptURL || '',
        scope: registration?.scope || '',
    };
    console.info('[FCM_CLIENT][GETTOKEN_REGISTRATION]', lastGetTokenRegistration);
    return lastGetTokenRegistration;
}

export function getLastGetTokenRegistration() {
    return lastGetTokenRegistration;
}

export function getOnMessageAttached() {
    return foregroundBound;
}

export function getFirebaseAppCount() {
    try {
        return getApps().length;
    } catch {
        return app ? 1 : 0;
    }
}

function getFirebaseApp() {
    if (getApps().length > 0) {
        return getApp();
    }
    return initializeApp(firebaseWebConfig);
}

export async function listServiceWorkerRegistrations() {
    if (!('serviceWorker' in navigator)) {
        return [];
    }
    const regs = await navigator.serviceWorker.getRegistrations();
    return regs.map((reg) => ({
        scriptURL: reg.active?.scriptURL || reg.waiting?.scriptURL || reg.installing?.scriptURL || '',
        scope: reg.scope,
        state: reg.active?.state || reg.waiting?.state || reg.installing?.state || 'unknown',
    }));
}

export async function getPushRegistration() {
    try {
        const registration = await navigator.serviceWorker.register(CANONICAL_SW_SCRIPT, { scope: CANONICAL_SW_SCOPE });
        await navigator.serviceWorker.ready;
        rememberGetTokenRegistration(registration);
        return registration;
    } catch (error) {
        await reportPushDiagnostic('SERVICE_WORKER_READY_FAILED', { message: error?.message || String(error) });
        throw error;
    }
}

async function displaySystemNotification(payload) {
    const parsed = parseFcmPayload(payload);
    console.info('[FCM] showNotification requested', { title: parsed.title, tag: parsed.tag, trace_id: parsed.traceId });

    if (Notification.permission !== 'granted') {
        await reportPushDiagnostic('PUSH_PERMISSION_DENIED', { push_trace_id: parsed.traceId, message: 'permission not granted' });
        throw new Error('Notification permission is not granted');
    }

    const registration = await navigator.serviceWorker.ready;
    if (!registration?.showNotification) {
        await reportPushDiagnostic('SERVICE_WORKER_NOT_READY', { push_trace_id: parsed.traceId });
        throw new Error('ServiceWorkerRegistration.showNotification is unavailable');
    }

    await registration.showNotification(parsed.title, {
        body: parsed.body,
        icon: parsed.icon,
        badge: parsed.badge,
        tag: parsed.tag,
        renotify: true,
        data: {
            url: parsed.url,
            push_trace_id: parsed.traceId,
            ack_clicked: parsed.ackClicked || '',
        },
    });

    console.info('[FCM] showNotification success', { trace_id: parsed.traceId });
    if (parsed.ackDisplayed) {
        fetch(parsed.ackDisplayed, { keepalive: true }).catch(() => {});
    }
    await reportPushDiagnostic('DISPLAY_REQUESTED', { push_trace_id: parsed.traceId });
    try {
        localStorage.setItem(
            'controltb_last_foreground_display',
            JSON.stringify({ at: Date.now(), traceId: parsed.traceId, status: 'DISPLAY_REQUESTED' }),
        );
    } catch {
        // ignore quota
    }
    window.dispatchEvent(
        new CustomEvent('controltb-fcm-display', {
            detail: { status: 'DISPLAY_REQUESTED', ...parsed, at: Date.now() },
        }),
    );

    return parsed;
}

async function handleForegroundMessage(payload) {
    console.info('[FCM_CLIENT][ONMESSAGE_RECEIVED]');
    const parsed = parseFcmPayload(payload);
    if (parsed.ackRaw) {
        fetch(parsed.ackRaw, { keepalive: true }).catch(() => {});
    }
    if (parsed.ackReceived) {
        fetch(parsed.ackReceived, { keepalive: true }).catch(() => {});
    }
    if (parsed.ackParsed) {
        fetch(parsed.ackParsed, { keepalive: true }).catch(() => {});
    }
    await reportPushDiagnostic('ONMESSAGE_RECEIVED', { push_trace_id: parsed.traceId });
    await reportPushDiagnostic('FOREGROUND_RECEIVED', { push_trace_id: parsed.traceId });
    try {
        localStorage.setItem(
            'controltb_last_foreground',
            JSON.stringify({ at: Date.now(), traceId: parsed.traceId, title: parsed.title }),
        );
    } catch {
        // ignore quota
    }
    window.dispatchEvent(
        new CustomEvent('controltb-fcm-foreground', {
            detail: { ...parsed, at: Date.now() },
        }),
    );

    try {
        await displaySystemNotification(payload);
    } catch (error) {
        console.error('[FCM] showNotification error', error);
        await reportPushDiagnostic('SHOW_NOTIFICATION_FAILED', {
            push_trace_id: parsed.traceId,
            message: error?.message || String(error),
        });
        if (parsed.ackFailed) {
            fetch(
                parsed.ackFailed + (parsed.ackFailed.includes('?') ? '&' : '?') + 'err=' + encodeURIComponent(String(error?.message || 'showNotification').slice(0, 120)),
                { keepalive: true },
            ).catch(() => {});
        }
        try {
            localStorage.setItem(
                'controltb_last_foreground_error',
                JSON.stringify({ at: Date.now(), error: error?.message || String(error), traceId: parsed.traceId }),
            );
        } catch {
            // ignore
        }
        window.dispatchEvent(
            new CustomEvent('controltb-fcm-display', {
                detail: { status: 'DISPLAY_FAILED', error: error?.message || String(error), at: Date.now(), traceId: parsed.traceId },
            }),
        );
    }
}

export async function initFirebaseMessaging() {
    if (initPromise) {
        return initPromise;
    }

    initPromise = (async () => {
        if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
            return null;
        }

        assertFirebaseWebConfig();
        await getPushRegistration();

        app = getFirebaseApp();
        if (!messaging) {
            messaging = getMessaging(app);
        }
        if (!foregroundBound) {
            try {
                onMessage(messaging, (payload) => {
                    void handleForegroundMessage(payload);
                });
                foregroundBound = true;
                window.__CONTROLTB_ONMESSAGE_ATTACHED = true;
                console.info('[FCM_CLIENT][ONMESSAGE_ATTACHED]', {
                    projectId: firebaseWebConfig.projectId,
                    messagingSenderId: firebaseWebConfig.messagingSenderId,
                    appCount: getApps().length,
                });
                await reportPushDiagnostic('ONMESSAGE_ATTACHED', { message: 'onMessage attached to page messaging singleton' });
                window.dispatchEvent(
                    new CustomEvent('controltb-fcm-onmessage-attached', {
                        detail: { at: Date.now(), appCount: getApps().length },
                    }),
                );
            } catch (error) {
                foregroundBound = false;
                await reportPushDiagnostic('ONMESSAGE_SETUP_FAILED', { message: error?.message || String(error) });
                throw error;
            }
        }

        return messaging;
    })().catch((error) => {
        console.info('[FCM] Firebase messaging is unavailable in this browser.', error);
        void reportPushDiagnostic('ONMESSAGE_SETUP_FAILED', { message: error?.message || String(error) });
        void reportPushDiagnostic('FCM_ONMESSAGE_ERROR', { message: error?.message || String(error) });
        messaging = null;
        initPromise = null;
        return null;
    });

    return initPromise;
}

async function getTokenWithCanonicalSw(vapidKey) {
    const registration = await getPushRegistration();
    const meta = rememberGetTokenRegistration(registration);
    if (!String(meta.scriptURL).includes('service-worker.js')) {
        await reportPushDiagnostic('GET_TOKEN_FAILED', { message: `getToken SW is not canonical: ${meta.scriptURL}` });
        throw new Error('Canonical /service-worker.js is not the active registration');
    }

    let currentToken = '';
    try {
        currentToken = await getToken(messaging, {
            vapidKey,
            serviceWorkerRegistration: registration,
        });
    } catch (error) {
        await reportPushDiagnostic('GET_TOKEN_FAILED', { message: error?.message || String(error) });
        await reportPushDiagnostic('FCM_GET_TOKEN_FAILED', { message: error?.message || String(error) });
        throw error;
    }

    return currentToken;
}

export const getFBToken = async (vapidPublicKey) => {
    const ready = await initFirebaseMessaging();
    if (!ready) {
        throw new Error('Push messaging is not supported in this browser');
    }

    const vapidKey = (vapidPublicKey || firebaseVapidKey || '').trim();
    if (!vapidKey) {
        throw new Error('VAPID public key is missing');
    }

    const currentToken = await getTokenWithCanonicalSw(vapidKey);
    if (!currentToken) {
        await reportPushDiagnostic('FCM_TOKEN_EMPTY', { message: 'getToken returned empty' });
        throw new Error('No registration token available. Request permission to generate token');
    }

    const hash = await hashToken(currentToken);
    console.info('[FCM] current registration fingerprint', fingerprintFromHash(hash));

    return currentToken;
};

export async function peekFBToken(vapidPublicKey) {
    const ready = await initFirebaseMessaging();
    if (!ready || Notification.permission !== 'granted') {
        return '';
    }

    const vapidKey = (vapidPublicKey || firebaseVapidKey || '').trim();
    if (!vapidKey) {
        return '';
    }

    try {
        return (await getTokenWithCanonicalSw(vapidKey)) || '';
    } catch {
        return '';
    }
}

export const clearFBToken = async () => {
    try {
        await initFirebaseMessaging();
        const registration = await getPushRegistration();
        const subscription = await registration.pushManager.getSubscription();
        if (subscription) {
            await subscription.unsubscribe();
        }
        if (messaging) {
            await deleteToken(messaging);
        }
        return true;
    } catch (error) {
        console.info('[FCM] Unable to clear FCM token', error);
        return false;
    }
};

function isFallbackFirebaseSw(reg) {
    const script = reg.active?.scriptURL || reg.waiting?.scriptURL || reg.installing?.scriptURL || '';
    const scope = reg.scope || '';
    return script.includes('firebase-messaging-sw.js') || scope.includes('firebase-cloud-messaging-push-scope');
}

export async function hardReregisterCurrentDevice(vapidPublicKey) {
    const vapidKey = (vapidPublicKey || firebaseVapidKey || '').trim();
    if (!vapidKey) {
        throw new Error('VAPID public key is missing');
    }

    const ready = await initFirebaseMessaging();
    if (!ready) {
        throw new Error('Push messaging is not supported in this browser');
    }

    try {
        const registrations = await navigator.serviceWorker.getRegistrations();
        for (const reg of registrations) {
            if (isFallbackFirebaseSw(reg)) {
                await reg.unregister();
            }
        }

        const canonical = await getPushRegistration();
        const existing = await canonical.pushManager.getSubscription();
        if (existing) {
            await existing.unsubscribe();
        }

        try {
            await deleteToken(messaging);
        } catch {
            // Token may already be invalid.
        }

        const registration = await navigator.serviceWorker.register(CANONICAL_SW_SCRIPT, { scope: CANONICAL_SW_SCOPE });
        await navigator.serviceWorker.ready;
        rememberGetTokenRegistration(registration);

        const token = await getToken(messaging, {
            vapidKey,
            serviceWorkerRegistration: registration,
        });

        if (!token) {
            throw new Error('Hard re-register getToken returned empty');
        }

        const hash = await hashToken(token);
        await reportPushDiagnostic('HARD_REREGISTER_OK', {
            fingerprint: fingerprintFromHash(hash),
            message: `sw=${registration.active?.scriptURL || ''}`,
        });

        return token;
    } catch (error) {
        await reportPushDiagnostic('HARD_REREGISTER_FAILED', { message: error?.message || String(error) });
        throw error;
    }
}

if (typeof window !== 'undefined' && 'serviceWorker' in navigator) {
    void initFirebaseMessaging();

    navigator.serviceWorker.addEventListener('message', (event) => {
        const data = event.data || {};
        if (data.type === 'controltb-fcm-background') {
            window.dispatchEvent(new CustomEvent('controltb-fcm-background', { detail: data }));
        }
        if (data.type === 'controltb-push-diag') {
            window.dispatchEvent(new CustomEvent('controltb-sw-version', { detail: { version: data.sw_version || '', diag: data.diag } }));
        }
    });
}
