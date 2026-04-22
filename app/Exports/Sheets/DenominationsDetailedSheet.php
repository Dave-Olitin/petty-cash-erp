<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DenominationsDetailedSheet implements FromCollection, WithTitle, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $query;

    public function __construct($query, $dateFrom, $dateTo)
    {
        $this->query = clone $query;
    }

    public function collection()
    {
        // Load vouchers that have denominations
        return $this->query->with('denominations')->whereHas('denominations')->get();
    }

    public function map($voucher): array
    {
        $d = $voucher->denominations;
        return [
            $voucher->created_at->format('Y-m-d'),
            $voucher->voucher_number,
            $voucher->type === 'payment' ? 'Payment' : ($voucher->type === 'petty_cash' ? 'Petty Cash' : 'Receipt'),
            $voucher->payee,
            $d->bill_1000 ?? 0,
            $d->bill_500 ?? 0,
            $d->bill_200 ?? 0,
            $d->bill_100 ?? 0,
            $d->bill_50 ?? 0,
            $d->bill_20 ?? 0,
            $d->bill_10 ?? 0,
            $d->bill_5 ?? 0,
            $d->coin_1 ?? 0,
            $d->coin_0_50 ?? 0,
            $d->coin_0_25 ?? 0,
            number_format((float)$voucher->amount, 2),
        ];
    }

    public function headings(): array
    {
        return [
            'Date',
            'Voucher #',
            'Type',
            'Payee',
            '1000s (Qty)',
            '500s (Qty)',
            '200s (Qty)',
            '100s (Qty)',
            '50s (Qty)',
            '20s (Qty)',
            '10s (Qty)',
            '5s (Qty)',
            '1s (Qty)',
            '0.50s (Qty)',
            '0.25s (Qty)',
            'Total (AED)',
        ];
    }

    public function title(): string
    {
        return 'Detailed Breakdown';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
