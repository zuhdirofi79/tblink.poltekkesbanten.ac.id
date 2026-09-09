const CACHE_VERSION = 'controltb-sw-v3';

importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: 'AIzaSyCaDffhRWeG-Sq-N93koMHF8Fvp8ZvfPIc',
    authDomain: 'control-tb.firebaseapp.com',
    projectId: 'control-tb',
    storageBucket: 'control-tb.firebasestorage.app',
    messagingSenderId: '434051782845',
    appId: '1:434051782845:web:7084ea167df786c242bfd4',
});

const messaging = firebase.messaging();

function notificationOptionsFromPayload(payload) {
    const data = (payload && payload.data) || {};
    const notif = (payload && payload.notification) || {};
    const title = notif.title || data.title || 'Pengingat Minum Obat';
    const body = notif.body || data.body || 'Saatnya minum obat Anda.';
    const icon = data.icon || notif.icon || '/menkes192.png';
    const url = data.url || data.link || '/dashboard';
    const tag = data.tag || 'controltb-reminder';

    return {
        title: title,
        options: {
            body: body,
            icon: icon,
            badge: '/menkes192.png',
            tag: tag,
            renotify: true,
            data: {
                url: url,
            },
        },
    };
}

const recentNotifications = {};

function showPushNotification(payload) {
    const parsed = notificationOptionsFromPayload(payload || {});
    const key = parsed.title + '|' + parsed.options.body + '|' + parsed.options.tag;
    const now = Date.now();
    if (recentNotifications[key] && now - recentNotifications[key] < 2000) {
        return Promise.resolve();
    }
    recentNotifications[key] = now;
    return self.registration.showNotification(parsed.title, parsed.options);
}

messaging.onBackgroundMessage(function (payload) {
    return showPushNotification(payload);
});

self.addEventListener('push', function (event) {
    event.waitUntil(
        (async function () {
            let payload = {};
            try {
                payload = event.data ? event.data.json() : {};
            } catch (err) {
                payload = {
                    notification: {
                        title: 'Pengingat Minum Obat',
                        body: event.data ? event.data.text() : 'Saatnya minum obat Anda.',
                    },
                };
            }
            // FCM wraps some messages as { notification, data } or { data }.
            if (payload.notification || payload.data) {
                return showPushNotification(payload);
            }
            return showPushNotification({
                notification: {
                    title: payload.title || 'Pengingat Minum Obat',
                    body: payload.body || 'Saatnya minum obat Anda.',
                },
                data: payload,
            });
        })(),
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url) || '/dashboard';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if (client.url && 'focus' in client) {
                    try {
                        const clientPath = new URL(client.url).pathname;
                        const targetPath = new URL(targetUrl, self.location.origin).pathname;
                        if (clientPath === targetPath || client.url.indexOf(self.location.origin) === 0) {
                            return client.focus();
                        }
                    } catch (err) {
                        return client.focus();
                    }
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        }),
    );
});

self.addEventListener('install', function () {
    self.skipWaiting();
    void CACHE_VERSION;
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches
            .keys()
            .then(function (keys) {
                return Promise.all(
                    keys.map(function (key) {
                        return caches.delete(key);
                    }),
                );
            })
            .then(function () {
                return self.clients.claim();
            }),
    );
});

self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(function () {
            return caches.match(event.request);
        }),
    );
});
