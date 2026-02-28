const CACHE_NAME = 'petty-cash-erp-v3';
const urlsToCache = [
    '/manifest.json',
    '/images/icon-192.png',
    '/images/icon-512.png'
];

self.addEventListener('install', (event) => {
    self.skipWaiting(); // Force activation
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                return cache.addAll(urlsToCache);
            })
    );
});

self.addEventListener('fetch', (event) => {
    // For navigation requests (HTML pages), always go to network first
    if (event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request));
        return;
    }

    // For other requests, try cache first, then network
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                if (response) {
                    return response;
                }
                return fetch(event.request);
            })
    );
});

self.addEventListener('activate', (event) => {
    const cacheWhitelist = [CACHE_NAME];
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheWhitelist.indexOf(cacheName) === -1) {
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim()) // Take control immediately
    );
});

self.addEventListener('push', function (e) {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    if (e.data) {
        let msg;
        try {
            msg = e.data.json();
        } catch (err) {
            console.error('[SW] Push payload is not valid JSON:', err);
            msg = {
                title: 'New Notification',
                body: e.data.text() || 'You have a new update.',
                icon: '/images/icon-192.png'
            };
        }

        e.waitUntil(
            self.registration.showNotification(msg.title || 'Notification', {
                body: msg.body || '',
                icon: msg.icon || '/images/icon-192.png',
                badge: msg.badge || '/images/icon-192.png',
                data: msg.data || null,
                actions: msg.actions || [],
                tag: msg.tag || 'petty-cash-erp'
            })
        );
    }
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            let targetUrl = event.notification.data?.url || '/admin';

            for (let i = 0; i < clientList.length; i++) {
                let client = clientList[i];
                if (client.url.includes('/admin') || client.url.includes('/vouchers') && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
