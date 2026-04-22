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
            ->sum(\Illuminate\Support\Facades\DB::raw('vouchers.amount - COALESCE(liquidations.prior_deduction, 0)'));

        $avgDays = Liquidation::complete()
            ->whereNotNull('liquidated_at')
            ->selectRaw('AVG(DATEDIFF(liquidated_at, created_at)) as avg_days')
            ->value('avg_days');

        return [
            Stat::make('Awaiting Settlement', new \Illuminate\Support\HtmlString('<span class="font-normal text-3xl">' . $pending . '</span>'))
                ->description(new \Illuminate\Support\HtmlString('<span style="color: #d97706;" class="font-medium">' . ($overdue > 0 ? "{$overdue} past their deadline" : 'All within deadline') . '</span>'))
                ->descriptionIcon($overdue > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overdue > 0 ? 'danger' : ($pending > 0 ? 'warning' : 'success'))
                ->url(\App\Filament\Vouchers\Resources\LiquidationResource::getUrl('index') . '?tableTab=pending'),

            Stat::make('Cash Not Yet Returned', new \Illuminate\Support\HtmlString('<span class="font-normal text-3xl">AED ' . number_format($outstandingAmount, 2) . '</span>'))
                ->description(new \Illuminate\Support\HtmlString('<span style="color: #e11d48;" class="font-medium">Petty cash advances still to be accounted for</span>'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($outstandingAmount > 0 ? 'warning' : 'success'),

            Stat::make('Overdue — Needs Follow-Up', new \Illuminate\Support\HtmlString('<span class="font-normal text-3xl">' . $overdue . '</span>'))
                ->description(new \Illuminate\Support\HtmlString('<span style="color: #b91c1c;" class="font-medium">' . ($overdue > 0 ? 'Employees have not submitted receipts yet' : 'No overdue settlements') . '</span>'))
                ->descriptionIcon($overdue > 0 ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                ->color($overdue > 0 ? 'danger' : 'success')
                ->url(\App\Filament\Vouchers\Resources\LiquidationResource::getUrl('index') . '?tableTab=overdue'),

            Stat::make('Average Days to Settle', new \Illuminate\Support\HtmlString('<span class="font-normal text-3xl">' . ($avgDays ? round($avgDays) . ' days' : 'N/A') . '</span>'))
                ->description(new \Illuminate\Support\HtmlString('<span style="color: #4f46e5;" class="font-medium">How long employees typically take to file receipts</span>'))
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),
        ];
    }
}
