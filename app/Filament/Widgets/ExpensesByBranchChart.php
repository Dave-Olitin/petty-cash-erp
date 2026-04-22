<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Cache;

class ExpensesByBranchChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Top 5 Branch Expenses';
    protected static ?int $sort = 4;
    protected static ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return auth()->user()->branch_id === null;
    }

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate   = $this->filters['endDate'] ?? null;

        $cacheKey = 'expenses_by_branch_' . md5(json_encode($this->filters));

        $data = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($startDate, $endDate) {
            return \App\Models\Transaction::query()
                ->where('transactions.type', 'EXPENSE')
                ->whereNull('transactions.deleted_at')
                ->where('transactions.status', '!=', 'rejected')
                ->when($startDate, fn($q) => $q->whereDate('transactions.created_at', '>=', $startDate))
                ->when($endDate,   fn($q) => $q->whereDate('transactions.created_at', '<=', $endDate))
                ->join('branches', 'transactions.branch_id', '=', 'branches.id')
                ->selectRaw('branches.name as branch_name, SUM(transactions.amount) as total')
                ->groupBy('branches.name')
                ->orderByDesc('total')
                ->get();
        });

        if ($data->isEmpty()) {
            return [
                'datasets' => [['label' => 'Total Expenses', 'data' => [], 'backgroundColor' => '#3B82F6']],
                'labels'   => [],
            ];
        }

        // Cap visible bars at 5
        $top = $data->take(5);

        $labels = $top->pluck('branch_name')->toArray();
        $totals = $top->pluck('total')->map(fn($v) => round((float) $v, 2))->toArray();

        // Dynamic bar height: 32px per bar, min 200px
        $barCount  = count($labels);
        $chartHeight = max(200, $barCount * 36);

        return [
            'datasets' => [
                [
                    'label'           => 'Total Expenses (AED)',
                    'data'            => $totals,
                    'backgroundColor' => '#3B82F6',
                    'borderRadius'    => 4,
                    // Dynamic thickness — shrinks slightly for many bars
                    'barThickness'    => max(14, min(28, intval(280 / max($barCount, 1)))),
                ],
            ],
            'labels' => $labels,
            '_chartHeight' => $chartHeight, // passed via options below
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        $data      = $this->getData();
        $barCount  = count($data['labels']);
        $needsScroll = $barCount > 8;

        return [
            'indexAxis' => 'y', // Horizontal bars
            'maintainAspectRatio' => !$needsScroll,
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'callbacks' => [],
                ],
            ],
            'scales' => [
                'x' => [
                    'grid'  => ['display' => false],
                    'ticks' => ['font' => ['size' => 11]],
                ],
                'y' => [
                    'grid'  => ['display' => false],
                    'ticks' => [
                        'font'     => ['size' => 11],
                        'maxRotation' => 0,
                    ],
                ],
            ],
        ];
    }
}
