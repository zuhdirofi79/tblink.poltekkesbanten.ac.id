import { initializeApp } from 'firebase/app';
import { deleteToken, getMessaging, getToken, onMessage } from 'firebase/messaging';

function envValue(key) {
    const value = import.meta.env[key];
    return typeof value === 'string' ? value.trim() : '';
}

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
    const ackReceived = data.ack_received || '';
    const ackDisplayed = data.ack_displayed || '';

    return { title, body, icon, badge, tag, url, traceId, type, historyId, ackReceived, ackDisplayed };
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

let app = null;
let messaging = null;
let initPromise = null;
let foregroundBound = false;

export async function getPushRegistration() {
    const registration = await navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
    await navigator.serviceWorker.ready;
    return registration;
}

async function displaySystemNotification(payload) {
    const parsed = parseFcmPayload(payload);
    console.info('[FCM] showNotification requested', { title: parsed.title, tag: parsed.tag, trace_id: parsed.traceId });

    if (Notification.permission !== 'granted') {
        throw new Error('Notification permission is not granted');
    }

    const registration = await navigator.serviceWorker.ready;
    if (!registration?.showNotification) {
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
        },
    });

    console.info('[FCM] showNotification success', { trace_id: parsed.traceId });
    if (parsed.ackDisplayed) {
        fetch(parsed.ackDisplayed, { keepalive: true }).catch(() => {});
    }
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
    console.info('[FCM] foreground message received');
    const parsed = parseFcmPayload(payload);
    if (parsed.ackReceived) {
        fetch(parsed.ackReceived, { keepalive: true }).catch(() => {});
    }
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

        if (!app) {
            app = initializeApp(firebaseWebConfig);
        }
        if (!messaging) {
            messaging = getMessaging(app);
        }
        if (!foregroundBound) {
            onMessage(messaging, (payload) => {
                void handleForegroundMessage(payload);
            });
            foregroundBound = true;
        }

        return messaging;
    })().catch((error) => {
        console.info('[FCM] Firebase messaging is unavailable in this browser.', error);
        messaging = null;
        initPromise = null;
        return null;
    });

    return initPromise;
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

    const registration = await getPushRegistration();
    const existing = await registration.pushManager.getSubscription();
    if (existing) {
        await existing.unsubscribe();
    }

    try {
        await deleteToken(messaging);
    } catch {
        // Old-project tokens may already be invalid.
    }

    const currentToken = await getToken(messaging, {
        vapidKey,
        serviceWorkerRegistration: registration,
    });

    if (!currentToken) {
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

    const registration = await getPushRegistration();
    const token = await getToken(messaging, {
        vapidKey,
        serviceWorkerRegistration: registration,
    });

    return token || '';
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

if (typeof window !== 'undefined' && 'serviceWorker' in navigator) {
    void initFirebaseMessaging();

    navigator.serviceWorker.addEventListener('message', (event) => {
        const data = event.data || {};
        if (data.type === 'controltb-fcm-background') {
            window.dispatchEvent(new CustomEvent('controltb-fcm-background', { detail: data }));
        }
    });
}
