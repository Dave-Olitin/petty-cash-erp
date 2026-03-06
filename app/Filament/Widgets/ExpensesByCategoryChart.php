<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ExpensesByCategoryChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Expenses by Account Code';
    protected static ?int $sort = 3;
    protected static ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $user      = auth()->user();
        $startDate = $this->filters['startDate'] ?? null;
        $endDate   = $this->filters['endDate'] ?? null;
        $branchId  = $user->isHeadOffice() ? ($this->filters['branch_id'] ?? null) : $user->branch_id;

        $rawData = \App\Models\TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->leftJoin('account_codes', 'transaction_items.account_code_id', '=', 'account_codes.id')
            ->where('transactions.type', 'EXPENSE')
            ->whereNull('transactions.deleted_at')
            ->where('transactions.status', '!=', 'rejected')
            ->when($startDate, fn($q) => $q->whereDate('transactions.created_at', '>=', $startDate))
            ->when($endDate,   fn($q) => $q->whereDate('transactions.created_at', '<=', $endDate))
            ->when($branchId,  fn($q) => $q->where('transactions.branch_id', $branchId))
            ->selectRaw("COALESCE(account_codes.name, 'Unassigned') as label, SUM(transaction_items.total_price) as total")

            ->groupBy('label')
            ->orderByDesc('total')
            ->get();

        if ($rawData->isEmpty()) {
            return [
                'datasets' => [['label' => 'Expenses', 'data' => [], 'backgroundColor' => []]],
                'labels'   => [],
            ];
        }

        // Cap at top 8, collapse the rest into "Others"
        $top    = $rawData->take(8);
        $others = $rawData->skip(8);

        $labels = $top->pluck('label')->toArray();
        $totals = $top->pluck('total')->map(fn($v) => round((float) $v, 2))->toArray();

        if ($others->isNotEmpty()) {
            $labels[] = 'Others (' . $others->count() . ')';
            $totals[] = round($others->sum('total'), 2);
        }

        $colors = [
            '#F87171', '#FB923C', '#FBBF24', '#A3E635', '#34D399',
            '#22D3EE', '#818CF8', '#C084FC', '#F472B6', '#94A3B8',
        ];

        return [
            'datasets' => [
                [
                    'label'           => 'Expenses (AED)',
                    'data'            => $totals,
                    'backgroundColor' => array_slice($colors, 0, count($labels)),
                    'hoverOffset'     => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'right',
                    'labels'   => [
                        'boxWidth'  => 12,
                        'padding'   => 10,
                        'font'      => ['size' => 11],
                    ],
                ],
                'tooltip' => [
                    'callbacks' => [],
                ],
            ],
            'scales'  => [
                'x' => ['display' => false],
                'y' => ['display' => false],
            ],
        ];
    }
}
