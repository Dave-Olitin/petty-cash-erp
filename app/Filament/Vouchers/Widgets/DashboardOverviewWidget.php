<?php

namespace App\Filament\Vouchers\Widgets;

use App\Models\Denomination;
use App\Models\FloatReplenishment;
use App\Models\Liquidation;
use App\Models\Voucher;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

class DashboardOverviewWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-overview-widget';

    protected int | string | array $columnSpan = 'full';

    public function getColumnSpan(): int | string | array
    {
        return 'full';
    }

    protected static ?int $sort = 0;

    protected function getViewData(): array
    {
        $user = auth()->user();
        
        // Dynamic cache key to guarantee that data updates are fast but do not hit the DB on every single reload
        $cacheKey = 'dashboard_overview_widget_stats_' . $user->id;

        return Cache::remember($cacheKey, 60, function () use ($user) {
            
            // ── 1. GRAND TOTAL IN BOX (Net Cash) ──
            $replenished = Denomination::where('denominatable_type', FloatReplenishment::class)->sum('total_amount');
            
            $receipts = Denomination::where('denominatable_type', Voucher::class)
                ->whereHasMorph('denominatable', [Voucher::class], function ($query) {
                    $query->where('type', 'receipt')
                          ->where('status', 'paid');
                })
                ->get()
                ->sum(fn ($d) => (float) $d->total_amount - (float) $d->change_given);

            $payments = Denomination::where('denominatable_type', Voucher::class)
                ->whereHasMorph('denominatable', [Voucher::class], function ($query) {
                    $query->where(function ($q) {
                        $q->whereIn('type', ['payment', 'petty_cash'])->orWhereNull('type');
                    })->where('status', 'paid');
                })
                ->get()
                ->sum(fn ($d) => (float) $d->total_amount - ($d->is_change_received ? (float) $d->change_given : 0));

            $grandTotalInBox = (float) ($replenished + $receipts - $payments);

            // Format for massive display (e.g. -33K or 1.2M or 75K)
            $grandTotalInBoxFormatted = $this->formatMassiveValue($grandTotalInBox);
            $grandTotalInBoxExact = 'AED ' . number_format($grandTotalInBox, 2);

            // ── 2. TOTAL SPENT THIS MONTH ──
            $baseVoucherQuery = Voucher::query()
                ->whereIn('type', ['petty_cash', 'payment'])
                ->leftJoin('denominations', function (\Illuminate\Database\Query\JoinClause $join) {
                    $join->on('vouchers.id', '=', 'denominations.denominatable_id')
                         ->where('denominations.denominatable_type', Voucher::class);
                });

            if (!$user->isHeadOffice() && !$user->hasAnyRole(['Accountant', 'Approver', 'Admin', 'Super Admin'])) {
                $baseVoucherQuery->where('vouchers.user_id', $user->id);
            }

            $thisMonth = now()->startOfMonth();
            $lastMonth = now()->subMonth()->startOfMonth();
            $lastMonthEnd = now()->subMonth()->endOfMonth();

            $paidThisMonth = (clone $baseVoucherQuery)
                ->where('vouchers.status', 'paid')
                ->whereBetween('vouchers.updated_at', [$thisMonth, now()])
                ->sum(DB::raw('vouchers.amount - COALESCE(denominations.prior_deduction, 0)'));

            $paidLastMonth = (clone $baseVoucherQuery)
                ->where('vouchers.status', 'paid')
                ->whereBetween('vouchers.updated_at', [$lastMonth, $lastMonthEnd])
                ->sum(DB::raw('vouchers.amount - COALESCE(denominations.prior_deduction, 0)'));

            $momDiff = (float) ($paidThisMonth - $paidLastMonth);
            $momDiffFormatted = number_format(abs($momDiff), 2);
            $momIsDecrease = $momDiff < 0;

            $totalSpentFormatted = 'AED ' . $this->formatMassiveValue($paidThisMonth);

            // ── 3. CASH NOT YET RETURNED (Outstanding Advances) ──
            $outstandingAmount = Liquidation::where('liquidations.status', 'pending')
                ->join('vouchers', 'vouchers.id', '=', 'liquidations.voucher_id')
                ->sum(DB::raw('vouchers.amount - COALESCE(liquidations.prior_deduction, 0)'));

            $cashNotYetReturnedFormatted = number_format($outstandingAmount, 2);

            // ── 4. ACTION REQUIRED PILLS ──
            $pendingVouchersCount = Voucher::whereIn('status', ['pending_checker', 'pending_approver']);
            if (!$user->isHeadOffice() && !$user->hasAnyRole(['Accountant', 'Approver', 'Admin', 'Super Admin'])) {
                $pendingVouchersCount->where('user_id', $user->id);
            }
            $pendingVouchersCount = $pendingVouchersCount->count();

            $readyToPayCount = Voucher::where('status', 'approved');
            if (!$user->isHeadOffice() && !$user->hasAnyRole(['Accountant', 'Approver', 'Admin', 'Super Admin'])) {
                $readyToPayCount->where('user_id', $user->id);
            }
            $readyToPayCount = $readyToPayCount->count();

            $awaitingSettlementCount = Liquidation::where('status', 'pending')->count();
            
            $overdueCount = Liquidation::overdue()->count();

            // ── 5. CASH FLOW ARCHITECTURE (Ledger Flow) ──
            $totalReplenishingAmount = FloatReplenishment::sum('amount');
            $totalReceiptsAmount = Voucher::where('type', 'receipt')
                ->where('status', 'paid')
                ->sum('amount');
            $totalCashOutAmount = Voucher::whereIn('type', ['petty_cash', 'payment'])
                ->where('status', 'paid')
                ->sum('amount');

            $avgDays = Liquidation::complete()
                ->whereNotNull('liquidated_at')
                ->selectRaw('AVG(DATEDIFF(liquidated_at, created_at)) as avg_days')
                ->value('avg_days');
            $avgDaysFormatted = $avgDays ? round($avgDays) . ' Day' . (round($avgDays) > 1 ? 's' : '') : 'N/A';

            return [
                'grandTotalInBox'             => $grandTotalInBox,
                'grandTotalInBoxFormatted'    => $grandTotalInBoxFormatted,
                'grandTotalInBoxExact'        => $grandTotalInBoxExact,
                
                'paidThisMonth'               => $paidThisMonth,
                'totalSpentFormatted'         => $totalSpentFormatted,
                'momDiffFormatted'            => $momDiffFormatted,
                'momIsDecrease'               => $momIsDecrease,

                'outstandingAmount'           => $outstandingAmount,
                'cashNotYetReturnedFormatted' => $cashNotYetReturnedFormatted,

                'pendingVouchersCount'        => $pendingVouchersCount,
                'readyToPayCount'             => $readyToPayCount,
                'awaitingSettlementCount'     => $awaitingSettlementCount,
                'overdueCount'                => $overdueCount,

                'totalReplenishingAmount'     => 'AED ' . number_format($totalReplenishingAmount, 2),
                'totalReceiptsAmount'         => 'AED ' . number_format($totalReceiptsAmount, 2),
                'totalCashOutAmount'          => 'AED ' . number_format($totalCashOutAmount, 2),
                'avgDaysFormatted'            => $avgDaysFormatted,
            ];
        });
    }

    private function formatMassiveValue(float $value): string
    {
        $absVal = abs($value);
        $sign = $value < 0 ? '-' : '';

        if ($absVal >= 1000000) {
            return $sign . round($absVal / 1000000, 2) . 'M';
        }
        if ($absVal >= 1000) {
            return $sign . round($absVal / 1000, 1) . 'K';
        }
        return $sign . number_format($absVal, 2);
    }
}
