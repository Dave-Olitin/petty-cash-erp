<div 
    x-data="{ 
        installPrompt: null,
        isInstalled: false,
        init() {
            window.addEventListener('beforeinstallprompt', (e) => {
                // Prevent the mini-infobar from appearing on mobile
                e.preventDefault();
                // Stash the event so it can be triggered later.
                this.installPrompt = e;
                console.log('PWA Install Prompt captured');
            });

            window.addEventListener('appinstalled', () => {
                this.installPrompt = null;
                this.isInstalled = true;
                console.log('PWA Installed');
            });

            // Check if already in standalone mode
            if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
                this.isInstalled = true;
            }
        },
        install() {
            if (this.installPrompt) {
                this.installPrompt.prompt();
                this.installPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the A2HS prompt');
                    } else {
                        console.log('User dismissed the A2HS prompt');
                    }
                    this.installPrompt = null;
                });
            }
        }
    }" 
    x-show="installPrompt && !isInstalled"
    class="flex items-center mr-4"
    style="display: none;" 
>
    <button 
        @click="install()"
        type="button" 
        class="flex items-center gap-2 px-3 py-1 text-sm font-medium text-white bg-primary-600 hover:bg-primary-500 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-600 transition-colors"
        title="Install App"
    >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
        </svg>
        <span class="hidden md:inline">Install App</span>
    </button>
</div>
