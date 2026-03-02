{{-- PWA & Login Page Head Injections --}}
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#3b82f6">
<meta name="csrf-token" content="{{ csrf_token() }}">

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
            }, function(err) {
                console.warn("ServiceWorker registration failed:", err);
            });
        });
    }

    async function subscribeToPushNotifications() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            alert('Push notifications are not supported by your browser.');
            return;
        }

        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                console.warn('Notification permission denied.');
                return;
            }

            const registration = await navigator.serviceWorker.ready;
            
            // Check if already subscribed
            let subscription = await registration.pushManager.getSubscription();
            
            if (!subscription) {
                const applicationServerKey = '{{ env('VAPID_PUBLIC_KEY') }}';
                if (!applicationServerKey) {
                    console.error('VAPID public key is missing from environment.');
                    return;
                }

                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(applicationServerKey)
                });
            }

            // Send subscription to the backend
            const response = await fetch('/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(subscription)
            });

            if (response.ok) {
                console.log('Successfully subscribed to push notifications.');
                // Fire a custom event to hide the subscribe button
                window.dispatchEvent(new CustomEvent('push-subscribed'));
            } else {
                console.error('Failed to store push subscription on server.');
            }
        } catch (error) {
            console.error('Error subscribing to push notifications:', error);
        }
    }

    // Utility function required by PushManager
    function urlBase64ToUint8Array(base64String) {
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
</script>
