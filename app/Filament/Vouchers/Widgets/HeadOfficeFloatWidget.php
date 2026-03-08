<?php

namespace App\Filament\Vouchers\Widgets;

use App\Models\FloatReplenishment;
use App\Models\Voucher;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class HeadOfficeFloatWidget extends BaseWidget
{
    protected static ?int $sort = 0; // Put it at the very top

    public static function canView(): bool
    {
        return auth()->user()->isHeadOffice() || auth()->user()->can('voucher.manage_float');
    }

    protected function getStats(): array
    {
        $data = \Illuminate\Support\Facades\Cache::remember('head_office_float_widget_stats', 60, function () {
            $totalReplenishing = FloatReplenishment::sum('amount');
            
            // Sum paid petty cash vouchers
            $totalSpent = Voucher::where('type', 'petty_cash')
                ->where('status', 'paid')
                ->sum('amount');
                
            $currentBalance = $totalReplenishing - $totalSpent;
            
            return compact('totalReplenishing', 'totalSpent', 'currentBalance');
        });

        extract($data);
        
        // Determine alert status
        $color = 'success';
        $icon = 'heroicon-m-check-circle';
        $description = 'Float is healthy';
        
        if ($currentBalance < 2000 && $currentBalance > 0) {
            $color = 'warning';
            $icon = 'heroicon-m-exclamation-triangle';
            $description = 'Low balance - consider replenishing soon';
        } elseif ($currentBalance <= 0) {
            $color = 'danger';
            $icon = 'heroicon-m-x-circle';
            $description = 'Float depleted! Replenishment required immediately.';
        }

        return [
            Stat::make('Head Office Petty Cash Balance', 'AED ' . Number::format($currentBalance, 2))
                ->description($description)
                ->descriptionIcon($icon)
                ->color($color),
                
            Stat::make('CASH IN', 'AED ' . Number::format($totalReplenishing, 2))
                ->description('Total funds added to float')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('gray'),
                
            Stat::make('CASH OUT', 'AED ' . Number::format($totalSpent, 2))
                ->description('Total petty cash paid out')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('gray'),
        ];
    }
}
