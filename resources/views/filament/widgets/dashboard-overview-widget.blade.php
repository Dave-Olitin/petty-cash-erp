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

        /* ── Advanced Enhancements CSS ── */
        @keyframes gentle-pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.03); opacity: 0.9; }
        }
        @keyframes vertical-bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(3px); }
        }
        @keyframes vertical-bounce-up {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }
        @keyframes smooth-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .pulse-urgent {
            animation: gentle-pulse 2s infinite ease-in-out;
            box-shadow: 0 0 8px rgba(225, 29, 72, 0.4);
        }

        .icon-animate {
            transition: transform 0.2s ease-in-out;
        }

        /* Hover actions on ledger icons */
        .ledger-card-replenish:hover .icon-animate {
            animation: vertical-bounce 1s infinite ease-in-out;
        }
        .ledger-card-return:hover .icon-animate {
            animation: smooth-spin 1.5s infinite linear;
        }
        .ledger-card-cashout:hover .icon-animate {
            animation: vertical-bounce-up 1s infinite ease-in-out;
        }

        /* Glowing accents for Action Center interactive cards */
        .action-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .action-card-amber:hover {
            box-shadow: 0 8px 24px -4px rgba(217, 119, 6, 0.12), 0 4px 12px -2px rgba(217, 119, 6, 0.08);
            border-color: rgba(217, 119, 6, 0.4) !important;
        }
        .dark .action-card-amber:hover {
            box-shadow: 0 8px 24px -4px rgba(251, 191, 36, 0.2), 0 4px 12px -2px rgba(251, 191, 36, 0.15);
            border-color: rgba(251, 191, 36, 0.4) !important;
        }

        .action-card-emerald:hover {
            box-shadow: 0 8px 24px -4px rgba(16, 185, 129, 0.12), 0 4px 12px -2px rgba(16, 185, 129, 0.08);
            border-color: rgba(16, 185, 129, 0.4) !important;
        }
        .dark .action-card-emerald:hover {
            box-shadow: 0 8px 24px -4px rgba(52, 211, 153, 0.2), 0 4px 12px -2px rgba(52, 211, 153, 0.15);
            border-color: rgba(52, 211, 153, 0.4) !important;
        }

        .action-card-blue:hover {
            box-shadow: 0 8px 24px -4px rgba(37, 99, 235, 0.12), 0 4px 12px -2px rgba(37, 99, 235, 0.08);
            border-color: rgba(37, 99, 235, 0.4) !important;
        }
        .dark .action-card-blue:hover {
            box-shadow: 0 8px 24px -4px rgba(96, 165, 250, 0.2), 0 4px 12px -2px rgba(96, 165, 250, 0.15);
            border-color: rgba(96, 165, 250, 0.4) !important;
        }

        .action-card-rose:hover {
            box-shadow: 0 8px 24px -4px rgba(225, 29, 72, 0.12), 0 4px 12px -2px rgba(225, 29, 72, 0.08);
            border-color: rgba(225, 29, 72, 0.4) !important;
        }
        .dark .action-card-rose:hover {
            box-shadow: 0 8px 24px -4px rgba(251, 113, 133, 0.2), 0 4px 12px -2px rgba(251, 113, 133, 0.15);
            border-color: rgba(251, 113, 133, 0.4) !important;
        }
    </style>
    <!-- Top Row: 4 Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
        
        <!-- Stat 1: Total Cash in Box -->
        <div class="bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl p-6 shadow-sm flex flex-col justify-between">
            <div class="flex flex-col gap-y-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">TOTAL CASH IN BOX</span>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                    AED {{ number_format($grandTotalInBox, 2) }}
                </div>
            </div>
            <div class="mt-3 flex items-center gap-x-1.5 text-xs font-medium {{ $grandTotalInBox < 0 ? 'stat-val-rose' : 'stat-val-green' }}">
                @if($grandTotalInBox < 0)
                    <x-heroicon-m-exclamation-triangle class="h-4 w-4" />
                @else
                    <x-heroicon-m-check-circle class="h-4 w-4" />
                @endif
                <span>Matches physical safe ending balance</span>
            </div>
        </div>

        <!-- Stat 2: Total Spent This Month -->
        <div class="bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl p-6 shadow-sm flex flex-col justify-between">
            <div class="flex flex-col gap-y-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">TOTAL SPENT THIS MONTH</span>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                    AED {{ number_format($paidThisMonth, 2) }}
                </div>
            </div>
            <div class="mt-3 flex flex-col gap-y-1">
                <div class="flex items-center gap-x-1.5 text-xs font-medium {{ $momIsDecrease ? 'stat-val-green' : 'stat-val-rose' }}">
                    @if($momIsDecrease)
                        <x-heroicon-m-arrow-trending-down class="h-4 w-4" />
                        <span>▼ {{ $momDiffFormatted }} vs last month</span>
                    @else
                        <x-heroicon-m-arrow-trending-up class="h-4 w-4" />
                        <span>▲ {{ $momDiffFormatted }} vs last month</span>
                    @endif
                </div>
                <span class="text-xs text-slate-400 dark:text-slate-500">Total approved disbursements this month</span>
            </div>
        </div>

        <!-- Stat 3: Cash Not Yet Returned -->
        <div class="bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl p-6 shadow-sm flex flex-col justify-between">
            <div class="flex flex-col gap-y-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">CASH NOT YET RETURNED</span>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                    AED {{ number_format($outstandingAmount, 2) }}
                </div>
            </div>
            <div class="mt-3 flex items-center gap-x-1.5 text-xs font-medium stat-val-amber">
                <x-heroicon-m-banknotes class="h-4 w-4" />
                <span>Advances still to be accounted for</span>
            </div>
        </div>

        <!-- Stat 4: Average Days to Settle -->
        <div class="bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl p-6 shadow-sm flex flex-col justify-between">
            <div class="flex flex-col gap-y-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">AVERAGE DAYS TO SETTLE</span>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                    {{ $avgDaysFormatted }}
                </div>
            </div>
            <div class="mt-3 flex items-center gap-x-1.5 text-xs font-medium stat-val-indigo">
                <x-heroicon-m-clock class="h-4 w-4" />
                <span>Typical receipt filing duration</span>
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
            
            <div class="flex-1 flex flex-col justify-between gap-4 mt-4">
                <!-- Replenishments -->
                <div class="ledger-card-replenish group bg-emerald-50/20 dark:bg-emerald-500/5 border border-emerald-100/50 dark:border-emerald-500/10 rounded-xl p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-y-3 lg:gap-y-0 transition-all duration-300 hover:border-emerald-500/30 hover:bg-emerald-50/40 dark:hover:bg-emerald-500/10 hover:shadow-sm">
                    <div class="flex items-center gap-x-4">
                        <div class="p-3 rounded-lg bg-emerald-100/80 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 group-hover:scale-105 transition-transform flex-shrink-0">
                            <x-heroicon-o-arrow-down-tray class="h-6 w-6 icon-animate" />
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Replenishments</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Added via Float Replenishment vouchers</p>
                        </div>
                    </div>
                    <div class="ledger-val-green font-bold text-xl lg:text-2xl tracking-tight flex-shrink-0 pl-14 lg:pl-0 lg:text-right whitespace-nowrap">
                        + {{ $totalReplenishingAmount }}
                    </div>
                </div>

                <!-- Receipt Vouchers (Returns) -->
                <div class="ledger-card-return group bg-cyan-50/20 dark:bg-cyan-500/5 border border-cyan-100/50 dark:border-cyan-500/10 rounded-xl p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-y-3 lg:gap-y-0 transition-all duration-300 hover:border-cyan-500/30 hover:bg-cyan-50/40 dark:hover:bg-cyan-500/10 hover:shadow-sm">
                    <div class="flex items-center gap-x-4">
                        <div class="p-3 rounded-lg bg-cyan-100/80 dark:bg-cyan-500/20 text-cyan-600 dark:text-cyan-400 group-hover:scale-105 transition-transform flex-shrink-0">
                            <x-heroicon-o-arrow-path class="h-6 w-6 icon-animate" />
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Receipt Vouchers (Returns)</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Physical cash returned from liquidations</p>
                        </div>
                    </div>
                    <div class="ledger-val-green font-bold text-xl lg:text-2xl tracking-tight flex-shrink-0 pl-14 lg:pl-0 lg:text-right whitespace-nowrap">
                        + {{ $totalReceiptsAmount }}
                    </div>
                </div>

                <!-- Total Cash Out -->
                <div class="ledger-card-cashout group bg-rose-50/20 dark:bg-rose-500/5 border border-rose-100/50 dark:border-rose-500/10 rounded-xl p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-y-3 lg:gap-y-0 transition-all duration-300 hover:border-rose-500/30 hover:bg-rose-50/40 dark:hover:bg-rose-500/10 hover:shadow-sm">
                    <div class="flex items-center gap-x-4">
                        <div class="p-3 rounded-lg bg-rose-100/80 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 group-hover:scale-105 transition-transform flex-shrink-0">
                            <x-heroicon-o-arrow-up-tray class="h-6 w-6 icon-animate" />
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Total Cash Out (Petty Cash)</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Physical cash disbursed from the safe</p>
                        </div>
                    </div>
                    <div class="ledger-val-rose font-bold text-xl lg:text-2xl tracking-tight flex-shrink-0 pl-14 lg:pl-0 lg:text-right whitespace-nowrap">
                        - {{ $totalCashOutAmount }}
                    </div>
                </div>

                <!-- Inflow vs Outflow Balance Bar -->
                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-white/10">
                    <div class="flex justify-between items-center text-xs font-semibold text-slate-500 dark:text-slate-400 mb-2 gap-x-2">
                        <span>Total Inflows <span class="hidden lg:inline">(Replenishments + Returns)</span></span>
                        <span>Total Outflows</span>
                    </div>
                    @php
                        $inflowRaw = (float)($rawReplenishments ?? 0) + (float)($rawReceipts ?? 0);
                        $outflowRaw = (float)($rawCashOut ?? 0);
                        $totalRaw = $inflowRaw + $outflowRaw;
                        $inflowPercent = $totalRaw > 0 ? round(($inflowRaw / $totalRaw) * 100, 1) : 50;
                        $outflowPercent = $totalRaw > 0 ? round(($outflowRaw / $totalRaw) * 100, 1) : 50;
                    @endphp
                    <div class="h-2.5 w-full rounded-full bg-slate-100 dark:bg-white/10 flex overflow-hidden">
                        <div class="bg-emerald-500 transition-all duration-500" style="width: {{ $inflowPercent }}%" title="Inflow: {{ $inflowPercent }}%"></div>
                        <div class="bg-rose-500 transition-all duration-500" style="width: {{ $outflowPercent }}%" title="Outflow: {{ $outflowPercent }}%"></div>
                    </div>
                    <div class="flex justify-between items-center text-[10px] font-semibold text-slate-400 dark:text-slate-500 mt-1.5">
                        <span>{{ $inflowPercent }}% Inflow</span>
                        <span>{{ $outflowPercent }}% Outflow</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Center -->
        <div class="bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl p-6 shadow-sm flex flex-col">
            <h2 class="text-base font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-white/10 pb-4">
                Action Center
            </h2>
            
            <div class="flex-1 flex flex-col justify-between gap-4 mt-4">
                <!-- Waiting for Approval -->
                <a href="{{ \App\Filament\Vouchers\Resources\VoucherResource::getUrl('index') }}?tableFilters[status][values][0]=pending_checker&tableFilters[status][values][1]=pending_approver" 
                   class="action-card action-card-amber group bg-amber-50/10 dark:bg-amber-500/5 border border-amber-100/30 dark:border-amber-500/10 hover:border-amber-500/30 rounded-xl p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-y-3 lg:gap-y-0 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-sm">
                    <div class="flex items-center gap-x-4">
                        <div class="p-3 rounded-lg bg-amber-100/80 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 group-hover:scale-105 transition-transform flex-shrink-0">
                            <x-heroicon-o-clock class="h-6 w-6" />
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Waiting for Approval</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Awaiting checker or approver action</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-x-3 flex-shrink-0 pl-14 lg:pl-0 justify-between lg:justify-end">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold badge-amber whitespace-nowrap">
                            {{ $pendingVouchersCount }} Pending
                        </span>
                        <x-heroicon-m-chevron-right class="h-5 w-5 text-slate-400 dark:text-slate-500 transition-transform duration-300 group-hover:translate-x-1 group-hover:text-amber-500 dark:group-hover:text-amber-400" />
                    </div>
                </a>

                <!-- Approved & Ready -->
                <a href="{{ \App\Filament\Vouchers\Resources\VoucherResource::getUrl('index') }}?tableFilters[status][values][0]=approved" 
                   class="action-card action-card-emerald group bg-emerald-50/10 dark:bg-emerald-500/5 border border-emerald-100/30 dark:border-emerald-500/10 hover:border-emerald-500/30 rounded-xl p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-y-3 lg:gap-y-0 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-sm">
                    <div class="flex items-center gap-x-4">
                        <div class="p-3 rounded-lg bg-emerald-100/80 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 group-hover:scale-105 transition-transform flex-shrink-0">
                            <x-heroicon-o-check-badge class="h-6 w-6" />
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Approved & Ready</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Approved vouchers ready for payment</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-x-3 flex-shrink-0 pl-14 lg:pl-0 justify-between lg:justify-end">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold badge-emerald whitespace-nowrap">
                            {{ $readyToPayCount }} Ready
                        </span>
                        <x-heroicon-m-chevron-right class="h-5 w-5 text-slate-400 dark:text-slate-500 transition-transform duration-300 group-hover:translate-x-1 group-hover:text-emerald-500 dark:group-hover:text-emerald-400" />
                    </div>
                </a>

                <!-- Awaiting Settlement -->
                <a href="{{ \App\Filament\Vouchers\Resources\LiquidationResource::getUrl('index') }}?activeTab=pending" 
                   class="action-card action-card-blue group bg-blue-50/10 dark:bg-blue-500/5 border border-blue-100/30 dark:border-blue-500/10 hover:border-blue-500/30 rounded-xl p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-y-3 lg:gap-y-0 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-sm">
                    <div class="flex items-center gap-x-4">
                        <div class="p-3 rounded-lg bg-blue-100/80 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 group-hover:scale-105 transition-transform flex-shrink-0">
                            <x-heroicon-o-document-magnifying-glass class="h-6 w-6" />
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Awaiting Settlement</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Outstanding advances to be liquidated</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-x-3 flex-shrink-0 pl-14 lg:pl-0 justify-between lg:justify-end">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold badge-blue whitespace-nowrap">
                            {{ $awaitingSettlementCount }} Total
                        </span>
                        <x-heroicon-m-chevron-right class="h-5 w-5 text-slate-400 dark:text-slate-500 transition-transform duration-300 group-hover:translate-x-1 group-hover:text-blue-500 dark:group-hover:text-blue-400" />
                    </div>
                </a>

                <!-- Overdue Follow-Ups -->
                <a href="{{ \App\Filament\Vouchers\Resources\LiquidationResource::getUrl('index') }}?activeTab=overdue" 
                   class="action-card action-card-rose group bg-rose-50/10 dark:bg-rose-500/5 border border-rose-100/30 dark:border-rose-500/10 hover:border-rose-500/30 rounded-xl p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-y-3 lg:gap-y-0 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-sm">
                    <div class="flex items-center gap-x-4">
                        <div class="p-3 rounded-lg bg-rose-100/80 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 group-hover:scale-105 transition-transform flex-shrink-0">
                            <x-heroicon-o-exclamation-triangle class="h-6 w-6" />
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Overdue Follow-Ups</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Liquidations past standard settlement timeline</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-x-3 flex-shrink-0 pl-14 lg:pl-0 justify-between lg:justify-end">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold badge-rose @if($overdueCount > 0) pulse-urgent @endif whitespace-nowrap">
                            {{ $overdueCount }} Overdue
                        </span>
                        <x-heroicon-m-chevron-right class="h-5 w-5 text-slate-400 dark:text-slate-500 transition-transform duration-300 group-hover:translate-x-1 group-hover:text-rose-500 dark:group-hover:text-rose-400" />
                    </div>
                </a>
            </div>
        </div>

    </div>
</x-filament-widgets::widget>
