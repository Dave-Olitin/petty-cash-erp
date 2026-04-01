<?php

namespace App\Filament\Vouchers\Widgets;

use App\Models\Liquidation;
use App\Models\Voucher;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LiquidationSummaryWidget extends BaseWidget
{
    protected static ?int $sort = 2;

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
            Stat::make('Pending Liquidations', $pending)
                ->description($overdue > 0 ? "{$overdue} overdue" : 'All within deadline')
                ->descriptionIcon($overdue > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overdue > 0 ? 'danger' : ($pending > 0 ? 'warning' : 'success'))
                ->url(\App\Filament\Vouchers\Resources\LiquidationResource::getUrl('index') . '?tableTab=pending'),

            Stat::make('Outstanding Amount', 'AED ' . number_format($outstandingAmount, 2))
                ->description('Unrecovered petty cash advances')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($outstandingAmount > 0 ? 'warning' : 'success'),

            Stat::make('Overdue Liquidations', $overdue)
                ->description($overdue > 0 ? 'Past their due date — follow up required' : 'None overdue')
                ->descriptionIcon($overdue > 0 ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                ->color($overdue > 0 ? 'danger' : 'success')
                ->url(\App\Filament\Vouchers\Resources\LiquidationResource::getUrl('index') . '?tableTab=overdue'),

            Stat::make('Avg. Settlement Time', $avgDays ? round($avgDays) . ' days' : 'N/A')
                ->description('Average days to settle a liquidation')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),
        ];
    }
}
