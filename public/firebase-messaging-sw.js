/* eslint-disable no-undef */
const DEFAULT_PATH = '/dashboard';

function defaultDashboardUrl() {
    return self.location.origin + DEFAULT_PATH;
}

function firstNonEmptyString() {
    for (let i = 0; i < arguments.length; i++) {
        const value = arguments[i];
        if (typeof value === 'string' && value.trim() !== '') {
            return value.trim();
        }
    }
    return '';
}

function extractRawClickUrl(data) {
    if (!data || typeof data !== 'object') {
        return '';
    }

    const fcmMsg = data.FCM_MSG || data.fcm_msg || {};
    const fcmData = fcmMsg.data || {};
    const fcmOptions = fcmMsg.fcmOptions || fcmMsg.fcm_options || {};
    const notification = fcmMsg.notification || data.notification || {};

    return firstNonEmptyString(
        fcmOptions.link,
        notification.click_action,
        notification.clickAction,
        fcmData.url,
        fcmData.link,
        data.url,
        data.link,
    );
}

function sameOriginClickUrl(rawUrl) {
    const fallback = defaultDashboardUrl();
    if (!rawUrl) {
        return fallback;
    }

    try {
        const resolved = new URL(rawUrl, self.location.origin);
        if (resolved.origin !== self.location.origin) {
            return fallback;
        }

        return resolved.href;
    } catch (error) {
        return fallback;
    }
}

function clientPath(url) {
    try {
        return new URL(url, self.location.origin).pathname;
    } catch (error) {
        return '';
    }
}

function openOrFocusDashboard(targetUrl) {
    return clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
        for (let i = 0; i < clientList.length; i++) {
            const client = clientList[i];
            if (!client.url || client.url.indexOf(self.location.origin) !== 0) {
                continue;
            }

            if (typeof client.navigate === 'function') {
                return client.navigate(targetUrl).then(function (navigated) {
                    const next = navigated || client;
                    return typeof next.focus === 'function' ? next.focus() : next;
                });
            }

            if (clientPath(client.url) === DEFAULT_PATH && typeof client.focus === 'function') {
                return client.focus();
            }
        }

        if (clients.openWindow) {
            return clients.openWindow(targetUrl);
        }
    });
}

self.addEventListener('notificationclick', function (event) {
    event.stopImmediatePropagation();
    event.notification.close();
    const data = (event.notification && event.notification.data) || {};
    const targetUrl = sameOriginClickUrl(extractRawClickUrl(data));
    event.waitUntil(openOrFocusDashboard(targetUrl));
});

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
