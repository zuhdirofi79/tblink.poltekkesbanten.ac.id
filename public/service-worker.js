const CACHE_VERSION = 'controltb-sw-v8';
const DIAG_DB = 'controltb-push-diag';
const processedTraces = {};

function pingAck(url) {
    if (!url) {
        return Promise.resolve();
    }
    return fetch(url, { keepalive: true, credentials: 'omit' }).catch(function () {
        return undefined;
    });
}

function openDiagDb() {
    return new Promise(function (resolve, reject) {
        try {
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
        } catch (err) {
            reject(err);
        }
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

function extractData(payload) {
    if (!payload || typeof payload !== 'object') {
        return {};
    }
    if (payload.data && typeof payload.data === 'object') {
        return payload.data;
    }
    return payload;
}

function notificationOptionsFromPayload(payload) {
    const data = extractData(payload);
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
        payloadMode: data.payload_mode || '',
        ackRaw: data.ack_raw || '',
        ackParsed: data.ack_parsed || '',
        ackParseFailed: data.ack_parse_failed || '',
        ackReceived: data.ack_received || '',
        ackDisplayed: data.ack_displayed || '',
        ackFailed: data.ack_failed || '',
        ackClicked: data.ack_clicked || '',
        options: {
            body: body,
            icon: icon,
            badge: data.badge || '/menkes192.png',
            tag: tag,
            renotify: true,
            data: {
                url: url,
                push_trace_id: traceId,
                ack_clicked: data.ack_clicked || '',
            },
        },
    };
}

function notifyClients(parsed, extra) {
    return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
        clientList.forEach(function (client) {
            client.postMessage(
                Object.assign(
                    {
                        type: 'controltb-fcm-background',
                        title: parsed.title,
                        tag: parsed.options.tag,
                        trace_id: parsed.traceId,
                        sw_version: CACHE_VERSION,
                        at: Date.now(),
                    },
                    extra || {},
                ),
            );
        });
    });
}

function markTrace(traceId, stage) {
    if (!traceId) {
        return false;
    }
    const key = traceId + '|' + stage;
    const now = Date.now();
    if (processedTraces[key] && now - processedTraces[key] < 4000) {
        return true;
    }
    processedTraces[key] = now;
    return false;
}

const recentNotifications = {};

function showPushNotification(payload) {
    const parsed = notificationOptionsFromPayload(payload || {});
    if (markTrace(parsed.traceId, 'display')) {
        return Promise.resolve();
    }
    const key = parsed.title + '|' + parsed.options.body + '|' + parsed.options.tag;
    const now = Date.now();
    if (recentNotifications[key] && now - recentNotifications[key] < 2500) {
        return Promise.resolve();
    }
    recentNotifications[key] = now;

    return persistDiag({
        last_background_push_received_at: now,
        last_background_notification_requested_at: now,
        last_display_status: 'DISPLAY_REQUESTED',
        title: parsed.title,
        trace_id: parsed.traceId,
        sw_version: CACHE_VERSION,
    })
        .then(function () {
            return self.registration.showNotification(parsed.title, parsed.options);
        })
        .then(function () {
            return pingAck(parsed.ackDisplayed);
        })
        .then(function () {
            return notifyClients(parsed, { stage: 'DISPLAY_REQUESTED' });
        })
        .catch(function (err) {
            const name = err && err.name ? String(err.name) : 'Error';
            const message = err && err.message ? String(err.message) : 'showNotification failed';
            const failedUrl = parsed.ackFailed
                ? parsed.ackFailed + (parsed.ackFailed.indexOf('?') >= 0 ? '&' : '?') + 'err=' + encodeURIComponent((name + ':' + message).slice(0, 120))
                : '';
            return pingAck(failedUrl).then(function () {
                console.error('[SW] showNotification failed', name, message);
            });
        });
}

function isNotificationPayload(payload) {
    const data = extractData(payload);
    return data.payload_mode === 'notification' || !!(payload && payload.notification && (payload.notification.title || payload.notification.body));
}

async function processRawPush(event) {
    const now = Date.now();
    console.info('[SW][RAW_PUSH_RECEIVED]', CACHE_VERSION);

    let payload = {};
    let parseOk = false;
    try {
        payload = event.data ? event.data.json() : {};
        parseOk = true;
    } catch (err) {
        payload = {};
        parseOk = false;
    }

    const data = extractData(payload);
    const parsed = notificationOptionsFromPayload(payload);

    await persistDiag({
        last_background_push_received_at: now,
        last_raw_push_at: now,
        last_raw_stage: 'RAW_PUSH_RECEIVED',
        parse_ok: parseOk,
        title: parsed.title,
        trace_id: parsed.traceId || data.push_trace_id || '',
        sw_version: CACHE_VERSION,
    });
    await pingAck(parsed.ackRaw || data.ack_raw || '');
    await pingAck(parsed.ackReceived || data.ack_received || '');
    await notifyClients(parsed, { stage: 'RAW_PUSH_RECEIVED', parse_ok: parseOk });

    if (!parseOk) {
        await pingAck(parsed.ackParseFailed || data.ack_parse_failed || '');
        await persistDiag({
            last_background_push_received_at: now,
            last_raw_stage: 'PAYLOAD_PARSE_FAILED',
            parse_ok: false,
            title: parsed.title,
            trace_id: parsed.traceId,
            sw_version: CACHE_VERSION,
        });
        return;
    }

    await pingAck(parsed.ackParsed || data.ack_parsed || '');

    if (isNotificationPayload(payload)) {
        await persistDiag({
            last_background_push_received_at: now,
            last_raw_stage: 'PAYLOAD_PARSED',
            last_display_status: 'CHROME_AUTO_DISPLAY',
            title: parsed.title,
            trace_id: parsed.traceId,
            sw_version: CACHE_VERSION,
        });
        await notifyClients(parsed, { stage: 'PAYLOAD_PARSED', chrome_auto_display: true });
        return;
    }

    await showPushNotification(payload);
}

self.addEventListener('push', function (event) {
    event.waitUntil(processRawPush(event));
});

self.addEventListener('message', function (event) {
    if (!event.data || event.data.type !== 'GET_PUSH_DIAG') {
        return;
    }
    const source = event.source;
    event.waitUntil(
        readDiag().then(function (diag) {
            if (source) {
                source.postMessage({
                    type: 'controltb-push-diag',
                    sw_version: CACHE_VERSION,
                    diag: diag,
                });
            }
        }),
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const data = (event.notification && event.notification.data) || {};
    const targetUrl = data.url || '/dashboard';
    const ackClicked = data.ack_clicked || '';
    event.waitUntil(
        pingAck(ackClicked).then(function () {
            return clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
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
            });
        }),
    );
});

self.addEventListener('install', function (event) {
    self.skipWaiting();
    event.waitUntil(Promise.resolve(CACHE_VERSION));
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
            .catch(function () {
                return undefined;
            })
            .then(function () {
                return self.clients.claim();
            }),
    );
});

self.addEventListener('fetch', function (event) {
    try {
        if (event.request.method !== 'GET') {
            return;
        }
        const url = new URL(event.request.url);
        if (url.pathname.indexOf('/push/') === 0) {
            return;
        }
        if (url.protocol !== 'http:' && url.protocol !== 'https:') {
            return;
        }
        event.respondWith(
            fetch(event.request).catch(function () {
                return caches.match(event.request).then(function (cached) {
                    return cached || Response.error();
                });
            }),
        );
    } catch (err) {
        console.error('[SW] fetch handler error', err && err.message ? err.message : err);
    }
});

try {
    importScripts('/firebase-sw-config.js');
    importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js');
    importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-messaging-compat.js');

    const firebaseWebConfig = self.__FIREBASE_WEB_CONFIG__;
    if (firebaseWebConfig && firebaseWebConfig.projectId && firebaseWebConfig.apiKey) {
        firebase.initializeApp(firebaseWebConfig);
        const messaging = firebase.messaging();
        messaging.onBackgroundMessage(function (payload) {
            console.info('[SW] onBackgroundMessage', CACHE_VERSION);
            const parsed = notificationOptionsFromPayload(payload || {});
            if (markTrace(parsed.traceId, 'background')) {
                return;
            }
            if (isNotificationPayload(payload)) {
                return;
            }
            return showPushNotification(payload);
        });
    }
} catch (err) {
    console.error('[SW] Firebase init skipped; raw push handler remains installed', err && err.message ? err.message : err);
}
