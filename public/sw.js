const CACHE_NAME = 'petty-cash-erp-v1-fixed';
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

// --- PUSH NOTIFICATIONS ---

self.addEventListener('push', function (event) {
    if (!event.data) {
        console.log('[SW] Push received with no data.');
        return;
    }

    try {
        const payload = event.data.json();
        const title = payload.title || 'Petty Cash ERP';
        const options = {
            body: payload.body,
            icon: payload.icon || '/images/icon-192.png',
            badge: payload.badge || '/images/badge-72.png',
            tag: payload.tag || 'default',
            data: payload.data || {}
        };

        if (payload.actions) {
            options.actions = payload.actions;
        }

        event.waitUntil(
            self.registration.showNotification(title, options)
        );
    } catch (e) {
        // Fallback for simple text payloads
        console.warn('[SW] Push data was not JSON:', e);
        event.waitUntil(
            self.registration.showNotification('Petty Cash ERP', {
                body: event.data.text(),
                icon: '/images/icon-192.png'
            })
        );
    }
});

self.addEventListener('notificationclick', function (event) {
    console.log('[SW] Notification click received.', event.notification.data);
    event.notification.close();

    let targetUrl = '/'; // Default fallback

    // Attempt to extract the URL from the notification data
    if (event.notification.data && event.notification.data.url) {
        targetUrl = event.notification.data.url;
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            // If the user already has the tab open, focus it and navigate
            for (let i = 0; i < windowClients.length; i++) {
                const client = windowClients[i];
                if (client.url && 'focus' in client) {
                    client.focus();
                    return client.navigate(targetUrl);
                }
            }
            // If the app is closed, open a new window
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
