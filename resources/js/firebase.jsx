import { getApp, getApps, initializeApp } from 'firebase/app';
import { deleteToken, getMessaging, getToken, onMessage } from 'firebase/messaging';

function envValue(key) {
    const value = import.meta.env[key];
    return typeof value === 'string' ? value.trim() : '';
}

export const MESSAGING_SW_SCRIPT = '/firebase-messaging-sw.js';

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

function getFirebaseApp() {
    if (getApps().length > 0) {
        return getApp();
    }

    return initializeApp(firebaseWebConfig);
}

let app = null;
let messaging = null;
let initPromise = null;
let foregroundBound = false;

function isObsoleteCustomWorker(registration) {
    const script = registration.active?.scriptURL || registration.waiting?.scriptURL || registration.installing?.scriptURL || '';
    return script.includes('/service-worker.js') && !script.includes('firebase-messaging-sw.js');
}

async function unregisterObsoleteWorkers() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const registrations = await navigator.serviceWorker.getRegistrations();
    await Promise.all(
        registrations.filter(isObsoleteCustomWorker).map((registration) => registration.unregister()),
    );
}

export async function getMessagingRegistration() {
    await unregisterObsoleteWorkers();
    const registration = await navigator.serviceWorker.register(MESSAGING_SW_SCRIPT);
    await navigator.serviceWorker.ready;
    return registration;
}

async function showForegroundNotification(payload) {
    const title = payload?.notification?.title || payload?.data?.title || 'Control TB';
    const body = payload?.notification?.body || payload?.data?.body || '';
    const url = payload?.data?.url || payload?.data?.link || '/pasien/notifikasi';
    const registration = await navigator.serviceWorker.ready;

    await registration.showNotification(title, {
        body,
        icon: '/menkes192.png',
        badge: '/menkes192.png',
        data: {
            ...(payload?.data || {}),
            url,
        },
    });
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
        await getMessagingRegistration();
        app = getFirebaseApp();
        if (!messaging) {
            messaging = getMessaging(app);
        }
        if (!foregroundBound) {
            onMessage(messaging, (payload) => {
                void showForegroundNotification(payload);
            });
            foregroundBound = true;
        }

        return messaging;
    })().catch((error) => {
        console.error('[FCM] messaging init failed', error);
        messaging = null;
        initPromise = null;
        throw error;
    });

    return initPromise;
}

async function tokenFromCanonicalWorker(vapidKey) {
    const registration = await getMessagingRegistration();
    const token = await getToken(messaging, {
        vapidKey,
        serviceWorkerRegistration: registration,
    });

    if (!token) {
        throw new Error('Firebase tidak mengembalikan token. Izinkan notifikasi lalu coba lagi.');
    }

    return token;
}

export const getFBToken = async (vapidPublicKey) => {
    await initFirebaseMessaging();
    const vapidKey = (vapidPublicKey || firebaseVapidKey || '').trim();
    if (!vapidKey) {
        throw new Error('VAPID public key is missing');
    }

    return tokenFromCanonicalWorker(vapidKey);
};

export async function peekFBToken(vapidPublicKey) {
    if (typeof Notification === 'undefined' || Notification.permission !== 'granted') {
        return '';
    }

    try {
        return await getFBToken(vapidPublicKey);
    } catch (error) {
        console.error('[FCM] peek token failed', error);
        return '';
    }
}

export const clearFBToken = async () => {
    try {
        await initFirebaseMessaging();
        const registration = await getMessagingRegistration();
        const subscription = await registration.pushManager.getSubscription();
        if (subscription) {
            await subscription.unsubscribe();
        }
        if (messaging) {
            await deleteToken(messaging);
        }
        return true;
    } catch (error) {
        console.error('[FCM] clear token failed', error);
        return false;
    }
};

export async function resetCurrentDeviceMessaging(vapidPublicKey) {
    const vapidKey = (vapidPublicKey || firebaseVapidKey || '').trim();
    if (!vapidKey) {
        throw new Error('VAPID public key is missing');
    }

    await initFirebaseMessaging();

    if (messaging) {
        try {
            await deleteToken(messaging);
        } catch (error) {
            console.error('[FCM] deleteToken failed', error);
        }
    }

    const registrations = await navigator.serviceWorker.getRegistrations();
    for (const registration of registrations) {
        const script = registration.active?.scriptURL || registration.waiting?.scriptURL || registration.installing?.scriptURL || '';
        if (isObsoleteCustomWorker(registration)) {
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await subscription.unsubscribe();
            }
            await registration.unregister();
            continue;
        }
        if (script.includes('firebase-messaging-sw.js')) {
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await subscription.unsubscribe();
            }
        }
    }

    const registration = await navigator.serviceWorker.register(MESSAGING_SW_SCRIPT);
    await navigator.serviceWorker.ready;

    const token = await getToken(messaging, {
        vapidKey,
        serviceWorkerRegistration: registration,
    });

    if (!token) {
        throw new Error('Reset gagal: Firebase tidak mengembalikan token baru.');
    }

    return token;
}

if (typeof window !== 'undefined' && 'serviceWorker' in navigator) {
    void initFirebaseMessaging().catch(() => {});
}
