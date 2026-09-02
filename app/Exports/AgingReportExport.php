<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class AgingReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle, WithColumnFormatting
{
    protected array $data;

    public function __construct(array $agingData)
    {
        $this->data = $agingData;
    }

    public function title(): string
    {
        return 'AP Aging ' . ($this->data['as_of_date'] ?? '');
    }

    public function collection(): Collection
    {
        $rows = collect();

        foreach ($this->data['rows'] as $supplierRow) {
            foreach ($supplierRow['entries'] as $entry) {
                $rows->push((object) array_merge([
                    'supplier_name' => $supplierRow['supplier_name'],
                    'supplier_trn'  => $supplierRow['supplier_trn']
                ], $entry));
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'SUPPLIER',
            'TRN',
            'ENTRY NO.',
            'INVOICE #',
            'BILL DATE',
            'DUE DATE',
            'DAYS OVERDUE',
            'BILL TOTAL (AED)',
            'AMOUNT PAID (AED)',
            'BALANCE DUE (AED)',
            'STATUS',
            'AGING BUCKET',
        ];
    }

    public function map($row): array
    {
        $isPaid = !empty($row->is_paid) || ($row->balance_due <= 0 && $row->payment_status === 'paid');
        $days = (int) $row->days_overdue;

        if ($isPaid) {
            $daysDisplay = 'Settled';
            $bucket = 'Settled';
        } else {
            $daysDisplay = $days > 0 ? $days : 'Current';
            $bucket = match(true) {
                $days <= 0  => 'Current',
                $days <= 30 => '1–30 Days',
                $days <= 60 => '31–60 Days',
                $days <= 90 => '61–90 Days',
                default     => '90+ Days',
            };
        }

        return [
            $row->supplier_name,
            $row->supplier_trn,
            $row->entry_no,
            $row->invoice_no,
            $row->date,
            $row->due_date,
            $daysDisplay,
            (float) $row->grand_total,
            (float) $row->amount_paid,
            (float) $row->balance_due,
            ucfirst($row->payment_status),
            $bucket,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'H' => '#,##0.00',
            'I' => '#,##0.00',
            'J' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E3A5F']],
            ],
        ];
    }
}
