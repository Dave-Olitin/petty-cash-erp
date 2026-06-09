<?php

namespace App\Exports;

use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class JournalEntriesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function collection()
    {
        // Get all journal entries with their lines and account codes
        $entries = $this->query->with(['lines.accountCode'])->get();
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
            'DATE',
            'PO NUMBER',
            'INVOICE NO',
            'REFERENCE',
            'CURRENCY',
            'ACCOUNT CODE',
            'ACCOUNT NAME',
            'BRANCH',
            'SUPPLIER NAME',
            'TRN',
            'LINE REMARKS',
            'DEBIT',
            'CREDIT',
            'TOTAL DEBIT',
            'TOTAL CREDIT',
        ];
    }

    public function map($row): array
    {
        if (isset($row->is_entry_only)) {
            $entry = $row->entry;
            return [
                $entry->entry_no,
                $entry->date ? $entry->date->format('Y-m-d') : '',
                $entry->po_number,
                $entry->invoice_no,
                $entry->reference,
                $entry->currency,
                '', // Account Code
                '', // Account Name
                '', // Branch
                '', // Supplier Name
                '', // TRN
                '', // Line Remarks
                '', // Debit
                '', // Credit
                $entry->total_debit,
                $entry->total_credit,
            ];
        }

        $entry = $row->parent_entry;
        return [
            $entry->entry_no,
            $row->date ? $row->date->format('Y-m-d') : ($entry->date ? $entry->date->format('Y-m-d') : ''),
            $entry->po_number,
            $row->invoice_no ?? $entry->invoice_no,
            $entry->reference,
            $entry->currency,
            $row->accountCode?->code,
            $row->accountCode?->name,
            $row->branch,
            $row->supplier_name,
            $row->trn,
            $row->remarks,
            $row->debit,
            $row->credit,
            $entry->total_debit,
            $entry->total_credit,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
