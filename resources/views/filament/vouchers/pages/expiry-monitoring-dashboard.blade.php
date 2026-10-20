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
        {{-- ── 1. Tab Navigation & Filter Bar (100% Native Filament Components) ── --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            {{-- Native Filament Tabs --}}
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

            {{-- Native Filament Input Wrappers for Filters --}}
            <div class="flex flex-wrap items-center gap-2">
                <div class="w-full sm:w-64">
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                        <x-filament::input
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search name, doc #..."
                        />
                    </x-filament::input.wrapper>
                </div>

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

        {{-- ── 2. Main Data Table (100% Native Filament Badges & Containers) ── --}}
        <div class="fi-ta-ctn divide-y divide-gray-200 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:divide-white/10 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-ta-content relative divide-y divide-gray-200 overflow-x-auto dark:divide-white/10">
                <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                    <thead class="divide-y divide-gray-200 dark:divide-white/5">
                        <tr class="bg-gray-50 dark:bg-white/5">
                            <th class="fi-ta-header-cell px-4 py-3 sm:first-of-type:ps-6 text-start text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Name & Identifier
                            </th>
                            <th class="fi-ta-header-cell px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Document Type
                            </th>
                            <th class="fi-ta-header-cell px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Expiry Date
                            </th>
                            <th class="fi-ta-header-cell px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Days Remaining
                            </th>
                            <th class="fi-ta-header-cell px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Grace Period
                            </th>
                            <th class="fi-ta-header-cell px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Days to Grace End
                            </th>
                            <th class="fi-ta-header-cell px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Projected Fine Start
                            </th>
                            <th class="fi-ta-header-cell px-4 py-3 text-end text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Estimated Fine (AED)
                            </th>
                            <th class="fi-ta-header-cell px-4 py-3 sm:last-of-type:pe-6 text-center text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
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
                            <tr class="fi-ta-row hover:bg-gray-50/50 dark:hover:bg-white/5 transition duration-75">
                                {{-- Name & Branch --}}
                                <td class="fi-ta-cell px-4 py-3 sm:first-of-type:ps-6">
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-gray-950 dark:text-white">{{ $row['name'] }}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $row['branch'] }} &bull; {{ $row['doc_number'] }}
                                        </span>
                                    </div>
                                </td>

                                {{-- Document Type --}}
                                <td class="fi-ta-cell px-4 py-3">
                                    <x-filament::badge color="gray">
                                        {{ $row['document_type'] }}
                                    </x-filament::badge>
                                </td>

                                {{-- Expiry Date --}}
                                <td class="fi-ta-cell px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">
                                    {{ $row['expiry_date'] ? $row['expiry_date']->format('d M Y') : '—' }}
                                </td>

                                {{-- Days Remaining --}}
                                <td class="fi-ta-cell px-4 py-3 text-center">
                                    @if($isOverdue)
                                        <x-filament::badge color="danger">
                                            -{{ abs($daysRem) }}d
                                        </x-filament::badge>
                                    @elseif($daysRem <= 30)
                                        <x-filament::badge color="warning">
                                            {{ $daysRem }}d
                                        </x-filament::badge>
                                    @else
                                        <x-filament::badge color="success">
                                            {{ $daysRem }}d
                                        </x-filament::badge>
                                    @endif
                                </td>

                                {{-- Grace Period (Days) --}}
                                <td class="fi-ta-cell px-4 py-3 text-center font-mono text-xs text-gray-600 dark:text-gray-400">
                                    {{ $row['grace_period'] !== null ? $row['grace_period'] . 'd' : '—' }}
                                </td>

                                {{-- Days to Grace Period End --}}
                                <td class="fi-ta-cell px-4 py-3 text-center">
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
                                <td class="fi-ta-cell px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">
                                    {{ $row['projected_fine_start'] ? $row['projected_fine_start']->format('d M Y') : '—' }}
                                </td>

                                {{-- Estimated Fine (AED) --}}
                                <td class="fi-ta-cell px-4 py-3 text-end font-mono">
                                    @if($row['estimated_fine'] > 0)
                                        <x-filament::badge color="danger">
                                            AED {{ number_format($row['estimated_fine'], 2) }}
                                        </x-filament::badge>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">AED 0.00</span>
                                    @endif
                                </td>

                                {{-- Status Badge --}}
                                <td class="fi-ta-cell px-4 py-3 sm:last-of-type:pe-6 text-center">
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
