<div class="relative w-full rounded-2xl border border-gray-200 bg-white/60 backdrop-blur-xl p-6 shadow-xl shadow-gray-200/50 dark:border-white/10 dark:bg-gray-900/60 dark:shadow-none transition-all duration-300 hover:shadow-2xl hover:-translate-y-1">
    <!-- Decorative Header Band -->
    <div class="absolute inset-x-0 top-0 h-3 rounded-t-2xl bg-gradient-to-r {{ $get('type') === 'petty_cash' ? 'from-info-400 to-info-600' : 'from-warning-400 to-warning-600' }}"></div>

    <div class="flex items-start justify-between mb-6 mt-2">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $get('type') === 'petty_cash' ? 'bg-info-50 text-info-600 dark:bg-info-500/10 dark:text-info-400' : 'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400' }} shadow-sm ring-1 ring-inset {{ $get('type') === 'petty_cash' ? 'ring-info-500/20' : 'ring-warning-500/20' }}">
                @if($get('type') === 'petty_cash')
                    <x-heroicon-o-banknotes class="w-5 h-5" />
                @else
                    <x-heroicon-o-credit-card class="w-5 h-5" />
                @endif
            </div>
            <div>
                <h3 class="text-[9px] font-black tracking-widest text-gray-400 dark:text-gray-500 uppercase mb-0.5">
                    Erick TR CO
                </h3>
                <h2 class="text-base font-black text-gray-900 dark:text-white tracking-tight">
                    {{ $get('type') === 'petty_cash' ? 'Petty Cash Request' : 'Payment Voucher' }}
                </h2>
            </div>
        </div>
        
        <!-- Status Badge Simulation -->
        <div class="flex flex-col items-end mt-1">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold tracking-wider text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:bg-gray-800 dark:text-gray-300 dark:ring-white/10 shadow-sm border border-transparent">
                <span class="h-1 w-1 rounded-full bg-gray-400 animate-pulse"></span>
                DRAFT
            </span>
        </div>
    </div>

    <div class="rounded-xl bg-gray-50/50 dark:bg-gray-800/30 p-6 space-y-7 border border-gray-100 dark:border-white/5">
        <!-- Amount Row (Prominent) -->
        <div class="pb-6 border-b border-gray-200/60 dark:border-white/10 flex flex-col items-center justify-center text-center relative overflow-hidden">
            <!-- Decorative blurred background blob -->
            <div class="absolute -inset-4 bg-gradient-to-tr {{ $get('type') === 'petty_cash' ? 'from-info-100/40 to-transparent dark:from-info-900/20' : 'from-warning-100/40 to-transparent dark:from-warning-900/20' }} rounded-full blur-2xl -z-10"></div>
            
            <p class="text-[9px] font-black tracking-widest text-gray-400 dark:text-gray-500 uppercase mb-2">Total Amount</p>
            <p class="text-3xl font-black tracking-tight {{ $get('type') === 'petty_cash' ? 'text-info-600 dark:text-info-400' : 'text-warning-600 dark:text-warning-400' }} drop-shadow-sm flex items-end justify-center gap-1">
                {{ $get('amount') ? number_format((float) $get('amount'), 2) : '0.00' }} 
                <span class="text-xs font-bold opacity-70 mb-0.5 tracking-widest">AED</span>
            </p>
        </div>

        <div class="flex flex-col gap-5">
            <!-- Payee Column -->
            <div>
                <p class="text-[9px] font-bold tracking-widest text-gray-500 dark:text-gray-400 uppercase mb-1.5 flex items-center gap-1.5">
                    <x-heroicon-m-user class="w-3 h-3 opacity-70" /> Paid To / Payee
                </p>
                <div class="bg-white dark:bg-gray-900 px-4 py-3 rounded-lg border border-gray-200 dark:border-white/10 shadow-sm flex items-center min-h-[44px] transition-colors hover:border-gray-300 dark:hover:border-white/20">
                    <p class="text-xs font-bold text-gray-900 dark:text-white leading-tight break-all">
                        {{ $get('payee') ?: '—' }}
                    </p>
                </div>
            </div>

            <!-- Category Column -->
            <div>
                <p class="text-[9px] font-bold tracking-widest text-gray-500 dark:text-gray-400 uppercase mb-1.5 flex items-center gap-1.5">
                    <x-heroicon-m-tag class="w-3 h-3 opacity-70" /> Category
                </p>
                <div class="bg-white dark:bg-gray-900 px-4 py-3 rounded-lg border border-gray-200 dark:border-white/10 shadow-sm flex items-center min-h-[44px] transition-colors hover:border-gray-300 dark:hover:border-white/20">
                    @if($get('category_id'))
                        @php
                            $category = \App\Models\Category::find($get('category_id'));
                        @endphp
                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 dark:text-gray-200 leading-tight">
                            <span class="inline-block shrink-0 w-2 h-2 rounded-full {{ $get('type') === 'petty_cash' ? 'bg-info-500' : 'bg-warning-500' }} shadow-sm ring-1 ring-white dark:ring-gray-900"></span>
                            {{ $category ? $category->name : '—' }}
                        </span>
                    @else
                        <span class="text-xs font-medium text-gray-400 dark:text-gray-500 italic">—</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Description Row -->
        <div class="pt-2">
            <p class="text-[9px] font-bold tracking-widest text-gray-500 dark:text-gray-400 uppercase mb-1.5 flex items-center gap-1.5">
                <x-heroicon-m-document-text class="w-3 h-3 opacity-70" /> Description
            </p>
            <div class="bg-white dark:bg-gray-900 rounded-lg p-4 border border-gray-200 dark:border-white/10 shadow-sm min-h-[70px] transition-colors hover:border-gray-300 dark:hover:border-white/20 overflow-hidden">
                @if($get('description'))
                    <p class="text-[11px] font-medium text-gray-700 dark:text-gray-300 leading-relaxed break-all p-1">
                        {{ $get('description') }}
                    </p>
                @else
                    <p class="text-[11px] text-gray-400 dark:text-gray-500 italic text-center py-1 flex items-center justify-center gap-2">
                        No description provided.
                    </p>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="mt-6 flex justify-between items-center border-t border-dashed border-gray-200 dark:border-white/10 pt-4 opacity-60">
        <span class="text-[10px] font-black uppercase tracking-widest text-gray-400 flex items-center gap-1.5">
            <span class="relative flex h-2 w-2">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-gray-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-gray-500"></span>
            </span>
            Live Preview Auto-Updates
        </span>
        <span class="text-[10px] font-bold tracking-widest text-gray-300 dark:text-gray-600 font-mono">
            ID: VCH-NEW
        </span>
    </div>
</div>
