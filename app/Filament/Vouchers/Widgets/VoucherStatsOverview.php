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
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin']);
    }

    protected function getStats(): array
    {
        $user = auth()->user();

        // Scope queries based on role
        $query = Voucher::query();
        if (! $user->isHeadOffice() && ! $user->hasAnyRole(['Accountant', 'Approver'])) {
            $query->where('user_id', $user->id);
        }

        $totalApproved = (clone $query)->where('status', 'approved')->count();
        $totalPending = (clone $query)->whereIn('status', ['pending_checker', 'pending_approver'])->count();
        $totalPaidAmount = (clone $query)->where('status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        return [
            Stat::make('Pending Approvals', $totalPending)
                ->description('Vouchers awaiting check/approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
                
            Stat::make('Ready to Pay', $totalApproved)
                ->description('Vouchers approved but not paid')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
                
            Stat::make('Total Paid This Month', 'AED ' . Number::format($totalPaidAmount, 2))
                ->description('Total amount paid out this month')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),
        ];
    }
}
