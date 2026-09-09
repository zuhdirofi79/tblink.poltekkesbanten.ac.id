const CACHE_VERSION = 'controltb-sw-v5';

importScripts('/firebase-sw-config.js');
importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-messaging-compat.js');

const firebaseWebConfig = self.__FIREBASE_WEB_CONFIG__;

if (!firebaseWebConfig || !firebaseWebConfig.projectId || !firebaseWebConfig.apiKey) {
    console.error('[SW] firebase-sw-config.js is missing web config');
} else {
    firebase.initializeApp(firebaseWebConfig);
}

const messaging = firebaseWebConfig && firebaseWebConfig.projectId ? firebase.messaging() : null;

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
            badge: data.badge || '/menkes192.png',
            tag: tag,
            renotify: true,
            data: {
                url: url,
            },
        },
    };
}

const recentNotifications = {};

function notifyClients(parsed) {
    return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
        clientList.forEach(function (client) {
            client.postMessage({
                type: 'controltb-fcm-background',
                title: parsed.title,
                tag: parsed.options.tag,
                at: Date.now(),
            });
        });
    });
}

function showPushNotification(payload) {
    console.info('[SW] notification payload parsed');
    const parsed = notificationOptionsFromPayload(payload || {});
    const key = parsed.title + '|' + parsed.options.body + '|' + parsed.options.tag;
    const now = Date.now();
    if (recentNotifications[key] && now - recentNotifications[key] < 2500) {
        console.info('[SW] showNotification skipped (duplicate)', parsed.options.tag);
        return Promise.resolve();
    }
    recentNotifications[key] = now;
    console.info('[SW] showNotification invoked', parsed.options.tag);
    return self.registration
        .showNotification(parsed.title, parsed.options)
        .then(function () {
            return notifyClients(parsed);
        });
}

if (messaging) {
    messaging.onBackgroundMessage(function (payload) {
        console.info('[SW] onBackgroundMessage');
        return showPushNotification(payload);
    });
}

self.addEventListener('push', function (event) {
    console.info('[SW] push received');
    event.waitUntil(
        (async function () {
            let payload = {};
            try {
                payload = event.data ? event.data.json() : {};
            } catch (err) {
                payload = {
                    data: {
                        title: 'Pengingat Minum Obat',
                        body: event.data ? event.data.text() : 'Saatnya minum obat Anda.',
                    },
                };
            }

            // Data-only FCM: always display via showNotification.
            // If a notification payload is present, Chrome may already display it.
            // Dedup by tag/title/body within 2.5s prevents a second banner.
            return showPushNotification(payload);
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
                        if (client.url.indexOf(self.location.origin) === 0) {
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
