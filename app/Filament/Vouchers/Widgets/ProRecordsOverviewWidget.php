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

    protected int | string | array $columns = [
        'default' => 1,
        'sm' => 2,
        'xl' => 4,
    ];

    protected function getStats(): array
    {
        $today = Carbon::today();

        // 1. Overstay Fines KPI (Only count genuinely expired visas past grace period without settled fines)
        $overdueRecords = ProRecord::whereNull('fines_settled_at')
            ->whereNotNull('residence_visa_expiry')
            ->where('residence_visa_expiry', '<', $today)
            ->whereNotNull('fines_start_date')
            ->where('fines_start_date', '<', $today)
            ->get();
        $totalFines = $overdueRecords->sum(fn (ProRecord $r) => $r->calculated_total_fines);
        $overdueCount = $overdueRecords->filter(fn (ProRecord $r) => $r->calculated_total_fines > 0)->count();

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
                'Overstay Penalties',
                'AED ' . number_format($totalFines, 2)
            )
                ->description(
                    $overdueCount > 0
                        ? "{$overdueCount} employees with active fines"
                        : 'No active fines'
                )
                ->color($overdueCount > 0 ? 'danger' : 'gray')
                ->url(url('/vouchers/pro-records?activeTab=overdue_fines')),

            // KPI 2: Critical Expiry Timeline
            Stat::make(
                'Expiring Visas',
                (string) $visasExpiring30d
            )
                ->description(
                    $visasExpiring30d > 0
                        ? "{$visasExpiring30d} expiring within 30 days"
                        : 'All visas up to date'
                )
                ->color($visasExpiring30d > 0 ? 'warning' : 'gray')
                ->url(url('/vouchers/pro-records?activeTab=expiring_soon')),

            // KPI 3: In-Progress Pipeline
            Stat::make(
                'Active Pipeline',
                (string) $inProgress
            )
                ->description(
                    $inProgress > 0
                        ? "{$newJoiners} new joiners · {$renewals} renewals"
                        : 'No active cases'
                )
                ->color('gray')
                ->url(url('/vouchers/pro-records?activeTab=all')),

            // KPI 4: Total Active Processing Budget
            Stat::make(
                'Pipeline Budget',
                'AED ' . number_format($totalPipelineCost, 2)
            )
                ->description('AED ' . number_format($completedTotal, 2) . ' settled')
                ->color('gray'),
        ];
    }
}
