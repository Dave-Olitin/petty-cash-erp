<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ExpensesByBranchChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Expenses by Branch';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];
    protected static ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        // Only Head Office needs to compare branches
        return auth()->user()->branch_id === null;
    }

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        
        // Query to sum expenses per branch
        $cacheKey = 'expenses_by_branch_' . md5(json_encode($this->filters));

        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(10), function () use ($startDate, $endDate) {
            return \App\Models\Transaction::query()
                ->where('transactions.type', 'EXPENSE')
                ->when($startDate, fn($q) => $q->whereDate('transactions.created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('transactions.created_at', '<=', $endDate))
                ->join('branches', 'transactions.branch_id', '=', 'branches.id')
                ->selectRaw('branches.name as branch_name, sum(transactions.amount) as total')
                ->groupBy('branches.name')
                ->orderByDesc('total')
                ->get();
        });

        return [
            'datasets' => [
                [
                    'label' => 'Total Expenses',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => '#3B82F6', // Blue
                    'borderRadius' => 4,
                    'barThickness' => 20,
                ],
            ],
            'labels' => $data->pluck('branch_name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
                'y' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
        ];
    }
}
