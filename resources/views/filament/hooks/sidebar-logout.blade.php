@auth
<div class="p-4 border-t border-gray-200 dark:border-gray-700">
    <form method="POST" action="{{ filament()->getLogoutUrl() }}">
        @csrf
        <button type="submit"
            class="w-full flex items-center gap-3 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 rounded-lg hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400 transition-all duration-150 group">
            <x-heroicon-o-arrow-left-start-on-rectangle class="w-5 h-5 flex-shrink-0 group-hover:scale-110 transition-transform" />
            <span>Log Out</span>
        </button>
    </form>
</div>
@endauth
