<?php

namespace App\Services;

use App\Models\PeriodClose;
use App\Models\Voucher;
use App\Models\PurchaseEntry;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Carbon;

class PeriodCloseService
{
    /**
     * Aggregate all financial figures for the period window and update the record.
     */
    public function aggregate(PeriodClose $period): PeriodClose
    {
        $start = $period->period_start;
        $end   = $period->period_end;

        // ── Vouchers ──────────────────────────────────────────────────────────
        $vouchers = Voucher::whereBetween('created_at', [$start->startOfDay(), $end->copy()->endOfDay()])
            ->where('status', 'paid')
            ->get();

        $totalVouchersPaid     = $vouchers->sum('amount');
        $totalPettyCash        = $vouchers->where('type', 'petty_cash')->sum('amount');
        $voucherCount          = $vouchers->count();

        // ── Purchase Entries ──────────────────────────────────────────────────
        $purchaseEntries = PurchaseEntry::whereBetween('date', [$start, $end])->get();

        $totalApBilled   = $purchaseEntries->where('entry_type', 'bill')->sum('grand_total');
        $totalApPaid     = $purchaseEntries->sum('amount_paid');
        $totalApBalance  = $purchaseEntries->sum('balance_due');
        $peCount         = $purchaseEntries->count();

        // ── Journal Entries ───────────────────────────────────────────────────
        $journalIds = JournalEntry::whereBetween('date', [$start, $end])->pluck('id');

        $totalJournalDr = \App\Models\JournalEntryLine::whereIn('journal_entry_id', $journalIds)->sum('debit');
        $totalJournalCr = \App\Models\JournalEntryLine::whereIn('journal_entry_id', $journalIds)->sum('credit');
        $jeCount        = $journalIds->count();

        $period->update([
            'total_vouchers_paid'        => $totalVouchersPaid,
            'total_petty_cash_disbursed' => $totalPettyCash,
            'total_ap_billed'            => $totalApBilled,
            'total_ap_paid'              => $totalApPaid,
            'total_ap_balance'           => $totalApBalance,
            'total_journal_dr'           => $totalJournalDr,
            'total_journal_cr'           => $totalJournalCr,
            'voucher_count'              => $voucherCount,
            'purchase_entry_count'       => $peCount,
            'journal_entry_count'        => $jeCount,
        ]);

        return $period->fresh();
    }

    /**
     * Close the period — marks it as closed and records who closed it.
     */
    public function close(PeriodClose $period, User $user): PeriodClose
    {
        // Re-aggregate latest figures before closing
        $this->aggregate($period);

        $period->update([
            'status'    => 'closed',
            'closed_by' => $user->id,
            'closed_at' => now(),
        ]);

        return $period->fresh();
    }

    /**
     * Re-open a closed period (Super Admin emergency use).
     */
    public function reopen(PeriodClose $period): PeriodClose
    {
        $period->update([
            'status'    => 'draft',
            'closed_by' => null,
            'closed_at' => null,
        ]);

        return $period->fresh();
    }
}
