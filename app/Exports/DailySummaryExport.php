<?php

namespace App\Exports;

use App\Models\Voucher;
use App\Models\FloatReplenishment;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Carbon\Carbon;

class DailySummaryExport implements FromView, ShouldAutoSize
{
    public $date;

    public function __construct($date)
    {
        $this->date = $date;
    }

    public function view(): View
    {
        $targetDateEndOfDay = Carbon::parse($this->date)->endOfDay();
        $targetDateStartOfDay = Carbon::parse($this->date)->startOfDay();

        // 1. Calculate Beginning Balance (Everything BEFORE this date exactly as computed)
        $pastReplenishments = FloatReplenishment::where('date', '<', $targetDateStartOfDay)->sum('amount');
        $pastReceipts = Voucher::where('type', 'receipt')->where('status', 'paid')->where('updated_at', '<', $targetDateStartOfDay)->sum('amount');
        $pastPayments = Voucher::where('type', 'petty_cash')->where('status', 'paid')->where('updated_at', '<', $targetDateStartOfDay)->sum('amount');
        $beginningBalance = $pastReplenishments + $pastReceipts - $pastPayments;

        // 2. Gather Today's Transactions (Only Petty Cash and Receipts, ignore Bank Payments)
        $vouchers = Voucher::with(['user', 'category'])->whereIn('type', ['petty_cash', 'receipt'])->whereDate('updated_at', $this->date)->where('status', 'paid')->get()->map(function($v) {
            return (object)[
                'date' => $v->updated_at,
                'reference' => $v->voucher_number ?? ('VOUCHER-'.$v->id),
                'department' => $v->department ?? '-',
                'maker' => $v->user->name ?? '-',
                'category' => $v->category->name ?? '-',
                'payee' => $v->payee ?? '-',
                'cheque_no'  => $v->cheque_no ?? '-',
                'type'       => $v->type,
                'amount'     => $v->amount,
                'attachments' => collect($v->attachment_paths ?? [])
                    ->map(fn ($p) => url('storage/' . implode('/', array_map('rawurlencode', explode('/', $p)))))
                    ->join(', '),
            ];
        });

        $replenishments = FloatReplenishment::with('creator')->whereDate('date', $this->date)->get()->map(function($r) {
            return (object)[
                'date' => Carbon::parse($r->date),
                'reference' => $r->reference ?? ('FUND REQUEST-'.$r->id),
                'department' => 'ACCOUNTS', // Default per screenshot
                'maker' => $r->creator->name ?? 'ACCOUNTS-JUNA',
                'category' => 'Float Replenishment',
                'payee' => 'FAB-SB',
                'cheque_no'   => '-',
                'type'        => 'Replenishment',
                'amount'      => $r->amount,
                'attachments' => collect($r->attachment_paths ?? [])
                    ->map(fn ($p) => url('storage/' . implode('/', array_map('rawurlencode', explode('/', $p)))))
                    ->join(', '),
            ];
        });

        // Merge and sort
        $transactions = $vouchers->concat($replenishments)->sortBy('date');

        // 3. Compute Current Box Denominations using exact column names from the denominations table
        $denominations = [];
        $denominationMap = [
            '1000' => 'bill_1000',
            '500'  => 'bill_500',
            '200'  => 'bill_200',
            '100'  => 'bill_100',
            '50'   => 'bill_50',
            '20'   => 'bill_20',
            '10'   => 'bill_10',
            '5'    => 'bill_5',
            '1'    => 'coin_1',
            '0.50' => 'coin_0_50',
            '0.25' => 'coin_0_25',
        ];

        foreach ($denominationMap as $displayVal => $column) {
            $replenishmentsIn = \App\Models\Denomination::where('denominatable_type', \App\Models\FloatReplenishment::class)
                ->where('created_at', '<=', $targetDateEndOfDay)
                ->sum($column);

            $receiptsIn = \App\Models\Denomination::where('denominatable_type', \App\Models\Voucher::class)
                ->whereHasMorph('denominatable', [\App\Models\Voucher::class], function($q) use ($targetDateEndOfDay) {
                    $q->where('type', 'receipt')->where('status', 'paid')->where('updated_at', '<=', $targetDateEndOfDay);
                })
                ->sum($column);

            $paymentsOut = \App\Models\Denomination::where('denominatable_type', \App\Models\Voucher::class)
                ->whereHasMorph('denominatable', [\App\Models\Voucher::class], function($q) use ($targetDateEndOfDay) {
                    $q->where('type', 'petty_cash')->where('status', 'paid')->where('updated_at', '<=', $targetDateEndOfDay);
                })
                ->sum($column);

            $denominations[$displayVal] = $replenishmentsIn + $receiptsIn - $paymentsOut;
        }

        // Get the detailed records of unallocated change
        $unallocatedChangeRecords = \App\Models\Denomination::with('denominatable')
            ->where('denominatable_type', \App\Models\Voucher::class)
            ->whereHasMorph('denominatable', [\App\Models\Voucher::class], function($q) use ($targetDateEndOfDay) {
                $q->whereIn('type', ['petty_cash', 'receipt'])->where('status', 'paid')->where('updated_at', '<=', $targetDateEndOfDay);
            })
            ->where('is_change_received', true)
            ->where('change_given', '>', 0)
            ->get();

        $unallocatedChange = $unallocatedChangeRecords->sum('change_given');

        return view('exports.daily-summary', [
            'date' => Carbon::parse($this->date)->format('d F, Y'),
            'beginningBalance' => $beginningBalance,
            'transactions' => $transactions,
            'denominations' => $denominations,
            'unallocatedChange' => $unallocatedChange,
            'unallocatedChangeRecords' => $unallocatedChangeRecords,
        ]);
    }
}
