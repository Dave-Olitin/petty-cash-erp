<x-filament-panels::page>
    @php $data = $this->getReportData(); @endphp

    <div class="space-y-6">

        {{-- ── FILTERS ──────────────────────────────────────────────────────────── --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-funnel class="h-5 w-5 text-primary-500" />
                    Filters
                </div>
            </x-slot>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="flex flex-col gap-1">
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3">
                        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">From</span>
                    </label>
                    <input type="date" wire:model.live="date_from"
                        class="fi-input block w-full rounded-lg border-none bg-white/0 py-1.5 pe-3 ps-3 text-sm text-gray-950 outline-none ring-1 ring-inset ring-gray-950/10 dark:text-white dark:ring-white/20 dark:bg-gray-900">
                </div>
                <div class="flex flex-col gap-1">
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3">
                        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">To</span>
                    </label>
                    <input type="date" wire:model.live="date_to"
                        class="fi-input block w-full rounded-lg border-none bg-white/0 py-1.5 pe-3 ps-3 text-sm text-gray-950 outline-none ring-1 ring-inset ring-gray-950/10 dark:text-white dark:ring-white/20 dark:bg-gray-900">
                </div>
                <div class="flex flex-col gap-1">
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3">
                        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">Type</span>
                    </label>
                    <select wire:model.live="type"
                        class="fi-input block w-full rounded-lg border-none bg-white/0 py-1.5 pe-3 ps-3 text-sm text-gray-950 outline-none ring-1 ring-inset ring-gray-950/10 dark:text-white dark:ring-white/20 dark:bg-gray-900">
                        <option value="">All Types</option>
                        <option value="petty_cash">Petty Cash</option>
                        <option value="payment">Payment Voucher</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3">
                        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">Category</span>
                    </label>
                    <select wire:model.live="category_id"
                        class="fi-input block w-full rounded-lg border-none bg-white/0 py-1.5 pe-3 ps-3 text-sm text-gray-950 outline-none ring-1 ring-inset ring-gray-950/10 dark:text-white dark:ring-white/20 dark:bg-gray-900">
                        <option value="">All Categories</option>
                        @foreach(\App\Models\Category::orderBy('name')->get() as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-filament::section>

        {{-- ── SUMMARY CARDS ────────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="rounded-full bg-primary-50 dark:bg-primary-950 p-3 ring-1 ring-primary-200 dark:ring-primary-800">
                        <x-heroicon-o-banknotes class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Disbursed</p>
                        <p class="text-2xl font-bold text-gray-950 dark:text-white">
                            AED {{ number_format($data['total_amount'], 2) }}
                        </p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="rounded-full bg-success-50 dark:bg-success-950 p-3 ring-1 ring-success-200 dark:ring-success-800">
                        <x-heroicon-o-document-check class="h-6 w-6 text-success-600 dark:text-success-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Vouchers Paid</p>
                        <p class="text-2xl font-bold text-gray-950 dark:text-white">
                            {{ $data['total_count'] }}
                        </p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="rounded-full bg-warning-50 dark:bg-warning-950 p-3 ring-1 ring-warning-200 dark:ring-warning-800">
                        <x-heroicon-o-calendar-days class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Period</p>
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ \Carbon\Carbon::parse($date_from)->format('d M Y') }}
                            →
                            {{ \Carbon\Carbon::parse($date_to)->format('d M Y') }}
                        </p>
                    </div>
                </div>
            </x-filament::section>

        </div>

        {{-- ── BOTTOM ROW: Category Breakdown + Voucher Table ──────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

            {{-- Category Breakdown --}}
            <x-filament::section class="lg:col-span-1">
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-tag class="h-4 w-4 text-gray-400" />
                        By Category
                    </div>
                </x-slot>

                @if($data['by_category']->isEmpty())
                    <div class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-gray-500">
                        <x-heroicon-o-inbox class="h-10 w-10 mb-2 opacity-50" />
                        <p class="text-sm">No data for this period.</p>
                    </div>
                @else
                    <div class="space-y-4">
                        @php
                            $colors = ['bg-primary-500', 'bg-success-500', 'bg-warning-500', 'bg-danger-500', 'bg-info-500'];
                            $ci = 0;
                        @endphp
                        @foreach($data['by_category'] as $cat => $total)
                            @php
                                $pct = $data['total_amount'] > 0 ? ($total / $data['total_amount']) * 100 : 0;
                                $color = $colors[$ci % count($colors)];
                                $ci++;
                            @endphp
                            <div>
                                <div class="flex justify-between items-baseline text-sm mb-1">
                                    <span class="font-medium text-gray-700 dark:text-gray-200 truncate max-w-[65%]">
                                        {{ $cat ?? 'Uncategorized' }}
                                    </span>
                                    <span class="font-bold text-gray-950 dark:text-white text-xs">
                                        AED {{ number_format($total, 0) }}
                                    </span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                                    <div class="{{ $color }} h-2 rounded-full transition-all duration-500"
                                        style="width: {{ number_format($pct, 1) }}%">
                                    </div>
                                </div>
                                <p class="text-xs text-gray-400 mt-0.5">{{ number_format($pct, 1) }}%</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            {{-- Voucher Table --}}
            <div class="lg:col-span-3">
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-list-bullet class="h-4 w-4 text-gray-400" />
                            Paid Vouchers
                        </div>
                    </x-slot>
                    <x-slot name="headerEnd">
                        <x-filament::button
                            wire:click="exportCsv"
                            icon="heroicon-o-arrow-down-tray"
                            color="success"
                            size="sm">
                            Export CSV
                        </x-filament::button>
                    </x-slot>

                    @if($data['vouchers']->isEmpty())
                        <div class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-gray-500">
                            <x-heroicon-o-document-magnifying-glass class="h-12 w-12 mb-3 opacity-40" />
                            <p class="text-sm font-medium">No paid vouchers found for this period.</p>
                            <p class="text-xs mt-1">Try adjusting your date range or filters.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto -mx-6 -mb-6">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Voucher #</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Payee</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Category</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Requester</th>
                                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach($data['vouchers'] as $v)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                            <td class="px-6 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap text-xs">
                                                {{ $v->created_at->format('d M Y') }}
                                            </td>
                                            <td class="px-6 py-3">
                                                <span class="font-mono text-xs font-semibold text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">
                                                    {{ $v->voucher_number }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-3 text-gray-700 dark:text-gray-300 font-medium">
                                                {{ $v->payee }}
                                            </td>
                                            <td class="px-6 py-3">
                                                @if($v->category)
                                                    <span class="inline-flex items-center text-xs font-medium px-2 py-0.5 rounded-full bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300">
                                                        {{ $v->category->name }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-3 text-gray-500 dark:text-gray-400 text-xs">
                                                {{ $v->user?->name ?? '—' }}
                                            </td>
                                            <td class="px-6 py-3 text-right font-bold text-gray-950 dark:text-white whitespace-nowrap">
                                                AED {{ number_format($v->amount, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="border-t-2 border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50">
                                        <td colspan="5" class="px-6 py-3 text-sm font-bold text-gray-700 dark:text-gray-200">
                                            Total ({{ $data['total_count'] }} vouchers)
                                        </td>
                                        <td class="px-6 py-3 text-right text-base font-bold text-primary-600 dark:text-primary-400 whitespace-nowrap">
                                            AED {{ number_format($data['total_amount'], 2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </x-filament::section>
            </div>

        </div>
    </div>
</x-filament-panels::page>
