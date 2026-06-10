<?php

namespace App\Services;

use App\Models\AccountCode;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\VoucherItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeneralLedgerService
{
    /**
     * Get the balances for all accounts within a specific period.
     * Consolidates data from Journal Entries.
     * 
     * Note: We are using AED only as requested.
     */
    public function getTrialBalance(?Carbon $from = null, ?Carbon $to = null, ?string $branch = null): Collection
    {
        $query = AccountCode::query()
            ->select('account_codes.*')
            ->withSum(['voucherItems as total_debit' => function ($q) use ($from, $to, $branch) {
                $q->whereHas('voucher', fn($v) => $v->where('status', 'paid'))
                  ->when($from, fn($query) => $query->whereHas('voucher', fn($v) => $v->whereDate('created_at', '>=', $from)))
                  ->when($to, fn($query) => $query->whereHas('voucher', fn($v) => $v->whereDate('created_at', '<=', $to)))
                  ->when($branch, fn($query) => $query->where('branch_code', $branch));
            }], 'debit')
            ->withSum(['voucherItems as total_credit' => function ($q) use ($from, $to, $branch) {
                $q->whereHas('voucher', fn($v) => $v->where('status', 'paid'))
                  ->when($from, fn($query) => $query->whereHas('voucher', fn($v) => $v->whereDate('created_at', '>=', $from)))
                  ->when($to, fn($query) => $query->whereHas('voucher', fn($v) => $v->whereDate('created_at', '<=', $to)))
                  ->when($branch, fn($query) => $query->where('branch_code', $branch));
            }], 'credit');

        return $query->get()->map(function ($account) {
            $debit = (float) $account->total_debit;
            $credit = (float) $account->total_credit;
            
            // Calculate net balance based on normal balance type
            if ($account->normal_balance === 'debit') {
                $account->net_balance = $debit - $credit;
            } else {
                $account->net_balance = $credit - $debit;
            }
            
            return $account;
        })->sortBy(fn($account) => $account->type->sortOrder());
    }

    /**
     * Get detailed ledger for a specific account.
     */
    public function getAccountLedger(AccountCode $account, ?Carbon $from = null, ?Carbon $to = null, ?string $branch = null): Collection
    {
        return VoucherItem::query()
            ->where('account_code', $account->code)
            ->whereHas('voucher', fn($v) => $v->where('status', 'paid'))
            ->with(['voucher', 'voucher.journalEntries'])
            ->when($from, fn($q) => $q->whereHas('voucher', fn($v) => $v->whereDate('created_at', '>=', $from)))
            ->when($to, fn($q) => $q->whereHas('voucher', fn($v) => $v->whereDate('created_at', '<=', $to)))
            ->when($branch, fn($q) => $q->where('branch_code', $branch))
            ->get()
            ->sortBy(fn($line) => $line->voucher->created_at);
    }
}
