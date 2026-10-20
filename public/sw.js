// Service Worker v3 — 2026-03-01
const CACHE_NAME = 'petty-cash-erp-v3';
const urlsToCache = [
    '/manifest.json',
    '/images/icon-192.png',
    '/images/icon-512.png'
];

self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(urlsToCache))
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((name) => {
                    if (name !== CACHE_NAME) {
                        return caches.delete(name);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request));
        return;
    }
    event.respondWith(
        caches.match(event.request).then((response) => response || fetch(event.request))
    );
});

// ── Push Notification Handler ───────────────────────────────────────────
self.addEventListener('push', function (event) {
    var title = 'Accounting Software';
    var payload = {};

    if (event.data) {
        try {
            payload = event.data.json();
        } catch (e) {
            var text = event.data.text();
            if (text) {
                payload = { body: text };
            }
        }
    }

    title = payload.title || title;
    var uniqueTag = payload.tag || ('notif-' + Date.now() + '-' + Math.floor(Math.random() * 100000));

    var options = {
        body: payload.body || 'You have a new notification.',
        icon: payload.icon || '/images/voucher-app-icon.png',
        badge: payload.badge || '/images/voucher-app-icon.png',
        tag: uniqueTag,
        timestamp: payload.timestamp || Date.now(),
        data: {
            url: payload.action || (payload.data && payload.data.url) || (payload.data && payload.data.action) || '/'
        },
        vibrate: payload.vibrate || [100, 50, 100],
        requireInteraction: true
    };

    // Always show a notification
    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// ── Notification Click Handler ──────────────────────────────────────────
self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var targetUrl = '/';
    if (event.notification.data && event.notification.data.url) {
        targetUrl = event.notification.data.url;
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            // Focus an existing tab if one is open
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if ((client.url.indexOf('/admin') !== -1 || client.url.indexOf('/vouchers') !== -1) && 'focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }
            // Otherwise open a new window
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
