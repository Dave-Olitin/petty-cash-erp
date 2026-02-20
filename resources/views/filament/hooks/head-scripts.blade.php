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
            }, function(err) {
                console.warn("ServiceWorker registration failed:", err);
            });
        });
    }
</script>
