<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DenominationsSummarySheet implements FromCollection, WithTitle, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $query;
    protected $dateFrom;
    protected $dateTo;

    public function __construct($query, $dateFrom, $dateTo)
    {
        $this->query = clone $query;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function collection()
    {
        // Get the list of voucher IDs matching the base query
        $voucherIds = $this->query->pluck('id');

        $summations = \App\Models\Denomination::whereIn('denominatable_id', $voucherIds)
            ->where('denominatable_type', 'App\Models\Voucher')
            ->selectRaw('
                SUM(total_amount) as grand_total,
                SUM(bill_1000) as sum_1000,
                SUM(bill_500) as sum_500,
                SUM(bill_200) as sum_200,
                SUM(bill_100) as sum_100,
                SUM(bill_50) as sum_50,
                SUM(bill_20) as sum_20,
                SUM(bill_10) as sum_10,
                SUM(bill_5) as sum_5,
                SUM(coin_1) as sum_1,
                SUM(coin_0_50) as sum_0_50,
                SUM(coin_0_25) as sum_0_25
            ')->first();

        return collect([
            [
                'AED 1,000' => $summations->sum_1000 ?? 0,
                'AED 500'   => $summations->sum_500 ?? 0,
                'AED 200'   => $summations->sum_200 ?? 0,
                'AED 100'   => $summations->sum_100 ?? 0,
                'AED 50'    => $summations->sum_50 ?? 0,
                'AED 20'    => $summations->sum_20 ?? 0,
                'AED 10'    => $summations->sum_10 ?? 0,
                'AED 5'     => $summations->sum_5 ?? 0,
                'AED 1'     => $summations->sum_1 ?? 0,
                'AED 0.50'  => $summations->sum_0_50 ?? 0,
                'AED 0.25'  => $summations->sum_0_25 ?? 0,
                'Total Amount' => number_format($summations->grand_total ?? 0, 2),
            ]
        ]);
    }

    public function headings(): array
    {
        return [
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
            'Grand Total (AED)',
        ];
    }

    public function title(): string
    {
        return 'Grand Summary';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
