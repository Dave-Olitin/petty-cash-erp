{{-- PWA & Login Page Head Injections --}}
@if(filament()->getCurrentPanel()->getId() === 'vouchers')
    <link rel="manifest" href="/manifest-vouchers.json">
    <meta name="theme-color" content="#f59e0b">
@else
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#3b82f6">
@endif

@if(filament()->getCurrentPanel()->getId() === 'admin')
<style>
    /* Login Page Background — Admin panel only */
    .fi-simple-layout {
        background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        min-height: 100vh;
    }
    .fi-simple-main {
        background-color: rgba(255, 255, 255, 0.95);
        border-radius: 1rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }
</style>
@endif

<script>
(function () {
    // ── Service Worker & Push Notifications ─────────────────────────────
    if (!('serviceWorker' in navigator)) {
        console.warn('[PWA] Service workers not supported.');
        return;
    }
    if (!('PushManager' in window)) {
        console.warn('[PWA] Web Push not supported in this browser.');
        return;
    }

    const VAPID_PUBLIC_KEY = '{!! config("webpush.vapid.public_key") !!}';
    const CSRF_TOKEN       = '{{ csrf_token() }}';

    // Convert URL-safe Base64 VAPID key to Uint8Array
    function urlB64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = atob(base64);
        const output  = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; i++) {
            output[i] = rawData.charCodeAt(i);
        }
        return output;
    }

    // Send subscription keys to our Laravel backend
    function saveSubscription(subscription) {
        const sub = subscription.toJSON();
        console.log('[Push] Saving subscription to server...', sub.endpoint.substring(0, 60) + '…');

        fetch('/push/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':  CSRF_TOKEN,
                'Accept':        'application/json',
            },
            body: JSON.stringify({
                endpoint: sub.endpoint,
                keys:     sub.keys,
            }),
        })
        .then(res => {
            if (!res.ok) {
                return res.text().then(txt => { throw new Error('HTTP ' + res.status + ': ' + txt); });
            }
            console.log('[Push] Subscription saved ✅');
        })
        .catch(err => console.error('[Push] Failed to save subscription:', err));
    }

    // Subscribe (or re-use existing subscription)
    function subscribeToPush(registration) {
        if (!VAPID_PUBLIC_KEY) {
            console.warn('[Push] VAPID public key missing in config.');
            return;
        }

        const appServerKey = urlB64ToUint8Array(VAPID_PUBLIC_KEY);

        // Check if already subscribed
        registration.pushManager.getSubscription()
            .then(existing => {
                if (existing) {
                    console.log('[Push] Already subscribed — refreshing server record.');
                    saveSubscription(existing);
                    return;
                }
                console.log('[Push] Creating new push subscription…');
                return registration.pushManager.subscribe({
                    userVisibleOnly:      true,
                    applicationServerKey: appServerKey,
                });
            })
            .then(subscription => {
                if (subscription) saveSubscription(subscription);
            })
            .catch(err => console.error('[Push] Subscription failed:', err));
    }

    // Register SW then handle permissions
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js?v=3')
            .then(function (registration) {
                console.log('[PWA] ServiceWorker registered. Scope:', registration.scope);

                const currentPermission = Notification.permission;
                console.log('[Push] Notification permission:', currentPermission);

                if (currentPermission === 'granted') {
                    // Already allowed — just make sure our subscription is on the server
                    subscribeToPush(registration);

                } else if (currentPermission === 'default') {
                    // Not yet asked — prompt the user
                    Notification.requestPermission().then(permission => {
                        console.log('[Push] User responded:', permission);
                        if (permission === 'granted') {
                            subscribeToPush(registration);
                        }
                    });

                } else {
                    // 'denied' — user blocked notifications; nothing we can do
                    console.warn('[Push] Notifications are BLOCKED by the user in browser settings.');
                }
            })
            .catch(err => console.error('[PWA] ServiceWorker registration failed:', err));
    });
})();
</script>
