<x-filament-panels::page>
    @php
        $metrics = $this->summaryMetrics;
        $activeTab = $this->activeTab;

        $currentData = match($activeTab) {
            'trade_licenses'       => $this->tradeLicensesData,
            'establishment_cards'  => $this->establishmentCardsData,
            default                => $this->employeesData,
        };

        $empCount    = $this->employeesData->count();
        $licCount    = $this->tradeLicensesData->count();
        $estCount    = $this->establishmentCardsData->count();
    @endphp

    <div class="space-y-6">
        {{-- ── 1. Top Executive KPI Cards ───────────────────────────────────── --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            {{-- Total Tracked --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Tracked</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        <x-heroicon-m-document-text class="h-4 w-4" />
                    </span>
                </div>
                <p class="mt-2 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $metrics['total'] }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Documents & credentials</p>
            </div>

            {{-- Action Required / Overdue --}}
            <div class="rounded-xl border border-danger-200 bg-danger-50/40 p-4 shadow-sm dark:border-danger-900/40 dark:bg-danger-950/20">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-danger-700 dark:text-danger-400">Action Required</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-danger-100 text-danger-700 dark:bg-danger-900/50 dark:text-danger-300">
                        <x-heroicon-m-exclamation-triangle class="h-4 w-4" />
                    </span>
                </div>
                <p class="mt-2 text-2xl font-bold tracking-tight text-danger-700 dark:text-danger-400">{{ $metrics['action_required'] }}</p>
                <p class="mt-1 text-xs text-danger-600/80 dark:text-danger-400/80">Expired or grace period active</p>
            </div>

            {{-- Expiring Soon --}}
            <div class="rounded-xl border border-amber-200 bg-amber-50/40 p-4 shadow-sm dark:border-amber-900/40 dark:bg-amber-950/20">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400">Expiring Soon</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
                        <x-heroicon-m-clock class="h-4 w-4" />
                    </span>
                </div>
                <p class="mt-2 text-2xl font-bold tracking-tight text-amber-700 dark:text-amber-400">{{ $metrics['expiring_soon'] }}</p>
                <p class="mt-1 text-xs text-amber-600/80 dark:text-amber-400/80">Due within 30 days</p>
            </div>

            {{-- On Track --}}
            <div class="rounded-xl border border-success-200 bg-success-50/40 p-4 shadow-sm dark:border-success-900/40 dark:bg-success-950/20">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-success-700 dark:text-success-400">On Track</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-success-100 text-success-700 dark:bg-success-900/50 dark:text-success-300">
                        <x-heroicon-m-check-circle class="h-4 w-4" />
                    </span>
                </div>
                <p class="mt-2 text-2xl font-bold tracking-tight text-success-700 dark:text-success-400">{{ $metrics['on_track'] }}</p>
                <p class="mt-1 text-xs text-success-600/80 dark:text-success-400/80">Compliant & healthy</p>
            </div>

            {{-- Accrued / Projected Fines --}}
            <div class="col-span-2 sm:col-span-1 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Estimated Fines</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-danger-100 text-danger-700 dark:bg-danger-900/40 dark:text-danger-300">
                        <span class="text-xs font-extrabold">AED</span>
                    </span>
                </div>
                <p class="mt-2 text-2xl font-bold tracking-tight text-danger-600 dark:text-danger-400">
                    AED {{ number_format($metrics['total_fines'], 2) }}
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">PR-50 & EID-20 penalties</p>
            </div>
        </div>

        {{-- ── 2. Tab Navigation & Filter Bar ───────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                {{-- Tabs exactly like example screenshot --}}
                <div class="inline-flex rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
                    <button
                        type="button"
                        wire:click="setTab('employees')"
                        class="flex items-center gap-2 rounded-md px-4 py-2 text-xs font-semibold transition-all duration-150 {{ $activeTab === 'employees' ? 'bg-primary-600 text-white shadow-sm dark:bg-primary-500' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}"
                    >
                        <x-heroicon-m-user class="h-3.5 w-3.5" />
                        Employees
                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $activeTab === 'employees' ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $empCount }}
                        </span>
                    </button>

                    <button
                        type="button"
                        wire:click="setTab('trade_licenses')"
                        class="flex items-center gap-2 rounded-md px-4 py-2 text-xs font-semibold transition-all duration-150 {{ $activeTab === 'trade_licenses' ? 'bg-primary-600 text-white shadow-sm dark:bg-primary-500' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}"
                    >
                        <x-heroicon-m-building-office class="h-3.5 w-3.5" />
                        Trade Licenses
                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $activeTab === 'trade_licenses' ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $licCount }}
                        </span>
                    </button>

                    <button
                        type="button"
                        wire:click="setTab('establishment_cards')"
                        class="flex items-center gap-2 rounded-md px-4 py-2 text-xs font-semibold transition-all duration-150 {{ $activeTab === 'establishment_cards' ? 'bg-primary-600 text-white shadow-sm dark:bg-primary-500' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}"
                    >
                        <x-heroicon-m-identification class="h-3.5 w-3.5" />
                        Establishment Cards
                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $activeTab === 'establishment_cards' ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $estCount }}
                        </span>
                    </button>
                </div>

                {{-- Filters --}}
                <div class="flex flex-wrap items-center gap-2.5">
                    {{-- Search Input --}}
                    <div class="relative w-full sm:w-64">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <x-heroicon-m-magnifying-glass class="h-4 w-4" />
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.250ms="search"
                            placeholder="Search name, doc #..."
                            class="block w-full rounded-lg border-gray-300 py-1.5 pl-9 pr-3 text-xs placeholder:text-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                    </div>

                    {{-- Status Dropdown --}}
                    <select
                        wire:model.live="statusFilter"
                        class="rounded-lg border-gray-300 py-1.5 pl-3 pr-8 text-xs font-medium focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option value="all">All Statuses</option>
                        <option value="action_required">Action Required</option>
                        <option value="expiring_soon">Expiring Soon (≤30d)</option>
                        <option value="on_track">On Track</option>
                    </select>

                    {{-- Branch Dropdown --}}
                    <select
                        wire:model.live="branchFilter"
                        class="rounded-lg border-gray-300 py-1.5 pl-3 pr-8 text-xs font-medium focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option value="all">All Entities / Branches</option>
                        @foreach($this->branches as $b)
                            <option value="{{ $b->name }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- ── 3. Main Data Table (Exact Columns as Screenshot) ─────────────── --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-800/40">
                            <th class="px-4 py-3.5 font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Name</th>
                            <th class="px-4 py-3.5 font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Document Type</th>
                            <th class="px-4 py-3.5 font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Expiry Date</th>
                            <th class="px-4 py-3.5 text-center font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Days Remaining</th>
                            <th class="px-4 py-3.5 text-center font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Grace Period (Days)</th>
                            <th class="px-4 py-3.5 text-center font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Days to Grace Period End</th>
                            <th class="px-4 py-3.5 font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Projected Fine Start</th>
                            <th class="px-4 py-3.5 text-right font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Estimated Fine (AED)</th>
                            <th class="px-4 py-3.5 text-center font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($currentData as $row)
                            @php
                                $daysRem   = $row['days_remaining'];
                                $isOverdue = $daysRem < 0;
                                $status    = $row['status'];
                            @endphp
                            <tr class="transition-colors duration-75 hover:bg-gray-50/60 dark:hover:bg-gray-800/50">
                                {{-- Name & Branch --}}
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                                    <div class="flex flex-col">
                                        <span>{{ $row['name'] }}</span>
                                        <span class="text-[10px] font-normal text-gray-500 dark:text-gray-400">
                                            {{ $row['branch'] }} &bull; {{ $row['doc_number'] }}
                                        </span>
                                    </div>
                                </td>

                                {{-- Document Type --}}
                                <td class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">
                                    {{ $row['document_type'] }}
                                </td>

                                {{-- Expiry Date --}}
                                <td class="px-4 py-3 font-mono font-medium text-gray-800 dark:text-gray-200">
                                    {{ $row['expiry_date'] ? $row['expiry_date']->format('d/m/Y') : '—' }}
                                </td>

                                {{-- Days Remaining --}}
                                <td class="px-4 py-3 text-center">
                                    @if($isOverdue)
                                        <span class="inline-flex items-center rounded-full bg-danger-100 px-2 py-0.5 font-mono text-[11px] font-bold text-danger-700 dark:bg-danger-900/40 dark:text-danger-300">
                                            -{{ abs($daysRem) }}d
                                        </span>
                                    @elseif($daysRem <= 30)
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 font-mono text-[11px] font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                            {{ $daysRem }}d
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 font-mono text-[11px] font-medium text-success-700 dark:bg-success-900/30 dark:text-success-400">
                                            {{ $daysRem }}d
                                        </span>
                                    @endif
                                </td>

                                {{-- Grace Period (Days) --}}
                                <td class="px-4 py-3 text-center font-mono text-gray-600 dark:text-gray-400">
                                    {{ $row['grace_period'] !== null ? $row['grace_period'] : 'N/A' }}
                                </td>

                                {{-- Days to Grace Period End --}}
                                <td class="px-4 py-3 text-center font-mono">
                                    @if($row['days_to_grace_end'] !== null)
                                        @if($row['days_to_grace_end'] < 0)
                                            <span class="text-danger-600 font-bold dark:text-danger-400">Ended</span>
                                        @elseif($isOverdue)
                                            <span class="text-amber-600 font-bold dark:text-amber-400">{{ $row['days_to_grace_end'] }}d left</span>
                                        @else
                                            <span class="text-gray-700 dark:text-gray-300">{{ $row['days_to_grace_end'] }}d</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">N/A</span>
                                    @endif
                                </td>

                                {{-- Projected Fine Start --}}
                                <td class="px-4 py-3 font-mono text-gray-700 dark:text-gray-300">
                                    {{ $row['projected_fine_start'] ? $row['projected_fine_start']->format('d/m/Y') : '—' }}
                                </td>

                                {{-- Estimated Fine (AED) --}}
                                <td class="px-4 py-3 text-right font-mono font-bold">
                                    @if($row['estimated_fine'] > 0)
                                        <span class="text-danger-600 dark:text-danger-400">
                                            AED {{ number_format($row['estimated_fine'], 2) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">0</span>
                                    @endif
                                </td>

                                {{-- Status Badge --}}
                                <td class="px-4 py-3 text-center">
                                    @if($status === 'action_required')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-danger-50 px-2.5 py-1 text-[11px] font-bold text-danger-700 ring-1 ring-inset ring-danger-600/20 dark:bg-danger-900/30 dark:text-danger-300">
                                            <span class="h-1.5 w-1.5 rounded-full bg-danger-600"></span>
                                            Action Required
                                        </span>
                                    @elseif($status === 'expiring_soon')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-300">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                            Expiring Soon
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-success-50 px-2.5 py-1 text-[11px] font-bold text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-900/30 dark:text-success-300">
                                            <span class="h-1.5 w-1.5 rounded-full bg-success-500"></span>
                                            On Track
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-12 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <x-heroicon-o-shield-check class="mb-2 h-10 w-10 text-gray-300 dark:text-gray-600" />
                                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">No records found</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">There are no documents matching your selected filters.</p>
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
