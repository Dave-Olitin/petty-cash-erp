<?php

namespace App\Filament\Vouchers\Widgets;

use App\Models\Liquidation;
use App\Models\Voucher;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LiquidationSummaryWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    // Lives on the Liquidations list page, not the Overview dashboard
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $pending  = Liquidation::where('status', 'pending')->count();
        $overdue  = Liquidation::overdue()->count();
        $complete = Liquidation::complete()->count();

        $outstandingAmount = Liquidation::where('liquidations.status', 'pending')
            ->join('vouchers', 'vouchers.id', '=', 'liquidations.voucher_id')
            ->sum('vouchers.amount');

        $avgDays = Liquidation::complete()
            ->whereNotNull('liquidated_at')
            ->selectRaw('AVG(DATEDIFF(liquidated_at, created_at)) as avg_days')
            ->value('avg_days');

        return [
            Stat::make('Awaiting Settlement', $pending)
                ->description($overdue > 0 ? "{$overdue} past their deadline" : 'All within deadline')
                ->descriptionIcon($overdue > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overdue > 0 ? 'danger' : ($pending > 0 ? 'warning' : 'success'))
                ->url(\App\Filament\Vouchers\Resources\LiquidationResource::getUrl('index') . '?tableTab=pending'),

            Stat::make('Cash Not Yet Returned', 'AED ' . number_format($outstandingAmount, 2))
                ->description('Petty cash advances still to be accounted for')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($outstandingAmount > 0 ? 'warning' : 'success'),

            Stat::make('Overdue — Needs Follow-Up', $overdue)
                ->description($overdue > 0 ? 'Employees have not submitted receipts yet' : 'No overdue settlements')
                ->descriptionIcon($overdue > 0 ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                ->color($overdue > 0 ? 'danger' : 'success')
                ->url(\App\Filament\Vouchers\Resources\LiquidationResource::getUrl('index') . '?tableTab=overdue'),

            Stat::make('Average Days to Settle', $avgDays ? round($avgDays) . ' days' : 'N/A')
                ->description('How long employees typically take to file receipts')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),
        ];
    }
}
