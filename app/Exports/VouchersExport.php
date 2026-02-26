<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VouchersExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Voucher #',
            'Type',
            'Payee',
            'Category',
            'Requester',
            'Amount (AED)',
        ];
    }

    public function map($voucher): array
    {
        return [
            $voucher->created_at->format('Y-m-d'),
            $voucher->voucher_number,
            $voucher->type === 'petty_cash' ? 'Petty Cash' : 'Payment',
            $voucher->payee,
            $voucher->category?->name ?? 'N/A',
            $voucher->user?->name ?? 'N/A',
            number_format($voucher->amount, 2, '.', ''),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
