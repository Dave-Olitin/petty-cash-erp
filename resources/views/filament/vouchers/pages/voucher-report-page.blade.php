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
            <x-filament::section class="col-span-full">
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
                                        name: 'Total Spent',
                                        data: rawData
                                    }],
                                    labels: JSON.parse(this.$el.dataset.labels),
                                    chart: {
                                        type: 'bar',
                                        height: 380,
                                        animations: { enabled: true, easing: 'easeinout', speed: 800 },
                                        background: 'transparent',
                                        toolbar: { show: false }
                                    },
                                    plotOptions: {
                                        bar: {
                                            borderRadius: 6,
                                            columnWidth: '45%',
                                            distributed: true
                                        }
                                    },
                                    colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16', '#06b6d4', '#475569', '#3f3f46', '#22c55e', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6'],
                                    theme: { mode: isDark ? 'dark' : 'light' },
                                    dataLabels: {
                                        enabled: false
                                    },
                                    grid: {
                                        borderColor: isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.05)',
                                        strokeDashArray: 4
                                    },
                                    xaxis: {
                                        labels: {
                                            rotate: -45,
                                            rotateAlways: true,
                                            style: { colors: textColor, fontSize: '11px', fontWeight: 500 },
                                            trim: true,
                                            maxHeight: 120
                                        }
                                    },
                                    yaxis: [{
                                        labels: {
                                            style: { colors: textColor },
                                            formatter: function(val) { return 'AED ' + Number(val).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }); }
                                        }
                                    }],
                                    legend: { show: false },
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
            <div class="col-span-full">
                {{ $this->table }}
            </div>

        </div>
    </div>
</x-filament-panels::page>
