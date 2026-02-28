{{-- PWA & Login Page Head Injections --}}
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#3b82f6">

<style>
    /* Login Page Background */
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

<script>
    if ("serviceWorker" in navigator) {
        window.addEventListener("load", function() {
            navigator.serviceWorker.register("/sw.js").then(function(registration) {
                console.log("ServiceWorker registered. Scope:", registration.scope);

                // --- Push Notification Subscription ---
                if ('Notification' in window && 'PushManager' in window) {
                    Notification.requestPermission().then((permission) => {
                        if (permission === 'granted') {
                            subscribeUserToPush(registration);
                        }
                    });
                }
            }, function(err) {
                console.warn("ServiceWorker registration failed:", err);
            });
        });

        // Function to convert Base64 string to Uint8Array
        function urlB64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/\-/g, '+')
                .replace(/_/g, '/');

            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);

            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        function subscribeUserToPush(registration) {
            const vapidPublicKey = '{{ config("webpush.vapid.public_key") }}';
            if (!vapidPublicKey) return;

            const applicationServerKey = urlB64ToUint8Array(vapidPublicKey);

            registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKey
            })
            .then(function(subscription) {
                // Send subscription to server
                const subJSON = subscription.toJSON();
                fetch('/push/subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        endpoint: subJSON.endpoint,
                        keys: subJSON.keys
                    })
                }).catch(err => console.error('Push sub save failed:', err));
            })
            .catch(function(err) {
                console.log('Failed to subscribe the user: ', err);
            });
        }
    }
</script>
