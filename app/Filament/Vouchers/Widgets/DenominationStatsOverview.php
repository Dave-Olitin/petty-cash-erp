<?php

namespace App\Filament\Vouchers\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Denomination;
use App\Models\FloatReplenishment;
use App\Models\Voucher;

class DenominationStatsOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        // Amount added to box from replenishments
        $replenished = Denomination::where('denominatable_type', FloatReplenishment::class)->sum('total_amount');
        
        // Amount added to box from receipt vouchers (collecting funds)
        $receipts = Denomination::where('denominatable_type', Voucher::class)
            ->whereHasMorph('denominatable', [Voucher::class], function ($query) {
                $query->where('type', 'receipt');
            })
            ->get()
            ->sum(fn ($d) => $d->total_amount - $d->change_given); // Net cash kept

        // Amount removed from box by payment vouchers and petty cash (disbursing funds)
        $payments = Denomination::where('denominatable_type', Voucher::class)
            ->whereHasMorph('denominatable', [Voucher::class], function ($query) {
                // Include empty (legacy), payment, and petty_cash
                $query->whereIn('type', ['payment', 'petty_cash'])->orWhereNull('type');
            })
            ->get()
            // Only add back the change if it was officially marked as received back in the box!
            ->sum(fn ($d) => $d->total_amount - ($d->is_change_received ? $d->change_given : 0));

        $totalIn = $replenished + $receipts;
        $balance = $totalIn - $payments;

        return [
            Stat::make('Cash In: Replenishments', 'AED ' . number_format($replenished, 2))
                ->description('Physical cash added via Float Replenishment vouchers')
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray'),
            Stat::make('Cash In: Receipt Vouchers', 'AED ' . number_format($receipts, 2))
                ->description('Physical cash received via RV (e.g. liquidation returns)')
                ->color('info')
                ->icon('heroicon-o-arrow-path'),
            Stat::make('Total Cash Out (Petty Cash)', 'AED ' . number_format($payments, 2))
                ->description('Physical cash disbursed from the safe via denomination tracking')
                ->color('danger')
                ->icon('heroicon-o-minus-circle'),
            Stat::make('TOTAL CASH IN BOX (Grand Total)', 'AED ' . number_format($balance, 2))
                ->description('Matches the ENDING BALANCE on your Daily Summary Report')
                ->color('primary')
                ->icon('heroicon-o-banknotes'),
        ];
    }
}
