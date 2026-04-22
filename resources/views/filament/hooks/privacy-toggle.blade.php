<div 
    x-data="{ 
        privacy: localStorage.getItem('privacy_mode') === 'true',
        toggle() {
            this.privacy = !this.privacy;
            localStorage.setItem('privacy_mode', this.privacy);
            this.updateBody();
        },
        updateBody() {
            if (this.privacy) {
                document.body.classList.add('privacy-mode');
            } else {
                document.body.classList.remove('privacy-mode');
            }
        }
    }" 
    x-init="updateBody()"
    class="flex items-center gap-4 mr-4"
>
    <!-- Push Notifications Bell Button -->
    <div 
        x-data="{ 
            showButton: false,
            async checkStatus() {
                if (!('Notification' in window)) return;
                
                // Show button if permission is default (not yet granted/denied)
                if (Notification.permission === 'default') {
                    this.showButton = true;
                } else if (Notification.permission === 'granted') {
                    // Check if actually subscribed in Service Worker
                    if ('serviceWorker' in navigator) {
                        try {
                            const registration = await navigator.serviceWorker.getRegistration();
                            if (registration) {
                                const subscription = await registration.pushManager.getSubscription();
                                this.showButton = !subscription; // Show if NOT subscribed
                            }
                        } catch (e) {
                           console.error('Error checking subscription', e);
                        }
                    }
                }
            }
        }"
        x-init="
            checkStatus();
            // Listen for successful subscription event to hide button immediately
            window.addEventListener('push-subscribed', () => { showButton = false; });
        "
        x-show="showButton"
        style="display: none;"
    >
        <button 
            type="button" 
            onclick="subscribeToPushNotifications()"
            class="flex items-center gap-1.5 px-3 py-1 text-xs font-semibold text-primary-600 bg-primary-50 rounded-full hover:bg-primary-100 transition-colors focus:outline-none dark:bg-primary-500/10 dark:text-primary-400 dark:hover:bg-primary-500/20"
            title="Enable Push Notifications"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5 animate-pulse">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12.75 19.5v-.75a7.5 7.5 0 00-7.5-7.5H4.5m0-6.75h.75c7.87 0 14.25 6.38 14.25 14.25v.75M6 18.75a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
            </svg>
            <span>Enable Alerts</span>
        </button>
    </div>

    <button 
        @click="toggle()"
        type="button" 
        class="text-gray-500 hover:text-primary-500 dark:text-gray-400 dark:hover:text-primary-400 focus:outline-none"
        :title="privacy ? 'Show Amounts' : 'Hide Amounts'"
    >
        <!-- Eye Slash (Hidden) -->
        <svg x-show="privacy" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
        </svg>

        <!-- Eye (Visible) -->
        <svg x-show="!privacy" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
    </button>

    <style>
        body.privacy-mode .privacy-mask {
            filter: blur(6px);
            transition: filter 0.3s ease;
            cursor: pointer;
            user-select: none;
        }
        
        body.privacy-mode .privacy-mask:hover {
            filter: blur(0);
        }
    </style>
</div>
