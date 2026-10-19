<?php

namespace App\Filament\Vouchers\Widgets;

use App\Filament\Vouchers\Resources\ProRecordResource;
use App\Models\ProRecord;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class ProRecordsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    // Only render on the PRO Records List page, not the main Dashboard
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $today = Carbon::today();

        // 1. Overstay Fines KPI
        $overdueRecords = ProRecord::whereNotNull('fines_start_date')
            ->where('fines_start_date', '<', $today)
            ->get();
        $totalFines = $overdueRecords->sum(fn (ProRecord $r) => $r->calculated_total_fines);
        $overdueCount = $overdueRecords->count();

        // 2. Visas & Passports Expiring (< 30 days for visa, < 6mo for passport)
        $visasExpiring30d = ProRecord::whereNotNull('residence_visa_expiry')
            ->whereBetween('residence_visa_expiry', [$today, $today->copy()->addDays(30)])
            ->count();
        $passportsExpiring6mo = ProRecord::whereNotNull('passport_expiry')
            ->whereBetween('passport_expiry', [$today, $today->copy()->addMonths(6)])
            ->count();

        // 3. Pipeline Breakdown (In-Progress)
        $inProgress = ProRecord::where('status', 'in_progress')->count();
        $newJoiners = ProRecord::where('status', 'in_progress')
            ->whereIn('process_type', ['new_inside', 'new_outside'])
            ->count();
        $renewals = ProRecord::where('status', 'in_progress')
            ->where('process_type', 'renewal')
            ->count();

        // 4. Financial Pipeline Exposure
        $totalPipelineCost = ProRecord::where('status', 'in_progress')->sum('total_cost');
        $completedTotal = ProRecord::where('status', 'completed')->sum('total_cost');

        return [
            // KPI 1: Active Overstay Fines
            Stat::make(
                'Live Overstay Penalties',
                new HtmlString('<span class="font-bold text-3xl ' . ($overdueCount > 0 ? 'text-rose-600' : 'text-emerald-600') . '">AED ' . number_format($totalFines, 2) . '</span>')
            )
                ->description(new HtmlString(
                    $overdueCount > 0
                        ? '<span class="font-medium text-rose-600">⚠️ ' . $overdueCount . ' employee(s) incurring fines</span>'
                        : '<span class="font-medium text-emerald-600">✅ 100% Compliant — No penalties</span>'
                ))
                ->descriptionIcon($overdueCount > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-shield-check')
                ->color($overdueCount > 0 ? 'danger' : 'success')
                ->url(url('/vouchers/pro-records?activeTab=overdue_fines')),

            // KPI 2: Critical Expiry Timeline
            Stat::make(
                'Visas Expiring (< 30 Days)',
                new HtmlString('<span class="font-bold text-3xl ' . ($visasExpiring30d > 0 ? 'text-amber-600' : 'text-emerald-600') . '">' . $visasExpiring30d . '</span>')
            )
                ->description(new HtmlString(
                    '<span class="font-medium text-gray-500">' .
                    ($visasExpiring30d > 0 ? 'Requires immediate renewal submission' : 'All visas in safe validity') .
                    ($passportsExpiring6mo > 0 ? " • {$passportsExpiring6mo} passport(s) < 6mo" : '') .
                    '</span>'
                ))
                ->descriptionIcon($visasExpiring30d > 0 ? 'heroicon-m-bell-alert' : 'heroicon-m-check-circle')
                ->color($visasExpiring30d > 0 ? 'warning' : 'success')
                ->url(url('/vouchers/pro-records?activeTab=expiring_soon')),

            // KPI 3: In-Progress Pipeline
            Stat::make(
                'Active Visa Pipeline',
                new HtmlString('<span class="font-bold text-3xl text-primary-600">' . $inProgress . '</span>')
            )
                ->description(new HtmlString(
                    '<span class="font-medium text-gray-500">' .
                    "{$newJoiners} New Joiner(s) • {$renewals} Renewal(s)" .
                    '</span>'
                ))
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('primary')
                ->url(url('/vouchers/pro-records?activeTab=all')),

            // KPI 4: Total Active Processing Budget
            Stat::make(
                'Active Pipeline Budget',
                new HtmlString('<span class="font-bold text-3xl text-emerald-600">AED ' . number_format($totalPipelineCost, 2) . '</span>')
            )
                ->description(new HtmlString(
                    '<span class="font-medium text-gray-500">Completed visas: AED ' . number_format($completedTotal, 2) . '</span>'
                ))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}
