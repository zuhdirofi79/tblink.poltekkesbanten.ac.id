/* eslint-disable no-undef */
importScripts('/firebase-sw-config.js');
importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-messaging-compat.js');

const firebaseWebConfig = self.__FIREBASE_WEB_CONFIG__;

if (firebaseWebConfig && firebaseWebConfig.projectId && firebaseWebConfig.apiKey) {
    firebase.initializeApp(firebaseWebConfig);
    firebase.messaging();
}

self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const data = (event.notification && event.notification.data) || {};
    const targetUrl = data.url || data.link || '/pasien/notifikasi';
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
