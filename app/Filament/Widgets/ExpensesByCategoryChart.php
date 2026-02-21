<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ExpensesByCategoryChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Expenses by Category';
    protected static ?int $sort = 3;
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $user      = auth()->user();
        $startDate = $this->filters['startDate'] ?? null;
        $endDate   = $this->filters['endDate'] ?? null;
        $branchId  = $this->filters['branch_id'] ?? ($user->branch_id);

        // ──────────────────────────────────────────────────────────────────────
        // BUG FIX: The original query used INNER JOIN on categories, but
        // transaction_items.category_id is nullable. Any item without a category
        // was silently dropped — if NO items had a category the chart returned
        // empty. Changed to LEFT JOIN and filtered nulls in PHP so we can still
        // chart items that have a category while gracefully handling missing ones.
        // ──────────────────────────────────────────────────────────────────────
        $rawData = \App\Models\TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->leftJoin('categories', 'transaction_items.category_id', '=', 'categories.id')
            ->where('transactions.type', 'EXPENSE')
            ->whereNull('transactions.deleted_at')           // Exclude voided
            ->where('transactions.status', '!=', 'rejected') // Exclude rejected
            ->when($startDate, fn($q) => $q->whereDate('transactions.created_at', '>=', $startDate))
            ->when($endDate,   fn($q) => $q->whereDate('transactions.created_at', '<=', $endDate))
            ->when($branchId,  fn($q) => $q->where('transactions.branch_id', $branchId))
            ->selectRaw('COALESCE(categories.name, \'Uncategorised\') as category_name, SUM(transaction_items.total_price) as total')
            ->groupBy('category_name')
            ->orderByDesc('total')
            ->get();

        if ($rawData->isEmpty()) {
            return [
                'datasets' => [['label' => 'Expenses', 'data' => [], 'backgroundColor' => []]],
                'labels'   => [],
            ];
        }

        $labels = $rawData->pluck('category_name');
        $totals = $rawData->pluck('total');

        $colors = [
            '#F87171', '#FB923C', '#FBBF24', '#A3E635', '#34D399',
            '#22D3EE', '#818CF8', '#C084FC', '#F472B6', '#E879F9',
        ];

        return [
            'datasets' => [
                [
                    'label'           => 'Expenses',
                    'data'            => $totals->toArray(),
                    'backgroundColor' => array_slice($colors, 0, count($labels)),
                    'hoverOffset'     => 4,
                ],
            ],
            'labels' => $labels->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
