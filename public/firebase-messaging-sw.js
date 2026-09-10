/* eslint-disable no-undef */
/*
 * FCM SDK fallback worker. Scope is NOT "/".
 * Canonical owner is /service-worker.js at scope "/".
 * This file only exists so a stale FCM default registration cannot 404.
 * Hard Re-register unregisters this worker if it is present.
 */
const FALLBACK_SW_VERSION = 'controltb-fcm-fallback-v8';

importScripts('/firebase-sw-config.js');
importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-messaging-compat.js');

const firebaseWebConfig = self.__FIREBASE_WEB_CONFIG__;

function pingAck(url) {
    if (!url) {
        return Promise.resolve();
    }
    return fetch(url, { keepalive: true, credentials: 'omit' }).catch(function () {
        return undefined;
    });
}

self.addEventListener('push', function (event) {
    event.waitUntil(
        (async function () {
            let payload = {};
            try {
                payload = event.data ? event.data.json() : {};
            } catch (err) {
                payload = {};
            }
            const data = (payload && payload.data) || {};
            await pingAck(data.ack_raw || '');
            await pingAck(data.ack_received || '');
            console.info('[SW][FALLBACK] RAW_PUSH_RECEIVED', FALLBACK_SW_VERSION);
        })(),
    );
});

if (firebaseWebConfig && firebaseWebConfig.projectId && firebaseWebConfig.apiKey) {
    firebase.initializeApp(firebaseWebConfig);
    const messaging = firebase.messaging();
    messaging.onBackgroundMessage(function (payload) {
        console.info('[SW] fallback firebase-messaging-sw onBackgroundMessage');
        const data = (payload && payload.data) || {};
        const notif = (payload && payload.notification) || {};
        const title = notif.title || data.title || 'Pengingat Minum Obat';
        const body = notif.body || data.body || 'Saatnya minum obat Anda.';
        return pingAck(data.ack_received)
            .then(function () {
                return self.registration.showNotification(title, {
                    body: body,
                    icon: data.icon || '/menkes192.png',
                    badge: '/menkes192.png',
                    tag: data.tag || 'controltb-reminder',
                    renotify: true,
                    data: {
                        url: data.url || data.link || '/dashboard',
                        push_trace_id: data.push_trace_id || '',
                        ack_clicked: data.ack_clicked || '',
                    },
                });
            })
            .then(function () {
                return pingAck(data.ack_displayed);
            })
            .catch(function (err) {
                const message = err && err.message ? String(err.message).slice(0, 120) : 'showNotification';
                const failed = data.ack_failed
                    ? data.ack_failed + (String(data.ack_failed).indexOf('?') >= 0 ? '&' : '?') + 'err=' + encodeURIComponent(message)
                    : '';
                return pingAck(failed);
            });
    });
}

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const data = (event.notification && event.notification.data) || {};
    const targetUrl = data.url || '/dashboard';
    event.waitUntil(
        pingAck(data.ack_clicked).then(function () {
            return clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
                for (let i = 0; i < clientList.length; i++) {
                    const client = clientList[i];
                    if (client.url && client.url.indexOf(self.location.origin) === 0 && 'focus' in client) {
                        return client.focus();
                    }
                }
                if (clients.openWindow) {
                    return clients.openWindow(targetUrl);
                }
            });
        }),
    );
});
