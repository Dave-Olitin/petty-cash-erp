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
// CRITICAL: We MUST call event.waitUntil(showNotification(...)) in every
// code-path, otherwise Chrome shows "This site has been updated in the
// background" instead of the actual notification.
self.addEventListener('push', function (event) {
    var title = 'Petty Cash ERP';
    var options = {
        body: 'You have a new notification.',
        icon: '/images/icon-192.png',
        badge: '/images/icon-192.png',
        tag: 'petty-cash-notification',
        data: { url: '/' }
    };

    if (event.data) {
        try {
            var payload = event.data.json();
            title = payload.title || title;
            options.body = payload.body || options.body;
            options.icon = payload.icon || options.icon;
            options.badge = payload.badge || options.badge;
            options.tag = payload.tag || options.tag;
            if (payload.data) {
                options.data = payload.data;
            }
        } catch (e) {
            // Payload wasn't JSON — use the raw text as body
            var text = event.data.text();
            if (text) {
                options.body = text;
            }
        }
    }

    // Always show a notification — this is the critical line
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
