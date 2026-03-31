<?php

namespace App\Exports;

use App\Models\Voucher;
use App\Models\AccountCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VouchersExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $query;
    protected $accountCodes;

    public function __construct(Builder $query)
    {
        $this->query = $query;
        $this->accountCodes = AccountCode::pluck('name', 'code')->toArray();
    }

    public function collection()
    {
        // Get all vouchers that match the filtered query with their items
        $vouchers = $this->query->with(['items', 'template', 'category'])->get();
        $exportRows = new Collection();
        
        foreach ($vouchers as $voucher) {
            if ($voucher->items->isEmpty()) {
                // If a voucher somehow has no items, export a single row for it.
                $row = (object)[
                    'is_voucher_only' => true,
                    'voucher' => $voucher,
                ];
                $exportRows->push($row);
            } else {
                foreach ($voucher->items as $item) {
                    $item->voucher = $voucher; // attach parent voucher for mapping
                    $exportRows->push($item);
                }
            }
        }
        
        return $exportRows;
    }

    public function headings(): array
    {
        return [
            'REF',
            'SOURCE',
            'DATE',
            'TRN',
            'DEPARTMENT',
            'TRANS CAT',
            'REMARKS',
            'PAYEE',
            'P.O. NO.',
            'INVOICE NO.',
            'DESCRIPTION',
            'ACCOUNT CODE',
            'ACCOUNT NAME',
            'DEBIT',
            'AMOUNT',
            'BRANCH',
            '',
            'CREDIT',
            'AMOUNT',
            'BRANCH',
        ];
    }

    public function map($row): array
    {
        // Handle vouchers without items
        if (isset($row->is_voucher_only)) {
            $voucher = $row->voucher;
            return [
                $voucher->voucher_number ?? '',
                $voucher->type === 'petty_cash' ? 'Petty Cash' : 'Payment',
                $voucher->created_at ? $voucher->created_at->format('Y-m-d') : '',
                '', // TRN
                $voucher->department ?? '', // DEPARTMENT
                $voucher->category ? $voucher->category->name : '', // TRANS CAT
                $voucher->transaction_summary ?? '', // REMARKS
                $voucher->payee ?? '',
                '', // P.O. NO.
                $voucher->cheque_no ?? '', // INVOICE NO.
                $voucher->description ?? '', // DESCRIPTION
                '', // ACCOUNT CODE
                '', // ACCOUNT NAME
                '', // DEBIT
                number_format((float) $voucher->amount, 2, '.', ''), // AMOUNT
                '', // BRANCH
                '', // EMPTY
                '', // CREDIT
                '', // AMOUNT
                '', // BRANCH
            ];
        }

        $voucher = $row->voucher;
        $isDebit = $row->entry_type === 'debit';
        
        // Grab account name from pre-loaded AccountCodes or fallback to item description
        $accountName = $this->accountCodes[$row->account_code] ?? '';

        return [
            $voucher->voucher_number ?? '',
            $voucher->type === 'petty_cash' ? 'Petty Cash' : 'Payment',
            $voucher->created_at ? $voucher->created_at->format('Y-m-d') : '',
            $voucher->template ? $voucher->template->trn : '', // TRN
            $voucher->department ?? '', // DEPARTMENT
            $voucher->category ? $voucher->category->name : '', // TRANS CAT
            $voucher->transaction_summary ?? '', // REMARKS
            $voucher->payee ?? '',
            '', // P.O. NO.
            '', // INVOICE NO.
            $row->description ?? '', // DESCRIPTION
            $row->account_code ?? '', // ACCOUNT CODE
            $accountName, // ACCOUNT NAME
            '', // DEBIT
            $isDebit ? number_format((float) $row->amount, 2, '.', '') : '', // AMOUNT
            $isDebit ? ($row->branch_code ?? '') : '', // BRANCH
            '', // BLANK
            '', // CREDIT
            !$isDebit ? number_format((float) $row->amount, 2, '.', '') : '', // AMOUNT
            !$isDebit ? ($row->branch_code ?? '') : '', // BRANCH
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
