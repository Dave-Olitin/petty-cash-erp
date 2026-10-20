<x-filament-panels::page>
    @php
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

    <div class="space-y-4">
        {{-- ── 1. Full-Width Navigation Tabs ──────────────────────────────── --}}
        <div class="border-b border-gray-200 dark:border-gray-800">
            <x-filament::tabs>
                <x-filament::tabs.item
                    :active="$activeTab === 'employees'"
                    wire:click="setTab('employees')"
                    :badge="$empCount"
                    icon="heroicon-m-user"
                >
                    Employees
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'trade_licenses'"
                    wire:click="setTab('trade_licenses')"
                    :badge="$licCount"
                    icon="heroicon-m-building-office"
                >
                    Trade Licenses
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'establishment_cards'"
                    wire:click="setTab('establishment_cards')"
                    :badge="$estCount"
                    icon="heroicon-m-identification"
                >
                    Establishment Cards
                </x-filament::tabs.item>
            </x-filament::tabs>
        </div>

        {{-- ── 2. Unified Search & Filters Toolbar (Single Clean Row) ────── --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            {{-- Search Input on the Left --}}
            <div class="w-full sm:w-80">
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search name, doc #, branch..."
                    />
                </x-filament::input.wrapper>
            </div>

            {{-- Dropdown Filters Side-by-Side on the Right --}}
            <div class="flex flex-wrap items-center gap-2">
                <div class="w-auto">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="statusFilter">
                            <option value="all">All Statuses</option>
                            <option value="action_required">Action Required</option>
                            <option value="expiring_soon">Expiring Soon (≤30d)</option>
                            <option value="on_track">On Track</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div class="w-auto">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="branchFilter">
                            <option value="all">All Entities / Branches</option>
                            @foreach($this->branches as $b)
                                <option value="{{ $b->name }}">{{ $b->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                @if($this->search !== '' || $this->statusFilter !== 'all' || $this->branchFilter !== 'all')
                    <x-filament::button
                        color="gray"
                        icon="heroicon-m-x-mark"
                        wire:click="$set('search', ''); $set('statusFilter', 'all'); $set('branchFilter', 'all');"
                    >
                        Reset
                    </x-filament::button>
                @endif
            </div>
        </div>

        {{-- ── 3. Main Data Table (Enclosed in Filament Card Container) ───── --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="w-full text-start text-xs divide-y divide-gray-200 dark:divide-white/5">
                    <thead>
                        <tr class="bg-gray-50/80 dark:bg-white/5">
                            <th class="px-4 py-3.5 text-start font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Name & Identifier
                            </th>
                            <th class="px-4 py-3.5 text-start font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Document Type
                            </th>
                            <th class="px-4 py-3.5 text-start font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Expiry Date
                            </th>
                            <th class="px-4 py-3.5 text-center font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Days Remaining
                            </th>
                            <th class="px-4 py-3.5 text-center font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Grace Period
                            </th>
                            <th class="px-4 py-3.5 text-center font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Days to Grace End
                            </th>
                            <th class="px-4 py-3.5 text-start font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Projected Fine Start
                            </th>
                            <th class="px-4 py-3.5 text-end font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Estimated Fine (AED)
                            </th>
                            <th class="px-4 py-3.5 text-center font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
                        @forelse($currentData as $row)
                            @php
                                $daysRem   = $row['days_remaining'];
                                $isOverdue = $daysRem < 0;
                                $status    = $row['status'];
                            @endphp
                            <tr class="hover:bg-gray-50/60 dark:hover:bg-white/5 transition duration-75">
                                {{-- Name & Branch --}}
                                <td class="px-4 py-3.5">
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-gray-950 dark:text-white">{{ $row['name'] }}</span>
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400">
                                            {{ $row['branch'] }} &bull; {{ $row['doc_number'] }}
                                        </span>
                                    </div>
                                </td>

                                {{-- Document Type --}}
                                <td class="px-4 py-3.5">
                                    <x-filament::badge color="gray">
                                        {{ $row['document_type'] }}
                                    </x-filament::badge>
                                </td>

                                {{-- Expiry Date --}}
                                <td class="px-4 py-3.5 font-mono text-xs text-gray-700 dark:text-gray-300">
                                    {{ $row['expiry_date'] ? $row['expiry_date']->format('d M Y') : '—' }}
                                </td>

                                {{-- Days Remaining --}}
                                <td class="px-4 py-3.5 text-center">
                                    @if($isOverdue)
                                        <x-filament::badge color="danger">
                                            -{{ abs($daysRem) }}d Overdue
                                        </x-filament::badge>
                                    @elseif($daysRem === 0)
                                        <x-filament::badge color="danger">
                                            Expires Today
                                        </x-filament::badge>
                                    @elseif($daysRem <= 30)
                                        <x-filament::badge color="warning">
                                            {{ $daysRem }}d left
                                        </x-filament::badge>
                                    @else
                                        <span class="font-mono text-xs text-gray-700 dark:text-gray-300">
                                            {{ $daysRem }}d
                                        </span>
                                    @endif
                                </td>

                                {{-- Grace Period (Days) --}}
                                <td class="px-4 py-3.5 text-center font-mono text-xs text-gray-600 dark:text-gray-400">
                                    {{ $row['grace_period'] !== null ? $row['grace_period'] . 'd' : '—' }}
                                </td>

                                {{-- Days to Grace Period End --}}
                                <td class="px-4 py-3.5 text-center">
                                    @if($row['days_to_grace_end'] !== null)
                                        @if($row['days_to_grace_end'] < 0)
                                            <x-filament::badge color="danger">Ended</x-filament::badge>
                                        @elseif($isOverdue)
                                            <x-filament::badge color="warning">{{ $row['days_to_grace_end'] }}d left</x-filament::badge>
                                        @else
                                            <span class="font-mono text-xs text-gray-700 dark:text-gray-300">{{ $row['days_to_grace_end'] }}d</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                {{-- Projected Fine Start --}}
                                <td class="px-4 py-3.5 font-mono text-xs text-gray-700 dark:text-gray-300">
                                    {{ $row['projected_fine_start'] ? $row['projected_fine_start']->format('d M Y') : '—' }}
                                </td>

                                {{-- Estimated Fine (AED) --}}
                                <td class="px-4 py-3.5 text-end font-mono">
                                    @if($row['estimated_fine'] > 0)
                                        <x-filament::badge color="danger">
                                            AED {{ number_format($row['estimated_fine'], 2) }}
                                        </x-filament::badge>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">AED 0.00</span>
                                    @endif
                                </td>

                                {{-- Status Badge --}}
                                <td class="px-4 py-3.5 text-center">
                                    @if($status === 'action_required')
                                        <x-filament::badge color="danger" icon="heroicon-m-exclamation-triangle">
                                            Action Required
                                        </x-filament::badge>
                                    @elseif($status === 'expiring_soon')
                                        <x-filament::badge color="warning" icon="heroicon-m-clock">
                                            Expiring Soon
                                        </x-filament::badge>
                                    @else
                                        <x-filament::badge color="success" icon="heroicon-m-check-circle">
                                            On Track
                                        </x-filament::badge>
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
