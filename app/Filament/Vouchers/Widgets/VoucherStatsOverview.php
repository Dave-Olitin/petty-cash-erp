<?php

namespace App\Filament\Vouchers\Widgets;

use App\Models\Voucher;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class VoucherStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        $user = auth()->user();
        return $user?->hasAnyRole(['Admin', 'Super Admin', 'Accountant', 'Approver'])
            || $user?->can('manage_settings');
    }

    protected function getStats(): array
    {
        $user = auth()->user();

        // Cache the heavy aggregations for 60 seconds per user/role context
        $cacheKey = "voucher_stats_data_" . ($user->isHeadOffice() ? 'ho' : $user->id);
        
        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, 60, function () use ($user) {
            // Base query — scope to user if they're a regular requester
            // ONLY count petty cash and payment vouchers, as Receipt Vouchers are not expenses
            // and Bank Encashments are float transfers.
            $base = Voucher::query()
                ->select('vouchers.*')
                ->whereIn('vouchers.type', ['petty_cash', 'payment'])
                ->leftJoin('denominations', function (\Illuminate\Database\Query\JoinClause $join) {
                    $join->on('vouchers.id', '=', 'denominations.denominatable_id')
                         ->where('denominations.denominatable_type', Voucher::class);
                });
                
            if (!$user->isHeadOffice() && !$user->hasAnyRole(['Accountant', 'Approver', 'Admin', 'Super Admin'])) {
                $base->where('vouchers.user_id', $user->id);
            }

            // --- This Month vs Last Month ---
            $thisMonth = now()->startOfMonth();
            $lastMonth = now()->subMonth()->startOfMonth();
            $lastMonthEnd = now()->subMonth()->endOfMonth();

            $paidThisMonth = (clone $base)->where('vouchers.status', 'paid')
                ->whereBetween('vouchers.updated_at', [$thisMonth, now()])
                ->sum(\Illuminate\Support\Facades\DB::raw('vouchers.amount - COALESCE(denominations.prior_deduction, 0)'));

            $paidLastMonth = (clone $base)->where('vouchers.status', 'paid')
                ->whereBetween('vouchers.updated_at', [$lastMonth, $lastMonthEnd])
                ->sum(\Illuminate\Support\Facades\DB::raw('vouchers.amount - COALESCE(denominations.prior_deduction, 0)'));

            // Month-over-month trend for paid amounts (last 6 months)
            $paidTrend = collect(range(5, 0))->map(fn ($i) =>
                (clone $base)->where('vouchers.status', 'paid')
                    ->whereBetween('vouchers.updated_at', [
                        now()->subMonths($i)->startOfMonth(),
                        now()->subMonths($i)->endOfMonth(),
                    ])
                    ->sum(\Illuminate\Support\Facades\DB::raw('vouchers.amount - COALESCE(denominations.prior_deduction, 0)'))
            )->values()->toArray();

            // --- Pending Approvals (last 7 days trend) ---
            $pendingNow = (clone $base)->whereIn('vouchers.status', ['pending_checker', 'pending_approver'])->count();
            $pendingTrend = collect(range(6, 0))->map(fn ($i) =>
                (clone $base)->whereIn('vouchers.status', ['pending_checker', 'pending_approver'])
                    ->whereDate('vouchers.updated_at', now()->subDays($i))
                    ->count()
            )->values()->toArray();

            // --- Ready-to-Pay (fully approved) ---
            $readyToPay = (clone $base)->where('vouchers.status', 'approved')->count();
            $readyTrend = collect(range(6, 0))->map(fn ($i) =>
                (clone $base)->where('vouchers.status', 'approved')
                    ->whereDate('vouchers.updated_at', now()->subDays($i))
                    ->count()
            )->values()->toArray();

            return compact('paidThisMonth', 'paidLastMonth', 'paidTrend', 'pendingNow', 'pendingTrend', 'readyToPay', 'readyTrend');
        });

        // Extract variables from cached array
        extract($data);

        // --- MoM description ---
        $momDiff   = $paidThisMonth - $paidLastMonth;
        $momLabel  = $momDiff >= 0
            ? '▲ AED ' . Number::format(abs($momDiff), 2) . ' vs last month'
            : '▼ AED ' . Number::format(abs($momDiff), 2) . ' vs last month';
        $momColor  = $momDiff >= 0 ? 'danger' : 'success'; // More spending = warning; less = good

        return [
            Stat::make('Vouchers Waiting for Approval', new \Illuminate\Support\HtmlString('<span class="font-normal text-3xl">' . $pendingNow . '</span>'))
                ->description(new \Illuminate\Support\HtmlString('<span style="color: #d97706;" class="font-medium">Submitted vouchers not yet checked or signed off</span>'))
                ->descriptionIcon('heroicon-m-clock')
                ->chart($pendingTrend)
                ->color($pendingNow > 5 ? 'danger' : ($pendingNow > 0 ? 'warning' : 'success')),

            Stat::make('Approved — Ready to Pay', new \Illuminate\Support\HtmlString('<span class="font-normal text-3xl">' . $readyToPay . '</span>'))
                ->description(new \Illuminate\Support\HtmlString('<span style="color: #059669;" class="font-medium">Vouchers fully signed off, waiting for cash/cheque release</span>'))
                ->descriptionIcon('heroicon-m-check-badge')
                ->chart($readyTrend)
                ->color($readyToPay > 0 ? 'info' : 'success'),

            Stat::make('Total Spent This Month', new \Illuminate\Support\HtmlString('<span class="font-normal text-3xl">AED ' . Number::format($paidThisMonth, 2) . '</span>'))
                ->description(new \Illuminate\Support\HtmlString('<span style="color: ' . ($momDiff >= 0 ? '#e11d48' : '#0d9488') . ';" class="font-medium">' . $momLabel . '</span>'))
                ->descriptionIcon($momDiff >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($paidTrend)
                ->color($momColor),
        ];
    }
}
