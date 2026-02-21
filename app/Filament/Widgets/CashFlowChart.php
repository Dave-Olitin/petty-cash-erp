<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Carbon\CarbonPeriod;

class CashFlowChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Cash Flow';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $user = auth()->user();
        
        $filterStart = $this->filters['startDate'] ? \Carbon\Carbon::parse($this->filters['startDate']) : now()->subMonths(5)->startOfMonth();
        $filterEnd = $this->filters['endDate'] ? \Carbon\Carbon::parse($this->filters['endDate'])->endOfDay() : now()->endOfMonth();
        $branchId = $this->filters['branch_id'] ?? ($user->branch_id);

        $cacheKey = 'cash_flow_' . ($user->id) . '_' . md5(json_encode($this->filters));

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(10), function () use ($filterStart, $filterEnd, $branchId) {
            // Determine Granularity
            $diffInDays = $filterStart->diffInDays($filterEnd);
            $groupBy = $diffInDays > 60 ? 'Month' : 'Day';
            $format = $groupBy === 'Month' ? 'Y-m' : 'Y-m-d';
            $excludeTime = $groupBy === 'Month' ? '%Y-%m' : '%Y-%m-%d';
            $displayFormat = $groupBy === 'Month' ? 'M Y' : 'M j';

            // 1. Initialize arrays
            $labels = [];
            $expensesData = [];
            $replenishmentsData = [];
            
            $interval = $groupBy === 'Month' ? '1 month' : '1 day';
            $period = CarbonPeriod::create($filterStart, $interval, $filterEnd);
            
            foreach ($period as $date) {
                $key = $date->format($format);
                $labels[] = $date->format($displayFormat);
                $expensesData[$key] = 0;
                $replenishmentsData[$key] = 0;
            }

            // 2. Fetch Data — using DB-agnostic raw date (no DATE_FORMAT which is MySQL-only)
            $results = \App\Models\Transaction::query()
                ->whereBetween('created_at', [$filterStart, $filterEnd])
                ->whereNull('deleted_at')
                ->where('status', '!=', 'rejected')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->select('created_at', 'type', 'amount')
                ->get();

            // 3. Map Results — group in PHP using Carbon (DB-agnostic)
            foreach ($results as $row) {
                $key = \Carbon\Carbon::parse($row->created_at)->format($format);
                if (!isset($expensesData[$key])) continue; // outside range (paranoia guard)

                if ($row->type === 'EXPENSE') {
                    $expensesData[$key] = ($expensesData[$key] ?? 0) + (float) $row->amount;
                } elseif ($row->type === 'REPLENISHMENT') {
                    $replenishmentsData[$key] = ($replenishmentsData[$key] ?? 0) + (float) $row->amount;
                }
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Expenses',
                        'data' => array_values($expensesData),
                        'borderColor' => '#EF4444', // Red
                        'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                        'fill' => true,
                        'tension' => 0.4,
                    ],
                    [
                        'label' => 'Replenishments',
                        'data' => array_values($replenishmentsData),
                        'borderColor' => '#10B981', // Green
                        'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                        'fill' => true,
                        'tension' => 0.4,
                    ],
                ],
                'labels' => $labels,
            ];
        });
    }

    protected function getType(): string
    {
        return 'line';
    }
}
