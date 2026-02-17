<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

use Filament\Widgets\Concerns\InteractsWithPageFilters;

class StatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    // Auto-refresh every 15 seconds to show new pending requests
    protected static ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $user = auth()->user();
        
        // Filter Logic
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $branchId = $this->filters['branch_id'] ?? ($user->branch_id); // Use filter or user's branch

        // Common Query Helper (reused inside and outside cache)
        $query = function() use ($startDate, $endDate, $branchId) {
            return Transaction::query()
                ->where('status', '!=', 'rejected') // Exclude rejected transactions
                ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId));
        };

        // Cache Key for Heavy Aggregates (Trends, Totals)
        $cacheKey = 'stats_aggregates_' . ($user->id) . '_' . md5(json_encode($this->filters));

        // REMOVED CACHING TO FIX "CONFUSION" - DATA MUST BE LIVE
        // Fetch Aggregates (Totals & Trends)
            
        $expenseTrend = $this->getTrend('EXPENSE', $branchId);
        $totalExpenses = $query()->where('type', 'EXPENSE')->sum('amount');
        
        // Initializing replenishment vars
        $replenishTrend = []; 
        $totalReplenishments = 0;

        // Only calculate replenishment stats if relevant (prevent extra queries if not needed)
        // But for simplicity/consistency we calculate them.
        $replenishTrend = $this->getTrend('REPLENISHMENT', $branchId);
        $totalReplenishments = $query()->where('type', 'REPLENISHMENT')->sum('amount');

        $cachedStats = [
            'expenseTrend' => $expenseTrend,
            'replenishTrend' => $replenishTrend,
            'totalExpenses' => $totalExpenses,
            'totalReplenishments' => $totalReplenishments,
        ];

        // LIVE Data Construction
        if ($user->isHeadOffice() && !$branchId) {
            // HQ View
            // 1. Pending Count MUST be live
            $pendingCount = $query()->where('status', 'pending')->count();

            return [
                Stat::make('Total Expenses', new \Illuminate\Support\HtmlString('<span class="privacy-mask">AED ' . number_format($cachedStats['totalExpenses'], 2) . '</span>'))
                    ->description('7-day trend')
                    ->descriptionIcon('heroicon-m-arrow-trending-up')
                    ->chart($cachedStats['expenseTrend'])
                    ->color('danger'),
                
                Stat::make('Total Replenishments', new \Illuminate\Support\HtmlString('<span class="privacy-mask">AED ' . number_format($cachedStats['totalReplenishments'], 2) . '</span>'))
                    ->description('7-day trend')
                    ->descriptionIcon('heroicon-m-arrow-trending-up')
                    ->chart($cachedStats['replenishTrend'])
                    ->color('success'),

                Stat::make('Pending Requests', $pendingCount)
                    ->description($pendingCount > 0 ? 'Action Required' : 'All clear')
                    ->icon('heroicon-o-bell-alert')
                    ->color($pendingCount > 0 ? 'warning' : 'gray'),
            ];
        } else {
            // Branch View
            // 1. Balance MUST be live
            $branch = \App\Models\Branch::find($branchId);
            $balance = $branch ? $branch->current_balance : 0;

            return [
                Stat::make('Current Balance', new \Illuminate\Support\HtmlString('<span class="privacy-mask">AED ' . number_format($balance, 2) . '</span>'))
                    ->description('Available funds')
                    ->icon('heroicon-o-banknotes')
                    ->color($balance < 1000 ? 'danger' : 'success'),

                Stat::make('Expenses', new \Illuminate\Support\HtmlString('<span class="privacy-mask">AED ' . number_format($cachedStats['totalExpenses'], 2) . '</span>'))
                    ->description('7-day trend')
                    ->chart($cachedStats['expenseTrend'])
                    ->color('danger'),
                
                Stat::make('Replenishments', new \Illuminate\Support\HtmlString('<span class="privacy-mask">AED ' . number_format($cachedStats['totalReplenishments'], 2) . '</span>'))
                    ->description('Total received')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success'),
            ];
        }
    }

    protected function getTrend(string $type, ?int $branchId = null): array
    {
        return Transaction::query()
            ->where('type', $type)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, sum(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total')
            ->toArray();
    }
}
