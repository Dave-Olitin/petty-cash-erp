<?php

namespace App\Services;

use App\Models\AccountCode;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\VoucherItem;
use App\Models\PurchaseEntryLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeneralLedgerService
{
    /**
     * Centralized method to fetch all valid GL rows (Vouchers, JEs, Purchase Entries).
     * Normalizes all rows to a standard plain object.
     */
    public function getLedgerRows(?Carbon $from = null, ?Carbon $to = null, ?array $accountId = [], ?array $branch = [], ?array $basis = [], ?string $payee = null, string $dataSource = 'both'): Collection
    {
        $rows = collect();

        // 1. VoucherItem rows
        if ($dataSource !== 'je_only' && $dataSource !== 'purchases_only') {
            $voucherItems = VoucherItem::with(['voucher', 'voucher.journalEntries', 'accountCode'])
                ->whereHas('voucher', fn($q) => $q->where('status', 'paid'))
                ->when(!empty($accountId), function ($q) use ($accountId) {
                    $codes = AccountCode::whereIn('id', $accountId)->pluck('code');
                    $q->whereIn('account_code', $codes);
                })
                ->when(!empty($branch), fn($q) => $q->whereIn('branch_code', $branch))
                ->when(!empty($basis),  fn($q) => $q->whereHas('voucher', fn($v) => $v->whereIn('type', $basis)))
                ->when(!empty($payee),  fn($q) => $q->whereHas('voucher', fn($v) => $v->where('payee', 'like', '%' . $payee . '%')))
                ->when($from, fn($q) => $q->whereHas('voucher', fn($v) => $v->whereDate('created_at', '>=', $from)))
                ->when($to,   fn($q) => $q->whereHas('voucher', fn($v) => $v->whereDate('created_at', '<=', $to)))
                ->get()
                ->filter(fn($item) => $item->accountCode !== null)
                ->map(fn($item) => (object) [
                    'date'            => $item->voucher?->created_at,
                    'je_ref'          => $item->voucher?->journalEntries?->first()?->entry_no,
                    'je_id'           => $item->voucher?->journalEntries?->first()?->id,
                    'voucher_id'      => $item->voucher_id,
                    'voucher_number'  => $item->voucher?->voucher_number,
                    'voucher_type'    => $item->voucher?->type,
                    'voucher_amount'  => $item->voucher?->amount,
                    'purchase_entry_id'=> null,
                    'payee'           => $item->voucher?->payee,
                    'branch'          => $item->branch_code,
                    'debit'           => (float) $item->debit,
                    'credit'          => (float) $item->credit,
                    'source'          => 'voucher',
                    'is_info_only'    => (in_array($dataSource, ['both', 'all']) && $item->voucher?->journalEntries?->count() > 0),
                    'account_code_id' => $item->accountCode->id,
                    'account'         => $item->accountCode,
                    'running_balance' => 0.0,
                    'description'     => $item->description,
                ]);
            $rows = $rows->merge($voucherItems);
        }

        // 2. JournalEntryLine rows
        if ($dataSource !== 'vouchers_only' && $dataSource !== 'purchases_only') {
            $jeLines = JournalEntryLine::with(['journalEntry', 'journalEntry.vouchers', 'accountCode'])
                ->when(!empty($accountId), fn($q) => $q->whereIn('account_code_id', $accountId))
                ->when(!empty($branch),    fn($q) => $q->whereIn('branch', $branch))
                ->when(!empty($basis),     fn($q) => $q->whereHas('journalEntry.vouchers', fn($v) => $v->whereIn('type', $basis)))
                ->when(!empty($payee), fn($q) => $q->where(function ($sub) use ($payee) {
                    $sub->where('supplier_name', 'like', '%' . $payee . '%')
                        ->orWhereHas('journalEntry.vouchers', fn($v) => $v->where('payee', 'like', '%' . $payee . '%'));
                }))
                ->when($from, fn($q) => $q->whereHas('journalEntry', fn($j) => $j->whereDate('date', '>=', $from)))
                ->when($to,   fn($q) => $q->whereHas('journalEntry', fn($j) => $j->whereDate('date', '<=', $to)))
                ->get()
                ->filter(fn($line) => $line->accountCode !== null)
                ->map(fn($line) => (object) [
                    'date'            => $line->journalEntry?->date,
                    'je_ref'          => $line->journalEntry?->entry_no,
                    'je_id'           => $line->journal_entry_id,
                    'voucher_id'      => $line->journalEntry?->vouchers->first()?->id,
                    'voucher_number'  => $line->journalEntry?->vouchers->first()?->voucher_number,
                    'voucher_type'    => $line->journalEntry?->vouchers->first()?->type,
                    'voucher_amount'  => $line->journalEntry?->vouchers->first()?->amount,
                    'purchase_entry_id'=> null,
                    'payee'           => $line->supplier_name ?: ($line->journalEntry?->vouchers->first()?->payee ?? null),
                    'branch'          => $line->branch,
                    'debit'           => (float) $line->debit,
                    'credit'          => (float) $line->credit,
                    'source'          => 'je',
                    'is_info_only'    => false,
                    'account_code_id' => $line->account_code_id,
                    'account'         => $line->accountCode,
                    'running_balance' => 0.0,
                    'description'     => $line->remarks,
                ]);
            $rows = $rows->merge($jeLines);
        }

        // 3. PurchaseEntryLine rows (and Synthetic AP)
        if (in_array($dataSource, ['both', 'all', 'purchases_only'])) {
            // Find default AP account (e.g. Accounts Payable Trade)
            $apAccount = AccountCode::where('name', 'LIKE', '%ACCOUNTS PAYABLE%')
                ->where('name', 'NOT LIKE', '%NON TRADE%')
                ->first();

            $peLinesQuery = PurchaseEntryLine::with(['purchaseEntry.taxRegistration', 'debitAccount'])
                ->whereHas('purchaseEntry')
                ->when(!empty($branch), fn($q) => $q->whereIn('branch', $branch))
                ->when(!empty($payee), fn($q) => $q->whereHas('purchaseEntry.taxRegistration', fn($s) => $s->where('name', 'like', '%' . $payee . '%')))
                ->when($from, fn($q) => $q->whereHas('purchaseEntry', fn($p) => $p->whereDate('date', '>=', $from)))
                ->when($to,   fn($q) => $q->whereHas('purchaseEntry', fn($p) => $p->whereDate('date', '<=', $to)));

            $peLines = $peLinesQuery->get();

            $mappedPe = collect();
            $syntheticAp = collect();

            foreach ($peLines as $line) {
                $isReturn = $line->purchaseEntry->entry_type === 'return';
                $parentPayee = $line->purchaseEntry?->taxRegistration?->name ?? $line->purchaseEntry?->supplier_name;

                // Direct Line
                if (empty($accountId) || in_array($line->debit_account_id, $accountId)) {
                    if ($line->debitAccount !== null) {
                        $mappedPe->push((object) [
                            'date'            => $line->purchaseEntry?->date,
                            'je_ref'          => $line->purchaseEntry?->entry_no,
                            'je_id'           => null,
                            'voucher_id'      => null,
                            'voucher_number'  => null,
                            'voucher_type'    => null,
                            'voucher_amount'  => null,
                            'purchase_entry_id'=> $line->purchaseEntry?->id,
                            'payee'           => $parentPayee,
                            'branch'          => $line->branch,
                            'debit'           => (float) $line->debit,
                            'credit'          => (float) $line->credit,
                            'source'          => 'purchase_entry',
                            'is_info_only'    => false,
                            'account_code_id' => $line->debit_account_id,
                            'account'         => $line->debitAccount,
                            'running_balance' => 0.0,
                            'description'     => $line->description ?: 'Purchase Entry: ' . $line->purchaseEntry?->entry_no,
                        ]);
                    }
                }

                // Synthetic AP Line
                if ($apAccount !== null && (empty($accountId) || in_array($apAccount->id, $accountId))) {
                    $syntheticDr = 0;
                    $syntheticCr = 0;

                    if ($isReturn) {
                        // Return: we reverse the DR/CR because a Return reduces liability (debit AP).
                        $syntheticDr = (float) $line->credit;
                        $syntheticCr = (float) $line->debit;
                    } else {
                        // Purchase: standard offset (credit AP for debited expenses).
                        $syntheticDr = (float) $line->credit;
                        $syntheticCr = (float) $line->debit;
                    }

                    if ($syntheticDr > 0 || $syntheticCr > 0) {
                        $syntheticAp->push((object) [
                            'date'            => $line->purchaseEntry?->date,
                            'je_ref'          => $line->purchaseEntry?->entry_no,
                            'je_id'           => null,
                            'voucher_id'      => null,
                            'voucher_number'  => null,
                            'voucher_type'    => null,
                            'voucher_amount'  => null,
                            'purchase_entry_id'=> $line->purchaseEntry?->id,
                            'payee'           => $parentPayee,
                            'branch'          => $line->branch,
                            'debit'           => $syntheticDr,
                            'credit'          => $syntheticCr,
                            'source'          => 'purchase_entry',
                            'is_info_only'    => false,
                            'account_code_id' => $apAccount->id,
                            'account'         => $apAccount,
                            'running_balance' => 0.0,
                            'description'     => 'AP Offset: ' . ($line->description ?: $line->purchaseEntry?->entry_no),
                        ]);
                    }
                }
            }

            $rows = $rows->merge($mappedPe)->merge($syntheticAp);
        }

        return $rows;
    }

    /**
     * Get grouped ledger format for GL pages.
     */
    public function getLedgerGroups(?Carbon $from = null, ?Carbon $to = null, ?array $accountId = [], ?array $branch = [], ?array $basis = [], ?string $payee = null, string $dataSource = 'both'): Collection
    {
        $rows = $this->getLedgerRows($from, $to, $accountId, $branch, $basis, $payee, $dataSource);

        return $rows->sortBy(fn($r) => [
            $r->account?->code ?? 'zzz',
            optional($r->date)->timestamp ?? 0,
        ])
        ->groupBy(fn($r) => $r->account_code_id ?? 0)
        ->filter(fn($group, $key) => $key > 0)
        ->map(function (Collection $group) {
            $account        = $group->first()->account;
            $runningBalance = 0.0;

            $rows = $group->map(function ($line) use ($account, &$runningBalance) {
                $dr = !empty($line->is_info_only) ? 0.0 : $line->debit;
                $cr = !empty($line->is_info_only) ? 0.0 : $line->credit;

                if ($account && $account->normal_balance === 'debit') {
                    $runningBalance += ($dr - $cr);
                } else {
                    $runningBalance += ($cr - $dr);
                }

                $line->running_balance = $runningBalance;
                return $line;
            });

            return [
                'account'         => $account,
                'rows'            => $rows,
                'total_debit'     => $group->reject(fn($r) => !empty($r->is_info_only))->sum('debit'),
                'total_credit'    => $group->reject(fn($r) => !empty($r->is_info_only))->sum('credit'),
                'closing_balance' => $runningBalance,
            ];
        })
        ->sortBy(fn($g) => $g['account']?->code);
    }

    /**
     * Get the balances for all accounts within a specific period.
     * Consolidates data from Journal Entries, Vouchers, and Purchase Entries.
     */
    public function getTrialBalance(?Carbon $from = null, ?Carbon $to = null, ?string $branch = null): Collection
    {
        $branchFilter = $branch ? [$branch] : [];
        $groups = $this->getLedgerGroups($from, $to, [], $branchFilter, [], null, 'all');

        $trialBalance = collect();

        foreach ($groups as $group) {
            $account = $group['account'];
            
            // Add sums directly onto the account object
            $account->total_debit = $group['total_debit'];
            $account->total_credit = $group['total_credit'];
            
            // Calculate net balance based on normal balance type
            if ($account->normal_balance === 'debit') {
                $account->net_balance = $account->total_debit - $account->total_credit;
            } else {
                $account->net_balance = $account->total_credit - $account->total_debit;
            }

            $trialBalance->push($account);
        }

        return $trialBalance->sortBy(fn($account) => $account->type->sortOrder());
    }

    /**
     * Get detailed ledger for a specific account.
     */
    public function getAccountLedger(AccountCode $account, ?Carbon $from = null, ?Carbon $to = null, ?string $branch = null): Collection
    {
        $branchFilter = $branch ? [$branch] : [];
        $groups = $this->getLedgerGroups($from, $to, [$account->id], $branchFilter, [], null, 'all');

        if ($groups->has($account->id)) {
            return $groups->get($account->id)['rows'];
        }

        return collect();
    }
}
