<x-filament-widgets::widget>
    <style>
        .stat-val-green {
            color: #10b981 !important;
        }
        .dark .stat-val-green {
            color: #34d399 !important;
        }
        .stat-val-rose {
            color: #e11d48 !important;
        }
        .dark .stat-val-rose {
            color: #fb7185 !important;
        }
        .stat-val-blue {
            color: #2563eb !important;
        }
        .dark .stat-val-blue {
            color: #60a5fa !important;
        }
        .stat-val-amber {
            color: #d97706 !important;
        }
        .dark .stat-val-amber {
            color: #fbbf24 !important;
        }
        .stat-val-indigo {
            color: #4f46e5 !important;
        }
        .dark .stat-val-indigo {
            color: #818cf8 !important;
        }
        .ledger-val-green {
            color: #10b981 !important;
        }
        .dark .ledger-val-green {
            color: #34d399 !important;
        }
        .ledger-val-rose {
            color: #e11d48 !important;
        }
        .dark .ledger-val-rose {
            color: #fb7185 !important;
        }
        .badge-amber {
            background-color: #fef3c7 !important;
            color: #92400e !important;
        }
        .dark .badge-amber {
            background-color: rgba(146, 64, 14, 0.4) !important;
            color: #fcd34d !important;
        }
        .badge-emerald {
            background-color: #d1fae5 !important;
            color: #065f46 !important;
        }
        .dark .badge-emerald {
            background-color: rgba(6, 95, 70, 0.4) !important;
            color: #6ee7b7 !important;
        }
        .badge-blue {
            background-color: #dbeafe !important;
            color: #1e40af !important;
        }
        .dark .badge-blue {
            background-color: rgba(30, 64, 175, 0.4) !important;
            color: #93c5fd !important;
        }
        .badge-rose {
            background-color: #ffe4e6 !important;
            color: #9f1239 !important;
        }
        .dark .badge-rose {
            background-color: rgba(159, 18, 57, 0.4) !important;
            color: #fda4af !important;
        }
    </style>
    <!-- Top Row: 4 Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
        
        <!-- Stat 1: Total Cash in Box -->
        <div class="bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl p-6 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Total Cash in Box</span>
                <x-heroicon-o-credit-card class="h-5 w-5 text-slate-400 dark:text-slate-500" />
            </div>
            <div class="mt-4">
                <span class="text-3xl font-medium tracking-tight {{ $grandTotalInBox < 0 ? 'stat-val-rose' : 'stat-val-green' }}">
                    AED {{ number_format($grandTotalInBox, 2) }}
                </span>
            </div>
            <div class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                Matches physical safe ending balance
            </div>
        </div>

        <!-- Stat 2: Total Spent This Month -->
        <div class="bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl p-6 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Total Spent This Month</span>
                <x-heroicon-o-currency-dollar class="h-5 w-5 text-slate-400 dark:text-slate-500" />
            </div>
            <div class="mt-4">
                <span class="text-3xl font-medium tracking-tight stat-val-blue">
                    AED {{ number_format($paidThisMonth, 2) }}
                </span>
                <div class="flex items-center gap-1 mt-1 text-xs {{ $momIsDecrease ? 'text-emerald-600 dark:text-emerald-500' : 'text-rose-600 dark:text-rose-500' }}">
                    @if($momIsDecrease)
                        <span>▼ {{ $momDiffFormatted }} vs last month</span>
                    @else
                        <span>▲ {{ $momDiffFormatted }} vs last month</span>
                    @endif
                </div>
            </div>
            <div class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                Total approved disbursements this month
            </div>
        </div>

        <!-- Stat 3: Cash Not Yet Returned -->
        <div class="bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl p-6 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Cash Not Yet Returned</span>
                <x-heroicon-o-clock class="h-5 w-5 text-slate-400 dark:text-slate-500" />
            </div>
            <div class="mt-4">
                <span class="text-3xl font-medium tracking-tight stat-val-amber">
                    AED {{ number_format($outstandingAmount, 2) }}
                </span>
            </div>
            <div class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                Advances still to be accounted for
            </div>
        </div>

        <!-- Stat 4: Average Days to Settle -->
        <div class="bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl p-6 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Average Days to Settle</span>
                <x-heroicon-o-calendar class="h-5 w-5 text-slate-400 dark:text-slate-500" />
            </div>
            <div class="mt-4">
                <span class="text-3xl font-medium tracking-tight stat-val-indigo">
                    {{ $avgDaysFormatted }}
                </span>
            </div>
            <div class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                Typical receipt filing duration
            </div>
        </div>

    </div>

    <!-- Bottom Row: Cash Flow Ledger and Action Center -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
        
        <!-- Cash Flow Ledger -->
        <div class="bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl p-6 shadow-sm flex flex-col">
            <h2 class="text-base font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-white/10 pb-4">
                Cash Flow Ledger
            </h2>
            
            <div class="divide-y divide-gray-100 dark:divide-white/10 flex-1 flex flex-col justify-around">
                <!-- Replenishments -->
                <div class="py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Replenishments</h3>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Added via Float Replenishment vouchers</p>
                    </div>
                    <div class="ledger-val-green font-bold text-sm sm:text-base">
                        + {{ $totalReplenishingAmount }}
                    </div>
                </div>

                <!-- Receipt Vouchers -->
                <div class="py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Receipt Vouchers (Returns)</h3>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Physical cash returned from liquidations</p>
                    </div>
                    <div class="ledger-val-green font-bold text-sm sm:text-base">
                        + {{ $totalReceiptsAmount }}
                    </div>
                </div>

                <!-- Total Cash Out -->
                <div class="py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Total Cash Out (Petty Cash)</h3>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Physical cash disbursed from the safe</p>
                    </div>
                    <div class="ledger-val-rose font-bold text-sm sm:text-base">
                        - {{ $totalCashOutAmount }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Center (takes 1 column) -->
        <div class="bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl p-6 shadow-sm flex flex-col">
            <h2 class="text-base font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-white/10 pb-4">
                Action Center
            </h2>
            
            <div class="divide-y divide-gray-100 dark:divide-white/10 flex-1 flex flex-col justify-around">
                <!-- Waiting for Approval -->
                <a href="{{ \App\Filament\Vouchers\Resources\VoucherResource::getUrl('index') }}?tableFilters[status][values][0]=pending_checker&tableFilters[status][values][1]=pending_approver" 
                   class="py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-white/5 transition px-2 rounded-lg -mx-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Waiting for Approval</span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold badge-amber">
                        {{ $pendingVouchersCount }} Pending
                    </span>
                </a>

                <!-- Approved & Ready -->
                <a href="{{ \App\Filament\Vouchers\Resources\VoucherResource::getUrl('index') }}?tableFilters[status][values][0]=approved" 
                   class="py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-white/5 transition px-2 rounded-lg -mx-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Approved & Ready</span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold badge-emerald">
                        {{ $readyToPayCount }} Ready
                    </span>
                </a>

                <!-- Awaiting Settlement -->
                <a href="{{ \App\Filament\Vouchers\Resources\LiquidationResource::getUrl('index') }}?activeTab=pending" 
                   class="py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-white/5 transition px-2 rounded-lg -mx-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Awaiting Settlement</span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold badge-blue">
                        {{ $awaitingSettlementCount }} Total
                    </span>
                </a>

                <!-- Overdue Follow-Ups -->
                <a href="{{ \App\Filament\Vouchers\Resources\LiquidationResource::getUrl('index') }}?activeTab=overdue" 
                   class="py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-white/5 transition px-2 rounded-lg -mx-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Overdue Follow-Ups</span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold badge-rose">
                        {{ $overdueCount }} Overdue
                    </span>
                </a>
            </div>
        </div>

    </div>
</x-filament-widgets::widget>
