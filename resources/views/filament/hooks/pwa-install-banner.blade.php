{{--
    PWA Install Banner
    ==================
    This banner captures the `beforeinstallprompt` event and stores it
    so a custom UI button can re-trigger the native install dialog.
    This handles the case where a user previously dismissed the browser's
    automatic prompt — they can still install the app from here.

    The banner is hidden once:
      1) The app is already installed (detected via `display-mode: standalone`)
      2) The user explicitly dismisses this banner (stored in localStorage)
--}}
<div
    x-data="{
        deferredPrompt: null,
        showBanner: false,

        init() {
            // Don't show if already running as a standalone (installed) PWA
            if (window.matchMedia('(display-mode: standalone)').matches) {
                return;
            }

            // Don't show if the user has already permanently dismissed this banner
            if (localStorage.getItem('pwa_banner_dismissed') === 'true') {
                return;
            }

            // Capture the native browser install prompt event
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault(); // Stop the browser from showing its own prompt
                this.deferredPrompt = e;
                this.showBanner = true;
            });
        },

        async install() {
            if (!this.deferredPrompt) return;
            this.deferredPrompt.prompt();
            const { outcome } = await this.deferredPrompt.userChoice;
            this.deferredPrompt = null;
            this.showBanner = false;
            if (outcome === 'accepted') {
                localStorage.setItem('pwa_banner_dismissed', 'true');
            }
        },

        dismiss() {
            this.showBanner = false;
            localStorage.setItem('pwa_banner_dismissed', 'true');
        }
    }"
    x-init="init()"
>
    {{-- Floating banner at the bottom of the screen --}}
    <div
        x-show="showBanner"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        style="display: none; position: fixed; bottom: 1rem; left: 1rem; right: 1rem; z-index: 9999;"
    >
        <div style="
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: white;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 10px 40px rgba(79, 70, 229, 0.4);
        ">
            {{-- App Icon --}}
            <div style="flex-shrink: 0;">
                <img src="/images/icon-192.png" alt="App Icon" style="width: 40px; height: 40px; border-radius: 0.5rem;" onerror="this.style.display='none'">
            </div>

            {{-- Text --}}
            <div style="flex: 1; min-width: 0;">
                <p style="font-weight: 700; font-size: 0.875rem; margin: 0 0 0.15rem 0;">Install Petty Cash ERP</p>
                <p style="font-size: 0.75rem; opacity: 0.85; margin: 0;">Add to your home screen for quick access.</p>
            </div>

            {{-- Install Button --}}
            <button
                @click="install()"
                style="
                    flex-shrink: 0;
                    background: white;
                    color: #4f46e5;
                    border: none;
                    border-radius: 0.5rem;
                    padding: 0.4rem 0.9rem;
                    font-weight: 700;
                    font-size: 0.8rem;
                    cursor: pointer;
                    white-space: nowrap;
                "
            >
                Install
            </button>

            {{-- Dismiss Button --}}
            <button
                @click="dismiss()"
                style="
                    flex-shrink: 0;
                    background: transparent;
                    border: none;
                    color: white;
                    opacity: 0.75;
                    cursor: pointer;
                    padding: 0.25rem;
                    line-height: 1;
                "
                title="Dismiss"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 18px; height: 18px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
</div>
