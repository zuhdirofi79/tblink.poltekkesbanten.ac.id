/* eslint-disable no-undef */
/*
 * FCM SDK fallback worker. Scope is NOT "/".
 * Page registration uses /service-worker.js at scope "/".
 * This file only handles FCM background display if the SDK
 * auto-registers it under /firebase-cloud-messaging-push-scope.
 */
importScripts('/firebase-sw-config.js');
importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-messaging-compat.js');

const firebaseWebConfig = self.__FIREBASE_WEB_CONFIG__;

if (firebaseWebConfig && firebaseWebConfig.projectId && firebaseWebConfig.apiKey) {
    firebase.initializeApp(firebaseWebConfig);
    const messaging = firebase.messaging();
    messaging.onBackgroundMessage(function (payload) {
        console.info('[SW] fallback firebase-messaging-sw onBackgroundMessage');
        const data = (payload && payload.data) || {};
        const notif = (payload && payload.notification) || {};
        const title = notif.title || data.title || 'Pengingat Minum Obat';
        const body = notif.body || data.body || 'Saatnya minum obat Anda.';
        return self.registration.showNotification(title, {
            body: body,
            icon: data.icon || '/menkes192.png',
            badge: '/menkes192.png',
            tag: data.tag || 'controltb-reminder',
            renotify: true,
            data: {
                url: data.url || data.link || '/dashboard',
            },
        });
    });
}

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url) || '/dashboard';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if (client.url && client.url.indexOf(self.location.origin) === 0 && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        }),
    );
});
