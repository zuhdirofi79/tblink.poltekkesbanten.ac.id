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
            `[Push] Firebase web config incomplete. Missing: ${[...missing, !firebaseVapidKey ? 'vapidKey' : null].filter(Boolean).join(', ')}`,
        );
    }
}

let app = null;
let messaging = null;

try {
    if (typeof window !== 'undefined' && 'serviceWorker' in navigator) {
        assertFirebaseWebConfig();
        app = initializeApp(firebaseWebConfig);
        messaging = getMessaging(app);
        onMessage(messaging, (payload) => {
            const title = payload?.notification?.title || payload?.data?.title || 'Control TB';
            const body = payload?.notification?.body || payload?.data?.body || '';
            if (Notification.permission === 'granted' && title) {
                navigator.serviceWorker.ready.then((registration) => {
                    registration.showNotification(title, {
                        body,
                        icon: payload?.data?.icon || '/menkes192.png',
                        data: {
                            url: payload?.data?.url || payload?.data?.link || '/dashboard',
                        },
                    });
                });
            }
        });
    }
} catch (error) {
    console.info('[Push] Firebase messaging is unavailable in this browser.', error);
    messaging = null;
}

export async function getPushRegistration() {
    const registration = await navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
    await navigator.serviceWorker.ready;
    return registration;
}

export const getFBToken = async (vapidPublicKey) => {
    if (!messaging) {
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

    return currentToken;
};

export const clearFBToken = async () => {
    if (!messaging) {
        return false;
    }

    try {
        const registration = await getPushRegistration();
        const subscription = await registration.pushManager.getSubscription();
        if (subscription) {
            await subscription.unsubscribe();
        }
        await deleteToken(messaging);
        return true;
    } catch (error) {
        console.info('[Push] Unable to clear FCM token', error);
        return false;
    }
};
