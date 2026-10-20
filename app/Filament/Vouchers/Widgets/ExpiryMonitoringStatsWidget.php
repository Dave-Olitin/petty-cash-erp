<?php

namespace App\Filament\Vouchers\Widgets;

use App\Filament\Vouchers\Pages\ExpiryMonitoringDashboard;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExpiryMonitoringStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int | string | array $columns = [
        'default' => 1,
        'sm' => 2,
        'md' => 3,
        'xl' => 5,
    ];

    protected function getStats(): array
    {
        $metrics = ExpiryMonitoringDashboard::calculateSummaryMetrics();

        return [
            Stat::make('Total Tracked', (string) $metrics['total'])
                ->description('Documents & credentials')
                ->icon('heroicon-m-document-text'),

            Stat::make('Action Required', (string) $metrics['action_required'])
                ->description('Expired or grace active')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('Expiring Soon', (string) $metrics['expiring_soon'])
                ->description('Due within 30 days')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('On Track', (string) $metrics['on_track'])
                ->description('Compliant & healthy')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Estimated Fines', 'AED ' . number_format($metrics['total_fines'], 2))
                ->description('PR-50 & EID-20 penalties')
                ->color($metrics['total_fines'] > 0 ? 'danger' : 'gray'),
        ];
    }
}
