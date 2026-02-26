<div class="relative w-full rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900 transition-all duration-300">
    <!-- Decorative Header Band -->
    <div class="absolute inset-x-0 top-0 h-2 rounded-t-2xl {{ $get('type') === 'petty_cash' ? 'bg-info-500' : 'bg-warning-500' }}"></div>

    <div class="flex items-center justify-between mb-6 mt-2">
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Erick TR CO
            </h3>
            <h2 class="text-lg font-black text-gray-900 dark:text-white uppercase mt-1">
                {{ $get('type') === 'petty_cash' ? 'Petty Cash Request' : 'Payment Voucher' }}
            </h2>
        </div>
        
        <!-- Status Badge Simulation -->
        <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20">
            DRAFT
        </span>
    </div>

    <div class="space-y-4">
        <!-- Payee Row -->
        <div class="border-b border-gray-100 dark:border-white/5 pb-3">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Paid To / Payee</p>
            <p class="text-sm font-semibold text-gray-900 dark:text-white line-clamp-1">
                {{ $get('payee') ?: '—' }}
            </p>
        </div>

        <!-- Amount Row -->
        <div class="border-b border-gray-100 dark:border-white/5 pb-3">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Amount</p>
            <p class="text-2xl font-bold {{ $get('type') === 'petty_cash' ? 'text-info-600 dark:text-info-400' : 'text-warning-600 dark:text-warning-400' }}">
                {{ $get('amount') ? number_format((float) $get('amount'), 2) . ' AED' : '0.00 AED' }}
            </p>
        </div>

        <!-- Category Row -->
        <div class="border-b border-gray-100 dark:border-white/5 pb-3">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Category</p>
            <p class="text-sm text-gray-900 dark:text-white flex items-center gap-2">
                @if($get('category_id'))
                    @php
                        $category = \App\Models\Category::find($get('category_id'));
                    @endphp
                    <span class="inline-block w-2 h-2 rounded-full bg-gray-400"></span>
                    {{ $category ? $category->name : '—' }}
                @else
                    —
                @endif
            </p>
        </div>

        <!-- Description Row -->
        <div class="pt-1">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Description</p>
            <p class="text-sm text-gray-700 dark:text-gray-300 italic line-clamp-3">
                {{ $get('description') ?: 'No description provided.' }}
            </p>
        </div>
    </div>
    
    <!-- Watermark / Footer -->
    <div class="mt-8 flex justify-center border-t border-dashed border-gray-200 dark:border-white/10 pt-4 opacity-50">
        <span class="text-[10px] uppercase tracking-widest text-gray-400">Live Preview</span>
    </div>
</div>
