<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Carbon\CarbonPeriod;

class TransactionVolumeChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Daily Transaction Volume';
    protected static ?int $sort = 6;
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ? \Carbon\Carbon::parse($this->filters['startDate']) : now()->subDays(30);
        $endDate = $this->filters['endDate'] ? \Carbon\Carbon::parse($this->filters['endDate']) : now();
        $branchId = $this->filters['branch_id'] ?? (auth()->user()->branch_id);

        // 1. Prepare Date Range
        $period = CarbonPeriod::create($startDate, '1 day', $endDate);
        $labels = [];
        $counts = [];

        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $labels[] = $date->format('M j');
            $counts[$key] = 0;
        }

        // 2. Fetch Data
        $results = \App\Models\Transaction::query()
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->groupBy('date')
            ->get();

        // 3. Map Data
        foreach ($results as $row) {
            $counts[$row->date] = $row->count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Transactions',
                    'data' => array_values($counts),
                    'backgroundColor' => '#8B5CF6', // Purple
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
