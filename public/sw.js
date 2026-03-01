const CACHE_NAME = 'petty-cash-erp-v1-fixed';
const urlsToCache = [
    '/manifest.json',
    '/manifest-vouchers.json',
    '/images/icon-192.png',
    '/images/icon-512.png'
];

self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(urlsToCache))
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request).catch(() => caches.match(event.request)));
        return;
    }
    event.respondWith(
        caches.match(event.request).then((response) => response || fetch(event.request))
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => Promise.all(
            cacheNames.map((cacheName) => {
                if (cacheName !== CACHE_NAME) return caches.delete(cacheName);
            })
        )).then(() => self.clients.claim())
    );
});

// --- PUSH NOTIFICATION LISTENER ---
self.addEventListener('push', function (e) {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    let data = {};
    if (e.data) {
        try {
            data = e.data.json();
        } catch (err) {
            data = { title: 'Notification', body: e.data.text() };
        }
    }

    const title = data.title || 'Petty Cash Notification';
    const options = {
        body: data.body || 'You have a new update.',
        icon: data.icon || '/images/icon-192.png',
        badge: '/images/icon-192.png',
        data: {
            url: data.action || '/'
        },
        vibrate: data.vibrate || [100, 50, 100]
    };

    e.waitUntil(self.registration.showNotification(title, options));
});

// --- NOTIFICATION CLICK LISTENER ---
self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const urlToOpen = event.notification.data.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
            // Check if there is already a window/tab open with the target URL
            for (let i = 0; i < windowClients.length; i++) {
                const client = windowClients[i];
                if (client.url === urlToOpen && 'focus' in client) {
                    return client.focus();
                }
            }
            // If not, open a new window/tab
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});
