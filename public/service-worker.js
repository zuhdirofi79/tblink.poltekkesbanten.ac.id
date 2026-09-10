const CACHE_VERSION = 'controltb-sw-v6';
const DIAG_DB = 'controltb-push-diag';

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
    const traceId = data.push_trace_id || '';

    return {
        title: title,
        traceId: traceId,
        ackReceived: data.ack_received || '',
        ackDisplayed: data.ack_displayed || '',
        options: {
            body: body,
            icon: icon,
            badge: data.badge || '/menkes192.png',
            tag: tag,
            renotify: true,
            data: {
                url: url,
                push_trace_id: traceId,
            },
        },
    };
}

const recentNotifications = {};

function openDiagDb() {
    return new Promise(function (resolve, reject) {
        const req = indexedDB.open(DIAG_DB, 1);
        req.onupgradeneeded = function () {
            if (!req.result.objectStoreNames.contains('latest')) {
                req.result.createObjectStore('latest', { keyPath: 'key' });
            }
        };
        req.onsuccess = function () {
            resolve(req.result);
        };
        req.onerror = function () {
            reject(req.error);
        };
    });
}

function persistDiag(entry) {
    return openDiagDb()
        .then(function (db) {
            return new Promise(function (resolve) {
                const tx = db.transaction(['latest'], 'readwrite');
                tx.objectStore('latest').put(Object.assign({ key: 'last_background' }, entry));
                tx.oncomplete = function () {
                    resolve();
                };
                tx.onerror = function () {
                    resolve();
                };
            });
        })
        .catch(function () {
            return undefined;
        });
}

function readDiag() {
    return openDiagDb()
        .then(function (db) {
            return new Promise(function (resolve) {
                const tx = db.transaction(['latest'], 'readonly');
                const req = tx.objectStore('latest').get('last_background');
                req.onsuccess = function () {
                    resolve(req.result || null);
                };
                req.onerror = function () {
                    resolve(null);
                };
            });
        })
        .catch(function () {
            return null;
        });
}

function pingAck(url) {
    if (!url) {
        return Promise.resolve();
    }
    return fetch(url, { keepalive: true, credentials: 'omit' }).catch(function () {
        return undefined;
    });
}

function notifyClients(parsed) {
    return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
        clientList.forEach(function (client) {
            client.postMessage({
                type: 'controltb-fcm-background',
                title: parsed.title,
                tag: parsed.options.tag,
                trace_id: parsed.traceId,
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
    console.info('[SW] showNotification invoked', parsed.options.tag, parsed.traceId);

    return pingAck(parsed.ackReceived)
        .then(function () {
            return persistDiag({
                last_background_push_received_at: now,
                last_background_notification_requested_at: now,
                title: parsed.title,
                trace_id: parsed.traceId,
            });
        })
        .then(function () {
            return self.registration.showNotification(parsed.title, parsed.options);
        })
        .then(function () {
            return pingAck(parsed.ackDisplayed);
        })
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
            return showPushNotification(payload);
        })(),
    );
});

self.addEventListener('message', function (event) {
    if (!event.data || event.data.type !== 'GET_PUSH_DIAG') {
        return;
    }
    const source = event.source;
    event.waitUntil(
        readDiag().then(function (diag) {
            if (source) {
                source.postMessage({ type: 'controltb-push-diag', diag: diag });
            }
        }),
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url) || '/dashboard';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if (client.url && client.url.indexOf(self.location.origin) === 0 && 'focus' in client) {
                    return client.focus().then(function () {
                        if (typeof client.navigate === 'function') {
                            return client.navigate(targetUrl);
                        }
                    });
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
