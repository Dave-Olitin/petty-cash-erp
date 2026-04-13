<?php

namespace App\Exports;

use App\Models\PurchaseEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PurchaseEntriesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function collection()
    {
        // Get all purchase entries with their lines, accounts and tax registration
        $entries = $this->query->with(['lines.debitAccount', 'taxRegistration'])->get();
        $exportRows = new Collection();
        
        foreach ($entries as $entry) {
            if ($entry->lines->isEmpty()) {
                $row = (object)[
                    'is_entry_only' => true,
                    'entry' => $entry,
                ];
                $exportRows->push($row);
            } else {
                foreach ($entry->lines as $line) {
                    $line->parent_entry = $entry; // attach parent for mapping
                    $exportRows->push($line);
                }
            }
        }
        
        return $exportRows;
    }

    public function headings(): array
    {
        return [
            'ENTRY NO',
            'BILL DATE',
            'DUE DATE',
            'ENTITY',
            'BRANCH',
            'SUPPLIER NAME',
            'SUPPLIER TRN',
            'BILL NO',
            'INVOICE NO',
            'CURRENCY',
            'PRICE TYPE',
            'ITEM DESCRIPTION',
            'ITEM ACCOUNT CODE',
            'ITEM ACCOUNT NAME',
            'QTY',
            'UNIT PRICE',
            'TAX %',
            'VAT AMOUNT',
            'LINE TOTAL',
            'BASE AMOUNT TOTAL',
            'TOTAL VAT',
            'GRAND TOTAL',
        ];
    }

    public function map($row): array
    {
        if (isset($row->is_entry_only)) {
            $entry = $row->entry;
            return [
                $entry->entry_no,
                $entry->date ? $entry->date->format('Y-m-d') : '',
                $entry->due_date ? $entry->due_date->format('Y-m-d') : '',
                $entry->entity,
                $entry->branch,
                $entry->supplier_name,
                $entry->supplier_trn,
                $entry->bill_no,
                $entry->invoice_no,
                $entry->currency,
                $entry->price_type,
                '', // Description
                '', // Account Code
                '', // Account Name
                '', // Qty
                '', // Unit Price
                '', // Tax %
                '', // VAT Amount
                '', // Line Total
                $entry->total_amount,
                $entry->total_vat,
                $entry->grand_total,
            ];
        }

        $entry = $row->parent_entry;
        return [
            $entry->entry_no,
            $entry->date ? $entry->date->format('Y-m-d') : '',
            $entry->due_date ? $entry->due_date->format('Y-m-d') : '',
            $entry->entity,
            $entry->branch,
            $entry->supplier_name,
            $entry->supplier_trn,
            $entry->bill_no,
            $entry->invoice_no,
            $entry->currency,
            $entry->price_type,
            $row->description,
            $row->debitAccount?->code,
            $row->debitAccount?->name,
            $row->qty,
            $row->unit_price,
            $row->tax_percentage . '%',
            $row->tax_amount,
            $row->total,
            $entry->total_amount,
            $entry->total_vat,
            $entry->grand_total,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
