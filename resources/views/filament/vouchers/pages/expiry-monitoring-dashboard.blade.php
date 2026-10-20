<x-filament-panels::page>
    @php
        $metrics = $this->summaryMetrics;
        $activeTab = $this->activeTab;

        $currentData = match($activeTab) {
            'trade_licenses'       => $this->tradeLicensesData,
            'establishment_cards'  => $this->establishmentCardsData,
            default                => $this->employeesData,
        };

        $empCount = $this->employeesData->count();
        $licCount = $this->tradeLicensesData->count();
        $estCount = $this->establishmentCardsData->count();
    @endphp

    {{-- ── Scoped Executive Design System Styles ─────────────────────────── --}}
    <style>
        .exp-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }
        .exp-kpi-card {
            position: relative;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1.25rem 1.25rem 1.125rem 1.25rem;
            box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.04), 0 1px 2px -1px rgba(15, 23, 42, 0.04);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .exp-kpi-card:hover {
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
            transform: translateY(-2px);
            border-color: #cbd5e1;
        }
        .exp-kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }
        .exp-kpi-total::before { background: linear-gradient(90deg, #6366f1, #818cf8); }
        .exp-kpi-action::before { background: linear-gradient(90deg, #e11d48, #f43f5e); }
        .exp-kpi-warning::before { background: linear-gradient(90deg, #d97706, #f59e0b); }
        .exp-kpi-track::before { background: linear-gradient(90deg, #059669, #10b981); }
        .exp-kpi-fines::before { background: linear-gradient(90deg, #b91c1c, #e11d48); }

        /* Status Pills */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            white-space: nowrap;
            border-width: 1px;
            border-style: solid;
        }
        .status-action-required {
            background-color: #fef2f2 !important;
            color: #991b1b !important;
            border-color: #fecaca !important;
        }
        .status-expiring-soon {
            background-color: #fffbeb !important;
            color: #92400e !important;
            border-color: #fde68a !important;
        }
        .status-on-track {
            background-color: #ecfdf5 !important;
            color: #065f46 !important;
            border-color: #a7f3d0 !important;
        }
        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
        }
        .dot-red { background-color: #dc2626; box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.2); }
        .dot-amber { background-color: #d97706; box-shadow: 0 0 0 2px rgba(217, 119, 6, 0.2); }
        .dot-green { background-color: #059669; box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.2); }

        /* Countdown Badges */
        .badge-countdown {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.2rem 0.55rem;
            border-radius: 0.5rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.6875rem;
            font-weight: 700;
            border-width: 1px;
            border-style: solid;
            white-space: nowrap;
        }
        .badge-overdue {
            background-color: #fee2e2 !important;
            color: #b91c1c !important;
            border-color: #fca5a5 !important;
        }
        .badge-warning {
            background-color: #fef3c7 !important;
            color: #92400e !important;
            border-color: #fcd34d !important;
        }
        .badge-ok {
            background-color: #f1f5f9 !important;
            color: #334155 !important;
            border-color: #e2e8f0 !important;
        }
        .badge-ended {
            background-color: #fee2e2 !important;
            color: #dc2626 !important;
            border-color: #fca5a5 !important;
        }

        /* Document Type Badges */
        .doc-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.6rem;
            border-radius: 0.5rem;
            font-size: 0.6875rem;
            font-weight: 600;
            white-space: nowrap;
            border-width: 1px;
            border-style: solid;
        }
        .doc-visa {
            background-color: #eff6ff !important;
            color: #1d4ed8 !important;
            border-color: #bfdbfe !important;
        }
        .doc-eid {
            background-color: #f5f3ff !important;
            color: #6d28d9 !important;
            border-color: #ddd6fe !important;
        }
        .doc-permit {
            background-color: #f0fdfa !important;
            color: #0f766e !important;
            border-color: #99f6e4 !important;
        }
        .doc-passport {
            background-color: #f8fafc !important;
            color: #334155 !important;
            border-color: #cbd5e1 !important;
        }
        .doc-trade {
            background-color: #fffbeb !important;
            color: #b45309 !important;
            border-color: #fde68a !important;
        }
        .doc-est {
            background-color: #f0fdf4 !important;
            color: #15803d !important;
            border-color: #bbf7d0 !important;
        }

        /* Employee Avatar Initials */
        .avatar-circle {
            width: 34px;
            height: 34px;
            border-radius: 0.625rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6875rem;
            font-weight: 800;
            flex-shrink: 0;
            letter-spacing: -0.01em;
            border: 1px solid transparent;
        }
        .avatar-red {
            background-color: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }
        .avatar-amber {
            background-color: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }
        .avatar-blue {
            background-color: #eff6ff;
            color: #1e40af;
            border-color: #dbeafe;
        }

        /* Dark mode overrides */
        .dark .exp-kpi-card {
            background: #111827;
            border-color: #1f2937;
        }
        .dark .exp-kpi-card:hover {
            border-color: #374151;
        }
        .dark .status-action-required {
            background-color: rgba(153, 27, 27, 0.25) !important;
            color: #fca5a5 !important;
            border-color: rgba(239, 68, 68, 0.35) !important;
        }
        .dark .status-expiring-soon {
            background-color: rgba(146, 64, 14, 0.25) !important;
            color: #fcd34d !important;
            border-color: rgba(245, 158, 11, 0.35) !important;
        }
        .dark .status-on-track {
            background-color: rgba(6, 95, 70, 0.25) !important;
            color: #6ee7b7 !important;
            border-color: rgba(16, 185, 129, 0.35) !important;
        }
        .dark .badge-overdue {
            background-color: rgba(153, 27, 27, 0.3) !important;
            color: #fca5a5 !important;
            border-color: rgba(239, 68, 68, 0.4) !important;
        }
        .dark .badge-warning {
            background-color: rgba(146, 64, 14, 0.3) !important;
            color: #fcd34d !important;
            border-color: rgba(245, 158, 11, 0.4) !important;
        }
        .dark .badge-ok {
            background-color: rgba(30, 41, 59, 0.6) !important;
            color: #cbd5e1 !important;
            border-color: #334155 !important;
        }
        .dark .badge-ended {
            background-color: rgba(153, 27, 27, 0.3) !important;
            color: #f87171 !important;
            border-color: rgba(239, 68, 68, 0.4) !important;
        }
        .dark .doc-visa {
            background-color: rgba(29, 78, 216, 0.2) !important;
            color: #93c5fd !important;
            border-color: rgba(59, 130, 246, 0.3) !important;
        }
        .dark .doc-eid {
            background-color: rgba(109, 40, 217, 0.2) !important;
            color: #c4b5fd !important;
            border-color: rgba(139, 92, 246, 0.3) !important;
        }
        .dark .doc-permit {
            background-color: rgba(15, 118, 110, 0.2) !important;
            color: #5eead4 !important;
            border-color: rgba(20, 184, 166, 0.3) !important;
        }
        .dark .doc-passport {
            background-color: rgba(51, 65, 85, 0.3) !important;
            color: #cbd5e1 !important;
            border-color: rgba(100, 116, 139, 0.4) !important;
        }
        .dark .doc-trade {
            background-color: rgba(180, 83, 9, 0.2) !important;
            color: #fde68a !important;
            border-color: rgba(217, 119, 6, 0.3) !important;
        }
        .dark .doc-est {
            background-color: rgba(21, 128, 61, 0.2) !important;
            color: #86efac !important;
            border-color: rgba(34, 197, 94, 0.3) !important;
        }
        .dark .avatar-red {
            background-color: rgba(153, 27, 27, 0.35);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.3);
        }
        .dark .avatar-amber {
            background-color: rgba(146, 64, 14, 0.35);
            color: #fcd34d;
            border-color: rgba(245, 158, 11, 0.3);
        }
        .dark .avatar-blue {
            background-color: rgba(30, 58, 138, 0.35);
            color: #93c5fd;
            border-color: rgba(59, 130, 246, 0.3);
        }
    </style>

    <div class="space-y-6">
        {{-- ── 1. Top Executive KPI Cards (Flawlessly Balanced Auto-Fit Grid) ── --}}
        <div class="exp-kpi-grid">
            {{-- Card 1: Total Tracked --}}
            <div class="exp-kpi-card exp-kpi-total">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-gray-400">Total Tracked</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                        <x-heroicon-m-document-text class="h-4 w-4" />
                    </span>
                </div>
                <div class="mt-3">
                    <p class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ $metrics['total'] }}</p>
                    <p class="mt-1 text-xs font-medium text-slate-500 dark:text-gray-400">Documents & credentials</p>
                </div>
            </div>

            {{-- Card 2: Action Required --}}
            <div class="exp-kpi-card exp-kpi-action">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400">Action Required</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-100/70 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400">
                        <x-heroicon-m-exclamation-triangle class="h-4 w-4" />
                    </span>
                </div>
                <div class="mt-3">
                    <p class="text-3xl font-extrabold tracking-tight text-rose-600 dark:text-rose-400">{{ $metrics['action_required'] }}</p>
                    <div class="mt-1 flex items-center gap-1.5 text-xs font-medium text-rose-700/90 dark:text-rose-400/90">
                        <span class="status-dot dot-red"></span>
                        <span>Expired or grace active</span>
                    </div>
                </div>
            </div>

            {{-- Card 3: Expiring Soon --}}
            <div class="exp-kpi-card exp-kpi-warning">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Expiring Soon</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-100/70 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400">
                        <x-heroicon-m-clock class="h-4 w-4" />
                    </span>
                </div>
                <div class="mt-3">
                    <p class="text-3xl font-extrabold tracking-tight text-amber-600 dark:text-amber-400">{{ $metrics['expiring_soon'] }}</p>
                    <div class="mt-1 flex items-center gap-1.5 text-xs font-medium text-amber-700/90 dark:text-amber-400/90">
                        <span class="status-dot dot-amber"></span>
                        <span>Due within 30 days</span>
                    </div>
                </div>
            </div>

            {{-- Card 4: On Track --}}
            <div class="exp-kpi-card exp-kpi-track">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">On Track</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-100/70 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                        <x-heroicon-m-check-circle class="h-4 w-4" />
                    </span>
                </div>
                <div class="mt-3">
                    <p class="text-3xl font-extrabold tracking-tight text-emerald-600 dark:text-emerald-400">{{ $metrics['on_track'] }}</p>
                    <div class="mt-1 flex items-center gap-1.5 text-xs font-medium text-emerald-700/90 dark:text-emerald-400/90">
                        <span class="status-dot dot-green"></span>
                        <span>Compliant & healthy</span>
                    </div>
                </div>
            </div>

            {{-- Card 5: Estimated Overstay Fines --}}
            <div class="exp-kpi-card exp-kpi-fines">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-gray-400">Estimated Fines</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-red-100/80 text-red-700 font-extrabold text-[10px] tracking-wider dark:bg-red-950/60 dark:text-red-400">
                        AED
                    </span>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-black tracking-tight {{ $metrics['total_fines'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                        AED {{ number_format($metrics['total_fines'], 2) }}
                    </p>
                    <p class="mt-1 text-xs font-medium text-slate-500 dark:text-gray-400">PR-50 & EID-20 penalties</p>
                </div>
            </div>
        </div>

        {{-- ── 2. Segmented Navigation & Executive Control Strip ─────────────── --}}
        <div class="rounded-2xl border border-slate-200/90 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                {{-- Left: Modern Segmented Tab Buttons --}}
                <div class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-gray-800">
                    {{-- Employees Tab --}}
                    <button
                        type="button"
                        wire:click="setTab('employees')"
                        class="flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-semibold transition-all duration-150 {{ $activeTab === 'employees' ? 'bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-900' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700/60' }}"
                    >
                        <x-heroicon-m-user class="h-3.5 w-3.5" />
                        <span>Employees</span>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $activeTab === 'employees' ? 'bg-white/20 text-white dark:bg-slate-900/15 dark:text-slate-900' : 'bg-slate-200/80 text-slate-700 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $empCount }}
                        </span>
                    </button>

                    {{-- Trade Licenses Tab --}}
                    <button
                        type="button"
                        wire:click="setTab('trade_licenses')"
                        class="flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-semibold transition-all duration-150 {{ $activeTab === 'trade_licenses' ? 'bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-900' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700/60' }}"
                    >
                        <x-heroicon-m-building-office class="h-3.5 w-3.5" />
                        <span>Trade Licenses</span>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $activeTab === 'trade_licenses' ? 'bg-white/20 text-white dark:bg-slate-900/15 dark:text-slate-900' : 'bg-slate-200/80 text-slate-700 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $licCount }}
                        </span>
                    </button>

                    {{-- Establishment Cards Tab --}}
                    <button
                        type="button"
                        wire:click="setTab('establishment_cards')"
                        class="flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-semibold transition-all duration-150 {{ $activeTab === 'establishment_cards' ? 'bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-900' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700/60' }}"
                    >
                        <x-heroicon-m-identification class="h-3.5 w-3.5" />
                        <span>Establishment Cards</span>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $activeTab === 'establishment_cards' ? 'bg-white/20 text-white dark:bg-slate-900/15 dark:text-slate-900' : 'bg-slate-200/80 text-slate-700 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $estCount }}
                        </span>
                    </button>
                </div>

                {{-- Right: Filters Toolbar --}}
                <div class="flex flex-wrap items-center gap-2.5">
                    {{-- Search Input --}}
                    <div class="relative w-full sm:w-64">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <x-heroicon-m-magnifying-glass class="h-4 w-4" />
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.250ms="search"
                            placeholder="Search name, doc #, branch..."
                            class="block w-full rounded-xl border border-slate-200 bg-slate-50/60 py-2 pl-9 pr-8 text-xs font-medium text-slate-800 placeholder:text-slate-400 focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500"
                        />
                        @if($this->search)
                            <button
                                type="button"
                                wire:click="$set('search', '')"
                                class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-gray-200"
                            >
                                <x-heroicon-m-x-circle class="h-4 w-4" />
                            </button>
                        @endif
                    </div>

                    {{-- Status Dropdown --}}
                    <select
                        wire:model.live="statusFilter"
                        class="rounded-xl border border-slate-200 bg-slate-50/60 py-2 pl-3 pr-8 text-xs font-semibold text-slate-700 focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                    >
                        <option value="all">All Statuses</option>
                        <option value="action_required">Action Required</option>
                        <option value="expiring_soon">Expiring Soon (≤30d)</option>
                        <option value="on_track">On Track</option>
                    </select>

                    {{-- Branch Dropdown --}}
                    <select
                        wire:model.live="branchFilter"
                        class="rounded-xl border border-slate-200 bg-slate-50/60 py-2 pl-3 pr-8 text-xs font-semibold text-slate-700 focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                    >
                        <option value="all">All Entities / Branches</option>
                        @foreach($this->branches as $b)
                            <option value="{{ $b->name }}">{{ $b->name }}</option>
                        @endforeach
                    </select>

                    {{-- Quick Reset Filters (shown only when any filter active) --}}
                    @if($this->search !== '' || $this->statusFilter !== 'all' || $this->branchFilter !== 'all')
                        <button
                            type="button"
                            wire:click="$set('search', ''); $set('statusFilter', 'all'); $set('branchFilter', 'all');"
                            class="inline-flex items-center gap-1 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100 transition-colors dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300"
                            title="Reset all active filters"
                        >
                            <x-heroicon-m-x-mark class="h-3.5 w-3.5" />
                            <span>Reset</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── 3. Main Data Table (Executive SaaS Grade) ────────────────────── --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            {{-- Table Sub-header with Result Count and Details --}}
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3 dark:border-gray-800 bg-slate-50/50 dark:bg-gray-900/50">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-slate-500 dark:text-gray-400">
                        Displaying <span class="font-bold text-slate-900 dark:text-white">{{ $currentData->count() }}</span> monitored records
                    </span>
                </div>
                <div class="text-[11px] font-medium text-slate-400 dark:text-gray-500 hidden sm:block">
                    @if($activeTab === 'employees')
                        Residence Visas &bull; Emirates IDs &bull; MOHRE Work Permits &bull; Passports
                    @elseif($activeTab === 'trade_licenses')
                        Commercial & Professional Trade Licenses
                    @else
                        MOHRE & Immigration Establishment Cards
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/90 dark:border-gray-800 dark:bg-gray-800/60">
                            <th class="px-5 py-3.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-gray-400">Name & Identifier</th>
                            <th class="px-4 py-3.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-gray-400">Document Type</th>
                            <th class="px-4 py-3.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-gray-400">Expiry Date</th>
                            <th class="px-4 py-3.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-gray-400">Days Remaining</th>
                            <th class="px-4 py-3.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-gray-400">Grace Period</th>
                            <th class="px-4 py-3.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-gray-400">Days to Grace End</th>
                            <th class="px-4 py-3.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-gray-400">Projected Fine Start</th>
                            <th class="px-5 py-3.5 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-gray-400">Estimated Fine (AED)</th>
                            <th class="px-4 py-3.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-gray-400">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-gray-800">
                        @forelse($currentData as $row)
                            @php
                                $daysRem   = $row['days_remaining'];
                                $isOverdue = $daysRem < 0;
                                $status    = $row['status'];

                                // Generate initials for avatar circle
                                $words = preg_split('/\s+/', trim($row['name']));
                                $initials = count($words) >= 2 
                                    ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1))
                                    : strtoupper(substr($words[0] ?? 'PR', 0, 2));

                                $avatarClass = match($status) {
                                    'action_required' => 'avatar-red',
                                    'expiring_soon'   => 'avatar-amber',
                                    default           => 'avatar-blue',
                                };

                                // Document Badge styling
                                $docClass = match(true) {
                                    str_contains($row['document_type'], 'Residence Visa') => 'doc-visa',
                                    str_contains($row['document_type'], 'Emirates ID')   => 'doc-eid',
                                    str_contains($row['document_type'], 'Work Permit') || str_contains($row['document_type'], 'Labour') => 'doc-permit',
                                    str_contains($row['document_type'], 'Passport')      => 'doc-passport',
                                    str_contains($row['document_type'], 'Trade License') => 'doc-trade',
                                    str_contains($row['document_type'], 'Establishment') => 'doc-est',
                                    default => 'doc-passport',
                                };
                            @endphp
                            <tr class="transition-colors duration-100 hover:bg-slate-50/80 dark:hover:bg-gray-800/40">
                                {{-- 1. Name, Branch & Document Number --}}
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="avatar-circle {{ $avatarClass }}">
                                            {{ $initials }}
                                        </div>
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-xs font-bold text-slate-900 dark:text-white truncate">
                                                {{ $row['name'] }}
                                            </span>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold bg-slate-100 text-slate-700 dark:bg-gray-800 dark:text-gray-300">
                                                    {{ $row['branch'] }}
                                                </span>
                                                <span class="text-slate-300 dark:text-gray-600">&bull;</span>
                                                <span class="font-mono text-[10px] text-slate-500 dark:text-gray-400">
                                                    {{ $row['doc_number'] }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                {{-- 2. Document Type --}}
                                <td class="px-4 py-3.5">
                                    <span class="doc-pill {{ $docClass }}">
                                        @if(str_contains($row['document_type'], 'Residence Visa'))
                                            <x-heroicon-m-identification class="h-3 w-3" />
                                        @elseif(str_contains($row['document_type'], 'Emirates ID'))
                                            <x-heroicon-m-credit-card class="h-3 w-3" />
                                        @elseif(str_contains($row['document_type'], 'Passport'))
                                            <x-heroicon-m-globe-alt class="h-3 w-3" />
                                        @elseif(str_contains($row['document_type'], 'Trade License'))
                                            <x-heroicon-m-building-office class="h-3 w-3" />
                                        @else
                                            <x-heroicon-m-document-text class="h-3 w-3" />
                                        @endif
                                        <span>{{ $row['document_type'] }}</span>
                                    </span>
                                </td>

                                {{-- 3. Expiry Date --}}
                                <td class="px-4 py-3.5 font-mono text-xs font-semibold text-slate-700 dark:text-gray-200">
                                    {{ $row['expiry_date'] ? $row['expiry_date']->format('d M Y') : '—' }}
                                </td>

                                {{-- 4. Days Remaining --}}
                                <td class="px-4 py-3.5 text-center">
                                    @if($isOverdue)
                                        <span class="badge-countdown badge-overdue">
                                            -{{ abs($daysRem) }}d Overdue
                                        </span>
                                    @elseif($daysRem === 0)
                                        <span class="badge-countdown badge-overdue">
                                            Expires Today
                                        </span>
                                    @elseif($daysRem <= 30)
                                        <span class="badge-countdown badge-warning">
                                            {{ $daysRem }}d left
                                        </span>
                                    @else
                                        <span class="badge-countdown badge-ok">
                                            {{ $daysRem }}d
                                        </span>
                                    @endif
                                </td>

                                {{-- 5. Grace Period (Days) --}}
                                <td class="px-4 py-3.5 text-center">
                                    @if($row['grace_period'] !== null)
                                        <span class="font-mono text-xs font-semibold text-slate-600 dark:text-gray-300">
                                            {{ $row['grace_period'] }}d
                                        </span>
                                    @else
                                        <span class="font-mono text-slate-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>

                                {{-- 6. Days to Grace Period End --}}
                                <td class="px-4 py-3.5 text-center">
                                    @if($row['days_to_grace_end'] !== null)
                                        @if($row['days_to_grace_end'] < 0)
                                            <span class="badge-countdown badge-ended">Ended</span>
                                        @elseif($isOverdue)
                                            <span class="badge-countdown badge-warning">{{ $row['days_to_grace_end'] }}d left</span>
                                        @else
                                            <span class="font-mono text-xs font-semibold text-slate-700 dark:text-gray-300">{{ $row['days_to_grace_end'] }}d</span>
                                        @endif
                                    @else
                                        <span class="font-mono text-slate-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>

                                {{-- 7. Projected Fine Start --}}
                                <td class="px-4 py-3.5 font-mono text-xs text-slate-600 dark:text-gray-300">
                                    {{ $row['projected_fine_start'] ? $row['projected_fine_start']->format('d M Y') : '—' }}
                                </td>

                                {{-- 8. Estimated Fine (AED) --}}
                                <td class="px-5 py-3.5 text-right font-mono font-bold">
                                    @if($row['estimated_fine'] > 0)
                                        <span class="inline-flex items-center rounded-lg border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-extrabold text-red-600 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-400">
                                            AED {{ number_format($row['estimated_fine'], 2) }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400 dark:text-gray-500 font-medium">AED 0.00</span>
                                    @endif
                                </td>

                                {{-- 9. Status Pill Badge --}}
                                <td class="px-4 py-3.5 text-center">
                                    @if($status === 'action_required')
                                        <span class="status-pill status-action-required">
                                            <span class="status-dot dot-red"></span>
                                            <span>Action Required</span>
                                        </span>
                                    @elseif($status === 'expiring_soon')
                                        <span class="status-pill status-expiring-soon">
                                            <span class="status-dot dot-amber"></span>
                                            <span>Expiring Soon</span>
                                        </span>
                                    @else
                                        <span class="status-pill status-on-track">
                                            <span class="status-dot dot-green"></span>
                                            <span>On Track</span>
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-16 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center justify-center">
                                        <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-gray-800 dark:text-gray-500">
                                            <x-heroicon-o-magnifying-glass class="h-6 w-6" />
                                        </div>
                                        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">No matching credentials found</h4>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            There are no document records matching your active filters. Try adjusting your search query or selecting a different status or entity.
                                        </p>
                                        @if($this->search !== '' || $this->statusFilter !== 'all' || $this->branchFilter !== 'all')
                                            <button
                                                type="button"
                                                wire:click="$set('search', ''); $set('statusFilter', 'all'); $set('branchFilter', 'all');"
                                                class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-white dark:text-slate-900 transition-colors"
                                            >
                                                <x-heroicon-m-arrow-path class="h-3.5 w-3.5" />
                                                <span>Reset All Filters</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
