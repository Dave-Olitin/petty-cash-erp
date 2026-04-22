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

    // Refresh every 60s — balances freshness with DB load.
    // 15s was too aggressive and caused continuous background queries.
    protected static ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()->can('access_petty_cash_panel');
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        
        // Filter Logic
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $branchId = $user->isHeadOffice() ? ($this->filters['branch_id'] ?? null) : $user->branch_id; // Secure filter extraction

        // Common Query Helper (reused inside and outside cache)
        $query = function() use ($startDate, $endDate, $branchId) {
            return Transaction::query()
                ->where('status', '!=', 'rejected') // Exclude rejected transactions
                ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId));
        };

        // Trend data: cached for 30s — close enough to live, saves DB load during polling
        $cacheKey = 'stats_trends_' . ($user->id) . '_' . md5(json_encode($this->filters));

        $cachedStats = \Illuminate\Support\Facades\Cache::remember($cacheKey, 30, function () use ($query) {
            return [
                'expenseTrend'       => $this->getTrend('EXPENSE', $this->filters['branch_id'] ?? auth()->user()->branch_id),
                'replenishTrend'     => $this->getTrend('REPLENISHMENT', $this->filters['branch_id'] ?? auth()->user()->branch_id),
                'totalExpenses'      => $query()->where('type', 'EXPENSE')->sum('amount'),
                'totalReplenishments'=> $query()->where('type', 'REPLENISHMENT')->sum('amount'),
            ];
        });

        // LIVE Data Construction
        if ($user->isHeadOffice() && !$branchId) {
            // HQ View
            // 1. Pending Count MUST be live
            $pendingCount = $query()->where('status', 'pending')->count();

            return [
                Stat::make('Total Expenses', new \Illuminate\Support\HtmlString('<span class="privacy-mask font-normal text-3xl">AED ' . number_format($cachedStats['totalExpenses'], 2) . '</span>'))
                    ->description(new \Illuminate\Support\HtmlString('<span style="color: #e11d48;" class="font-medium">7-day trend</span>'))
                    ->descriptionIcon('heroicon-m-arrow-trending-up')
                    ->chart($cachedStats['expenseTrend'])
                    ->color('danger'),
                
                Stat::make('Total Replenishments', new \Illuminate\Support\HtmlString('<span class="privacy-mask font-normal text-3xl">AED ' . number_format($cachedStats['totalReplenishments'], 2) . '</span>'))
                    ->description(new \Illuminate\Support\HtmlString('<span style="color: #4f46e5;" class="font-medium">7-day trend</span>'))
                    ->descriptionIcon('heroicon-m-arrow-trending-up')
                    ->chart($cachedStats['replenishTrend'])
                    ->color('success'),

                Stat::make('Pending Requests', new \Illuminate\Support\HtmlString('<span class="font-normal text-3xl">' . $pendingCount . '</span>'))
                    ->description(new \Illuminate\Support\HtmlString('<span style="color: #d97706;" class="font-medium">' . ($pendingCount > 0 ? 'Action Required' : 'All clear') . '</span>'))
                    ->icon('heroicon-o-bell-alert')
                    ->color($pendingCount > 0 ? 'warning' : 'gray'),
            ];
        } else {
            // Branch View
            // 1. Balance MUST be live
            $branch = \App\Models\Branch::find($branchId);
            $balance = $branch ? $branch->current_balance : 0;

            return [
                Stat::make('Current Balance', new \Illuminate\Support\HtmlString('<span class="privacy-mask font-normal text-3xl">AED ' . number_format($balance, 2) . '</span>'))
                    ->description(new \Illuminate\Support\HtmlString('<span style="color: #4f46e5;" class="font-medium">Available funds</span>'))
                    ->icon('heroicon-o-banknotes')
                    ->color($balance < 1000 ? 'danger' : 'success'),

                Stat::make('Expenses', new \Illuminate\Support\HtmlString('<span class="privacy-mask font-normal text-3xl">AED ' . number_format($cachedStats['totalExpenses'], 2) . '</span>'))
                    ->description(new \Illuminate\Support\HtmlString('<span style="color: #e11d48;" class="font-medium">7-day trend</span>'))
                    ->chart($cachedStats['expenseTrend'])
                    ->color('danger'),
                
                Stat::make('Replenishments', new \Illuminate\Support\HtmlString('<span class="privacy-mask font-normal text-3xl">AED ' . number_format($cachedStats['totalReplenishments'], 2) . '</span>'))
                    ->description(new \Illuminate\Support\HtmlString('<span style="color: #059669;" class="font-medium">Total received</span>'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success'),
            ];
        }
    }

    protected function getTrend(string $type, ?int $branchId = null): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate   = $this->filters['endDate'] ?? null;

        // Default to last 7 days if no date filter is set
        $from = $startDate ? \Carbon\Carbon::parse($startDate) : now()->subDays(7);
        $to   = $endDate   ? \Carbon\Carbon::parse($endDate)->endOfDay() : now();

        $trend = Transaction::query()
            ->where('type', $type)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as date, sum(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total')
            ->toArray();

        // Filament requires at least 2 data points to render an SVG sparkline trend, 
        // otherwise it throws a Division By Zero error.
        return count($trend) < 2 ? [0, 0] : $trend;
    }
}
