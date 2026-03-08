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


        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="rounded-full bg-info-50 dark:bg-info-950 p-2.5 ring-1 ring-info-200 dark:ring-info-800">
                        <x-heroicon-o-currency-dollar class="h-5 w-5 text-info-600 dark:text-info-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold tracking-wider">Petty Cash (PCV)</p>
                        <p class="text-xl font-bold text-gray-950 dark:text-white mt-0.5">
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
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold tracking-wider">Payment (PV)</p>
                        <p class="text-xl font-bold text-gray-950 dark:text-white mt-0.5">
                            AED {{ number_format($data['total_payment'], 2) }}
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
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold tracking-wider">Overall Total</p>
                        <p class="text-xl font-bold text-gray-950 dark:text-white mt-0.5">
                            AED {{ number_format($data['total_amount'], 2) }}
                        </p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="rounded-full bg-success-50 dark:bg-success-950 p-2.5 ring-1 ring-success-200 dark:ring-success-800">
                        <x-heroicon-o-document-check class="h-5 w-5 text-success-600 dark:text-success-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold tracking-wider">Vouchers Paid</p>
                        <p class="text-xl font-bold text-gray-950 dark:text-white mt-0.5">
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
                        x-init="
                            $watch('$wire.date_from', () => setTimeout(() => renderChart(), 500));
                            $watch('$wire.date_to', () => setTimeout(() => renderChart(), 500));
                            $watch('$wire.account_code', () => setTimeout(() => renderChart(), 500));
                            $watch('$wire.type', () => setTimeout(() => renderChart(), 500));
                        "
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
                        <x-filament::button
                            wire:click="exportExcel"
                            icon="heroicon-o-document-arrow-down"
                            color="success"
                            size="sm">
                            Export Excel
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
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Voucher #</th>
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
                            </table>
                            <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-900 border-x border-b rounded-b-xl border-t-0 filament-report-pagination">
                                {{ $data['vouchers']->links() }}
                            </div>
                        </div>
                    @endif
                </x-filament::section>
            </div>

        </div>
    </div>
</x-filament-panels::page>
