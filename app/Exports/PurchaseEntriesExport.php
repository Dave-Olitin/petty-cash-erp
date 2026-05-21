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
        // Get all purchase entries with their lines, accounts, tax registration, and custodian user
        $entries = $this->query->with(['lines.debitAccount', 'taxRegistration', 'user'])->get();
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
            'SUPPLIER NAME',
            'SUPPLIER TRN',
            'CUSTODIAN',
            'PO NUMBER',
            'INVOICE NO',
            'CURRENCY',
            'ITEM ACCOUNT CODE',
            'ITEM ACCOUNT NAME',
            'ITEM DESCRIPTION',
            'BRANCH',
            'LINE TOTAL',
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
                $entry->supplier_name,
                $entry->supplier_trn,
                $entry->user?->name ?? 'System',
                $entry->po_number,
                $entry->invoice_no,
                $entry->currency,
                '', // Account Code
                '', // Account Name
                '', // Description
                '', // Branch
                '', // Line Total
                $entry->grand_total,
            ];
        }

        $entry = $row->parent_entry;
        return [
            $entry->entry_no,
            $entry->date ? $entry->date->format('Y-m-d') : '',
            $entry->due_date ? $entry->due_date->format('Y-m-d') : '',
            $entry->entity,
            $entry->supplier_name,
            $entry->supplier_trn,
            $entry->user?->name ?? 'System',
            $entry->po_number,
            $entry->invoice_no,
            $entry->currency,
            $row->debitAccount?->code,
            $row->debitAccount?->name,
            $row->description,
            $row->branch,
            $row->total,
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
