<?php

namespace App\Filament\Vouchers\Widgets;

use Filament\Widgets\ChartWidget;

class VoucherExpensesChart extends ChartWidget
{
    protected static ?string $heading = 'Expenses by Category (This Month)';
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth()->user()->can('access_vouchers_panel');
    }

    protected function getData(): array
    {
        $user = auth()->user();
        
        $query = \App\Models\Voucher::query()
            ->select('category_id', \Illuminate\Support\Facades\DB::raw('SUM(amount) as total'))
            ->where('status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->groupBy('category_id');

        if (! $user->isHeadOffice() && ! $user->hasAnyRole(['Accountant', 'Approver'])) {
            $query->where('user_id', $user->id);
        }

        $data = $query->with('category')->get();

        return [
            'datasets' => [
                [
                    'label' => 'Expenses (AED)',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => ['#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316', '#14b8a6', '#f43f5e', '#a855f7'],
                ],
            ],
            'labels' => $data->map(fn ($item) => $item->category?->name ?? 'Uncategorized')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
