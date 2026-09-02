<x-filament-panels::page>
    @php
        $data        = $this->getAgingData();
        $rows        = $data['rows'];
        $gt          = $data['grand_totals'];
        $asOf        = $data['as_of_date'];
        $totalRows   = count($rows);

        $bucketKeys    = ['current', 'b1_30', 'b31_60', 'b61_90', 'b90plus'];
        $bucketLabels  = ['Current', '1–30', '31–60', '61–90', '90+ Days'];
    @endphp

    <div x-data="{ expandedSupplier: null }">
        {{-- ── Filters ──────────────────────────────────────────────────── --}}
        <x-filament::section collapsible>
            <x-slot name="heading">Report Filters</x-slot>
            {{ $this->form }}
        </x-filament::section>

        {{-- ── Summary Cards ───────────────────────────────────────────────── --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6" style="margin-top: 2rem; margin-bottom: 2rem;">
            @foreach($bucketKeys as $i => $key)
            @php
                $borderCol = match($key) {
                    'current' => 'border-success-500',
                    'b1_30'   => 'border-info-500',
                    'b31_60'  => 'border-warning-500',
                    'b61_90'  => 'border-danger-500',
                    'b90plus' => 'border-gray-800',
                    default   => 'border-gray-300'
                };
            @endphp
            <div class="rounded-lg border-l-4 {{ $borderCol }} bg-white dark:bg-gray-900 shadow-sm p-3 ring-1 ring-gray-950/5">
                <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">{{ $bucketLabels[$i] }}</p>
                <p class="mt-1 text-sm font-mono font-bold text-gray-800 dark:text-gray-200">
                    {{ number_format($gt[$key], 2) }}
                </p>
            </div>
            @endforeach

            <div class="rounded-lg border-l-4 border-primary-500 bg-primary-50/50 dark:bg-primary-900/10 shadow-sm p-3 ring-1 ring-primary-500/10">
                <p class="text-[9px] font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400">Total AP</p>
                <p class="mt-1 text-sm font-mono font-bold text-primary-700 dark:text-primary-300">
                    {{ number_format($gt['total'], 2) }}
                </p>
            </div>
        </div>

        {{-- ── Main Aging Grid ────────────────────────────────────────────── --}}
        <div class="mt-6 bg-white dark:bg-gray-900 shadow-sm rounded-xl ring-1 ring-gray-950/5 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center bg-gray-50/50 dark:bg-gray-800/30">
                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider">Payables Aging Summary</h3>
                <div class="flex items-center gap-2 text-[10px] font-medium text-gray-500 uppercase tracking-tighter italic">
                    <x-heroicon-m-information-circle class="w-3 h-3" />
                    Click a row to expand detailed invoice breakdown
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left table-fixed min-w-[1100px]">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700 text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">
                            <th class="px-6 py-4 w-1/4">Supplier Entity</th>
                            <th class="px-4 py-4 text-right">Current</th>
                            <th class="px-4 py-4 text-right">1–30</th>
                            <th class="px-4 py-4 text-right bg-yellow-50/20 dark:bg-yellow-900/5">31–60</th>
                            <th class="px-4 py-4 text-right bg-orange-50/20 dark:bg-orange-900/5">61–90</th>
                            <th class="px-4 py-4 text-right bg-red-50/20 dark:bg-red-900/5 text-red-600 dark:text-red-400">90+ Days</th>
                            <th class="px-6 py-4 text-right font-black text-primary-600 dark:text-primary-400 border-l border-gray-100 dark:border-gray-800">Total Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($rows as $index => $row)
                            @php $supplierId = 'sup-' . $index; @endphp
                            {{-- Supplier Summary Row --}}
                            <tr class="hover:bg-primary-50/30 dark:hover:bg-primary-900/5 transition-all cursor-pointer group select-none"
                                @click="expandedSupplier = (expandedSupplier === '{{ $supplierId }}' ? null : '{{ $supplierId }}')">
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-shrink-0">
                                             <span class="inline-flex items-center justify-center p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 group-hover:bg-primary-100 dark:group-hover:bg-primary-900/40 transition-colors"
                                                   :class="expandedSupplier === '{{ $supplierId }}' ? 'bg-primary-100 dark:bg-primary-900/40' : ''">
                                                <x-heroicon-m-chevron-right 
                                                    class="w-4 h-4 text-gray-500 group-hover:text-primary-600 transition-transform duration-300" 
                                                    ::class="expandedSupplier === '{{ $supplierId }}' ? 'rotate-90 text-primary-600' : ''" />
                                             </span>
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-900 dark:text-gray-100 leading-tight">{{ $row['supplier_name'] }}</p>
                                            <p class="text-[10px] text-gray-500 uppercase tracking-tighter mt-0.5">
                                                {{ $row['count'] }} {{ ($this->payment_status === 'paid') ? 'paid' : (($this->payment_status === 'all') ? 'total' : 'outstanding') }} invoice(s)
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-5 text-right font-mono text-xs {{ $row['current'] > 0 ? 'text-gray-700 dark:text-gray-300' : 'text-gray-300 dark:text-gray-700 opacity-30' }}">
                                    {{ $row['current'] > 0 ? number_format($row['current'], 2) : '—' }}
                                </td>
                                <td class="px-4 py-5 text-right font-mono text-xs {{ $row['b1_30'] > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-gray-300 dark:text-gray-700 opacity-30' }}">
                                    {{ $row['b1_30'] > 0 ? number_format($row['b1_30'], 2) : '—' }}
                                </td>
                                <td class="px-4 py-5 text-right font-mono text-xs bg-yellow-50/20 dark:bg-yellow-900/5 {{ $row['b31_60'] > 0 ? 'text-yellow-700 dark:text-yellow-400 font-bold' : 'text-gray-300 dark:text-gray-700 opacity-30' }}">
                                    {{ $row['b31_60'] > 0 ? number_format($row['b31_60'], 2) : '—' }}
                                </td>
                                <td class="px-4 py-5 text-right font-mono text-xs bg-orange-50/20 dark:bg-orange-900/5 {{ $row['b61_90'] > 0 ? 'text-orange-700 dark:text-orange-400 font-bold' : 'text-gray-300 dark:text-gray-700 opacity-30' }}">
                                    {{ $row['b61_90'] > 0 ? number_format($row['b61_90'], 2) : '—' }}
                                </td>
                                <td class="px-4 py-5 text-right font-mono text-xs bg-red-50/20 dark:bg-red-900/5 {{ $row['b90plus'] > 0 ? 'text-red-600 dark:text-red-400 font-black' : 'text-gray-300 dark:text-gray-700 opacity-30' }}">
                                    {{ $row['b90plus'] > 0 ? number_format($row['b90plus'], 2) : '—' }}
                                </td>
                                <td class="px-6 py-5 text-right font-mono text-sm font-black text-primary-600 dark:text-primary-400 border-l border-gray-100 dark:border-gray-800 bg-primary-50/10 transition-colors group-hover:bg-primary-50/30">
                                    {{ number_format($row['total'], 2) }}
                                </td>
                            </tr>

                            {{-- Expanded Detail Drawer --}}
                             <tr x-show="expandedSupplier === '{{ $supplierId }}'" x-collapse x-cloak>
                                <td colspan="7" class="bg-gray-50/80 dark:bg-gray-950/40 px-4 py-6">
                                    <div class="mx-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg overflow-hidden ring-1 ring-black/5">
                                        <div class="px-4 py-2 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-600 flex items-center justify-between">
                                            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Invoice Breakdown: {{ $row['supplier_name'] }}</span>
                                        </div>
                                        <table class="w-full text-xs text-left">
                                            <thead>
                                                <tr class="text-[9px] font-black uppercase text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700 bg-gray-50/30">
                                                    <th class="px-5 py-3">Entry No</th>
                                                    <th class="px-4 py-3">Reference / Bill #</th>
                                                    <th class="px-4 py-3">Bill Date</th>
                                                    <th class="px-4 py-3">Due Date</th>
                                                    <th class="px-4 py-3 text-center">Aging</th>
                                                    <th class="px-4 py-3 text-right">Inv Amount</th>
                                                    <th class="px-4 py-3 text-right">Amount Paid</th>
                                                    <th class="px-5 py-3 text-right text-primary-600">Balance Due</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50 italic">
                                                @foreach($row['entries'] as $entry)
                                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20 transition-colors">
                                                        <td class="px-5 py-3 font-bold text-gray-900 dark:text-gray-100">{{ $entry['entry_no'] }}</td>
                                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $entry['invoice_no'] }}</td>
                                                        <td class="px-4 py-3 text-gray-500">{{ $entry['date'] }}</td>
                                                        <td class="px-4 py-3 text-gray-500">{{ $entry['due_date'] }}</td>
                                                        <td class="px-4 py-3 text-center">
                                                            @if(!empty($entry['is_paid']) || $entry['payment_status'] === 'paid' || $entry['balance_due'] <= 0)
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                                                    Settled
                                                                </span>
                                                            @elseif($entry['days_overdue'] > 0)
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold {{ $entry['days_overdue'] > 60 ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                                                    {{ $entry['days_overdue'] }}d Overdue
                                                                </span>
                                                            @else
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                                    Current
                                                                </span>
                                                            @endif
                                                        </td>
                                                        <td class="px-4 py-3 text-right font-mono">{{ number_format($entry['grand_total'], 2) }}</td>
                                                        <td class="px-4 py-3 text-right font-mono text-gray-400">{{ number_format($entry['amount_paid'], 2) }}</td>
                                                        <td class="px-5 py-3 text-right font-mono font-black text-primary-600 dark:text-primary-400">
                                                            {{ number_format($entry['balance_due'], 2) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot class="bg-primary-50/30 dark:bg-primary-900/10 border-t border-primary-100 dark:border-primary-800">
                                                <tr class="font-black text-xs">
                                                    <td colspan="7" class="px-5 py-3 text-right text-gray-500 uppercase tracking-tighter">Supplier AP Total</td>
                                                    <td class="px-5 py-3 text-right font-mono text-primary-700 dark:text-primary-300">
                                                        {{ number_format($row['total'], 2) }}
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-32 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-400 dark:text-gray-600">
                                        <x-heroicon-o-check-circle class="w-16 h-16 text-green-400/50 mb-4" />
                                        <p class="text-xl font-black text-gray-500 italic">No Outstanding Payables</p>
                                        <p class="text-xs uppercase tracking-widest mt-1">All supplier accounts are fully settled for the selected filters.</p>
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
