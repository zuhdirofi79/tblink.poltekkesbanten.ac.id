import { initializeApp } from 'firebase/app';
import { deleteToken, getMessaging, getToken, onMessage } from 'firebase/messaging';

const firebaseConfig = {
    apiKey: 'AIzaSyCaDffhRWeG-Sq-N93koMHF8Fvp8ZvfPIc',
    authDomain: 'control-tb.firebaseapp.com',
    projectId: 'control-tb',
    storageBucket: 'control-tb.firebasestorage.app',
    messagingSenderId: '434051782845',
    appId: '1:434051782845:web:7084ea167df786c242bfd4',
};

const app = initializeApp(firebaseConfig);

let messaging = null;
try {
    if (typeof window !== 'undefined' && 'serviceWorker' in navigator) {
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

    const registration = await getPushRegistration();
    const currentToken = await getToken(messaging, {
        vapidKey: vapidPublicKey,
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
