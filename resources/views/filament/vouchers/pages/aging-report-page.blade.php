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

        {{-- ── Summary Metrics Strip ─────────────────────────────────────────── --}}
        <div class="mt-4 flex flex-wrap items-center bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm divide-y sm:divide-y-0 sm:divide-x divide-gray-100 dark:divide-gray-800 overflow-hidden">
            @foreach($bucketKeys as $i => $key)
            <div class="flex-1 min-w-[33%] sm:min-w-0 p-3 flex flex-col items-center justify-center">
                <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $bucketLabels[$i] }}</p>
                <p class="mt-1 text-sm font-mono font-bold text-gray-900 dark:text-gray-100">
                    {{ number_format($gt[$key], 2) }}
                </p>
            </div>
            @endforeach

            <div class="flex-1 min-w-[50%] sm:min-w-[150px] p-3 flex flex-col items-center justify-center bg-primary-50/50 dark:bg-primary-900/10">
                <p class="text-[10px] font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400">Total AP</p>
                <p class="mt-1 text-base font-mono font-black text-primary-700 dark:text-primary-300">
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
                                            <p class="text-[10px] text-gray-500 uppercase tracking-tighter mt-0.5">{{ $row['count'] }} outstanding invoice(s)</p>
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
                                                            @if($entry['days_overdue'] > 0)
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
                    @if($totalRows > 0)
                    <tfoot>
                        <tr class="bg-gray-900 text-white font-mono border-t-4 border-primary-600 shadow-[0_-5px_15px_rgba(0,0,0,0.2)]">
                            <td class="px-6 py-6 font-black text-xs uppercase tracking-[0.2em] text-primary-400 italic">Global Aging Totals</td>
                            <td class="px-4 py-6 text-right font-black text-base">{{ number_format($gt['current'], 2) }}</td>
                            <td class="px-4 py-6 text-right font-black text-base text-blue-300">{{ number_format($gt['b1_30'], 2) }}</td>
                            <td class="px-4 py-6 text-right font-black text-base text-yellow-300 bg-white/5">{{ number_format($gt['b31_60'], 2) }}</td>
                            <td class="px-4 py-6 text-right font-black text-base text-orange-300 bg-white/5">{{ number_format($gt['b61_90'], 2) }}</td>
                            <td class="px-4 py-6 text-right font-black text-base text-red-300 bg-white/5 underline decoration-double">{{ number_format($gt['b90plus'], 2) }}</td>
                            <td class="px-6 py-6 text-right font-black text-2xl text-primary-400 border-l border-white/10 bg-black/20">
                                <span class="text-[10px] font-normal opacity-50 mr-1 italic">AED</span>
                                {{ number_format($gt['total'], 2) }}
                            </td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- ── Legend & Metadata ───────────────────────────────────────────── --}}
        @if($totalRows > 0)
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mt-8 px-4 pb-6">
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded shadow-sm bg-yellow-400"></span>
                    <span class="text-[9px] uppercase font-black tracking-widest text-gray-500">Watch List (31-60)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded shadow-sm bg-orange-500"></span>
                    <span class="text-[9px] uppercase font-black tracking-widest text-gray-500">Critical (61-90)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded shadow-sm bg-red-600"></span>
                    <span class="text-[9px] uppercase font-black tracking-widest text-gray-500">High Risk (90+)</span>
                </div>
            </div>
            <div class="text-center md:text-right font-mono italic">
                <p class="text-[9px] text-gray-400">
                    Report Generated: {{ now()->format('d/m/Y H:i:s') }}
                </p>
                <p class="text-[9px] text-gray-300 uppercase tracking-tighter mt-0.5">
                    Ref As-of: {{ $asOf }}
                </p>
            </div>
        </div>
        @endif
    </div>
</x-filament-panels::page>
