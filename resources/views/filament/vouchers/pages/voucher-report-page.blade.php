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

            {{ $this->form }}
        </x-filament::section>


        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="rounded-full bg-info-50 dark:bg-info-950 p-2.5 ring-1 ring-info-200 dark:ring-info-800">
                        <x-heroicon-o-currency-dollar class="h-5 w-5 text-info-600 dark:text-info-400" />
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider">Petty Cash (Exp)</p>
                        <p class="text-lg font-bold text-gray-950 dark:text-white mt-0.5">
                            AED {{ number_format($data['total_petty_cash'], 2) }}
                        </p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="rounded-full bg-warning-50 dark:bg-warning-950 p-2.5 ring-1 ring-warning-200 dark:ring-warning-800">
                        <x-heroicon-o-credit-card class="h-5 w-5 text-warning-600 dark:text-warning-400" />
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider">Payment (Exp)</p>
                        <p class="text-lg font-bold text-gray-950 dark:text-white mt-0.5">
                            AED {{ number_format($data['total_payment'], 2) }}
                        </p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="rounded-full bg-success-50 dark:bg-success-950 p-2.5 ring-1 ring-success-200 dark:ring-success-800">
                        <x-heroicon-o-arrow-down-tray class="h-5 w-5 text-success-600 dark:text-success-400" />
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider">Receipts (Inc)</p>
                        <p class="text-lg font-bold text-gray-950 dark:text-white mt-0.5">
                            AED {{ number_format($data['total_receipt'], 2) }}
                        </p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="rounded-full bg-primary-50 dark:bg-primary-950 p-2.5 ring-1 ring-primary-200 dark:ring-primary-800">
                        <x-heroicon-o-banknotes class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider">Net Expense</p>
                        <p class="text-lg font-bold text-gray-950 dark:text-white mt-0.5">
                            AED {{ number_format($data['net_expenditure'], 2) }}
                        </p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="rounded-full bg-gray-100 dark:bg-gray-800 p-2.5 ring-1 ring-gray-200 dark:ring-gray-700">
                        <x-heroicon-o-document-check class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider">Vouchers Paid</p>
                        <p class="text-lg font-bold text-gray-950 dark:text-white mt-0.5">
                            {{ $data['total_count'] }}
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
                        By Account Code
                    </div>
                </x-slot>

                @if($data['by_category']->isEmpty())
                    <div class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-gray-500">
                        <x-heroicon-o-inbox class="h-10 w-10 mb-2 opacity-50" />
                        <p class="text-sm">No data for this period.</p>
                    </div>
                @else
                    <div 
                        wire:key="chart-{{ md5(json_encode($data['by_category'])) }}"
                        data-series="{{ json_encode(array_values($data['by_category']->toArray())) }}"
                        data-labels="{{ json_encode(array_keys($data['by_category']->toArray())) }}"
                        x-data="{
                            init() {
                                if (!window.ApexCharts) {
                                    const script = document.createElement('script');
                                    script.src = 'https://cdn.jsdelivr.net/npm/apexcharts';
                                    script.onload = () => this.renderChart();
                                    document.head.appendChild(script);
                                } else {
                                    this.renderChart();
                                }
                            },
                            renderChart() {
                                const isDark = document.documentElement.classList.contains('dark');
                                const textColor = isDark ? '#FFF' : '#111827';
                                const rawData = JSON.parse(this.$el.dataset.series);
                                const options = {
                                    series: [{
                                        name: 'Total (Bar)',
                                        type: 'column',
                                        data: rawData
                                    }, {
                                        name: 'Total (Line)',
                                        type: 'line',
                                        data: rawData
                                    }],
                                    labels: JSON.parse(this.$el.dataset.labels),
                                    chart: {
                                        type: 'line',
                                        height: 380,
                                        animations: { enabled: true, easing: 'easeinout', speed: 800 },
                                        background: 'transparent',
                                        toolbar: { show: false }
                                    },
                                    stroke: {
                                        width: [0, 4],
                                        curve: 'smooth'
                                    },
                                    theme: { mode: isDark ? 'dark' : 'light', palette: 'palette1' },
                                    dataLabels: {
                                        enabled: true,
                                        enabledOnSeries: [1],
                                        formatter: function (val) { return 'AED ' + Number(val).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }); },
                                        background: { enabled: true, foreColor: isDark ? '#fff' : '#000', borderRadius: 2, borderWidth: 0 }
                                    },
                                    xaxis: {
                                        labels: { style: { colors: textColor } }
                                    },
                                    yaxis: [{
                                        labels: {
                                            style: { colors: textColor },
                                            formatter: function(val) { return 'AED ' + Number(val).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }); }
                                        }
                                    }],
                                    legend: { show: true, position: 'bottom', fontSize: '12px', labels: { colors: textColor } },
                                    tooltip: {
                                        theme: isDark ? 'dark' : 'light',
                                        y: { formatter: function(val) { return 'AED ' + Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); } }
                                    }
                                };
                                
                                if (this.chart) {
                                    this.chart.destroy();
                                }
                                this.chart = new ApexCharts(this.$refs.chart, options);
                                this.chart.render();
                            }
                        }"
                    >
                        <div x-ref="chart" class="w-full flex justify-center items-center"></div>
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
                        <div class="flex gap-2">
                            <x-filament::button
                                wire:click="exportExcel"
                                icon="heroicon-o-document-arrow-down"
                                color="success"
                                size="sm">
                                Export Excel
                            </x-filament::button>
                            <x-filament::button
                                wire:click="exportPdf"
                                icon="heroicon-o-document-arrow-down"
                                color="danger"
                                size="sm">
                                Export PDF
                            </x-filament::button>
                            <x-filament::button
                                wire:click="exportDenominationsExcel"
                                icon="heroicon-o-banknotes"
                                color="info"
                                size="sm">
                                Export Denominations
                            </x-filament::button>
                        </div>
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
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Voucher #</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Payee</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Account Code</th>
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
                                            <td class="px-6 py-3">
                                                @if($v->type === 'receipt')
                                                    <span class="inline-flex items-center text-xs font-medium px-2 py-0.5 rounded-full bg-success-50 dark:bg-success-900/30 text-success-700 dark:text-success-300">Receipt</span>
                                                @elseif($v->type === 'payment')
                                                    <span class="inline-flex items-center text-xs font-medium px-2 py-0.5 rounded-full bg-warning-50 dark:bg-warning-900/30 text-warning-700 dark:text-warning-300">Payment</span>
                                                @else
                                                    <span class="inline-flex items-center text-xs font-medium px-2 py-0.5 rounded-full bg-info-50 dark:bg-info-900/30 text-info-700 dark:text-info-300">Petty Cash</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-3 text-gray-700 dark:text-gray-300 font-medium">
                                                {{ $v->payee }}
                                            </td>
                                            <td class="px-6 py-3">
                                                <span class="inline-flex items-center text-xs font-medium px-2 py-0.5 rounded-full bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300">
                                                    {{ $v->items->first()?->account_code ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-3 text-gray-500 dark:text-gray-400 text-xs">
                                                {{ $v->user?->name ?? '—' }}
                                            </td>
                                            <td class="px-6 py-3 text-right font-bold whitespace-nowrap {{ $v->type === 'receipt' ? 'text-success-600 dark:text-success-400' : 'text-gray-950 dark:text-white' }}">
                                                {{ $v->type === 'receipt' ? '-' : '' }} AED {{ number_format($v->amount, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="border-t-2 border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50">
                                        <td colspan="6" class="px-6 py-3 text-sm font-bold text-gray-700 dark:text-gray-200">
                                            Total ({{ $data['total_count'] }} vouchers)
                                        </td>
                                        <td class="px-6 py-3 text-right text-base font-bold text-primary-600 dark:text-primary-400 whitespace-nowrap">
                                            AED {{ number_format($data['net_expenditure'], 2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                            <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-900 border-x border-b rounded-b-xl border-t-0 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-center gap-2">
                                    <label for="perPage" class="text-sm font-medium text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                        Per page
                                    </label>
                                    <select wire:model.live="perPage" id="perPage" class="block w-20 rounded-lg border-gray-300 bg-white px-2 py-1 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-500">
                                        <option value="5">5</option>
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                </div>
                                <div class="w-full sm:w-auto overflow-x-auto filament-report-pagination">
                                    {{ $data['vouchers']->links() }}
                                </div>
                            </div>
                        </div>
                    @endif
                </x-filament::section>
            </div>

        </div>
    </div>
</x-filament-panels::page>
