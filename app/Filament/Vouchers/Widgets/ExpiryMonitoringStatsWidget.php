<?php

namespace App\Filament\Vouchers\Widgets;

use App\Models\ProRecord;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExpiryMonitoringStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int | string | array $columns = 4;

    protected function getStats(): array
    {
        $today = Carbon::today();
        $in30Days = $today->copy()->addDays(30);
        $in6Months = $today->copy()->addMonths(6);

        $totalEmployees = ProRecord::count();

        // 1. Action Required: Expired Visas, EIDs, Labour Cards, or Passport < 6mo
        $actionRequired = ProRecord::where(function ($q) use ($today, $in6Months) {
            $q->where('residence_visa_expiry', '<', $today)
              ->orWhere('emirates_id_expiry', '<', $today)
              ->orWhere('labour_card_expiry', '<', $today)
              ->orWhere('passport_expiry', '<', $in6Months);
        })->count();

        // 2. Expiring Soon (≤30 days)
        $expiringSoon = ProRecord::where(function ($q) use ($today, $in30Days, $in6Months) {
            $q->where(function ($active) use ($today, $in6Months) {
                $active->where('residence_visa_expiry', '>=', $today)
                       ->where('emirates_id_expiry', '>=', $today)
                       ->where('labour_card_expiry', '>=', $today)
                       ->where('passport_expiry', '>=', $in6Months);
            })->where(function ($soon) use ($today, $in30Days) {
                $soon->whereBetween('residence_visa_expiry', [$today, $in30Days])
                     ->orWhereBetween('emirates_id_expiry', [$today, $in30Days])
                     ->orWhereBetween('labour_card_expiry', [$today, $in30Days]);
            });
        })->count();

        // 3. On Track
        $onTrack = ProRecord::where(function ($q) use ($in30Days, $in6Months) {
            $q->where('residence_visa_expiry', '>', $in30Days)
              ->where('emirates_id_expiry', '>', $in30Days)
              ->where('labour_card_expiry', '>', $in30Days)
              ->where('passport_expiry', '>=', $in6Months);
        })->count();

        // 4. Total Overstay Fines
        $totalFines = ProRecord::all()->sum('calculated_total_fines');

        return [
            Stat::make('Total Tracked', (string) $totalEmployees)
                ->description("{$onTrack} compliant & on track")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->icon('heroicon-m-user-group'),

            Stat::make('Action Required', (string) $actionRequired)
                ->description('Expired or grace active')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('Expiring Soon', (string) $expiringSoon)
                ->description('Due within 30 days')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Estimated Fines', 'AED ' . number_format($totalFines, 2))
                ->description('PR-50 & EID-20 statutory penalties')
                ->color($totalFines > 0 ? 'danger' : 'gray'),
        ];
    }
}
