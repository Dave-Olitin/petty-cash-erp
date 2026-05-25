<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class GeneralLedgerExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected Collection $ledgerGroups;
    protected ?Carbon $from;
    protected ?Carbon $to;
    protected ?string $branch;

    public function __construct(Collection $ledgerGroups, ?Carbon $from, ?Carbon $to, ?string $branch)
    {
        $this->ledgerGroups = $ledgerGroups;
        $this->from         = $from;
        $this->to           = $to;
        $this->branch       = $branch;
    }

    /**
     * Flatten all groups into a single collection with header rows per account.
     */
    public function collection(): Collection
    {
        $flat = collect();

        foreach ($this->ledgerGroups as $group) {
            $account = $group['account'];

            // Account header row
            $flat->push([
                '__type' => 'header',
                'code'   => $account?->code,
                'name'   => $account?->name,
            ]);

            // Transaction rows
            foreach ($group['rows'] as $line) {
                $flat->push([
                    '__type'   => 'row',
                    'line'     => $line,
                    'account'  => $account,
                ]);
            }

            // Subtotal row
            $flat->push([
                '__type'   => 'subtotal',
                'total_dr' => $group['total_debit'],
                'total_cr' => $group['total_credit'],
                'closing'  => $group['closing_balance'],
                'account'  => $account,
            ]);
        }

        return $flat;
    }

    public function headings(): array
    {
        return [
            ['GENERAL LEDGER'],
            ['Period:', ($this->from ? $this->from->format('d/m/Y') : 'Beginning') . ' to ' . ($this->to ? $this->to->format('d/m/Y') : 'Today')],
            ['Branch:', $this->branch ?? 'All Branches'],
            [],
            ['Date', 'JE Reference', 'Source', 'Voucher #', 'Payee', 'Branch', 'Debit (AED)', 'Credit (AED)', 'Balance'],
        ];
    }

    public function map($item): array
    {
        if ($item['__type'] === 'header') {
            return [
                "── {$item['code']} — {$item['name']} ──",
                '', '', '', '', '', '', '', '',
            ];
        }

        if ($item['__type'] === 'subtotal') {
            $account = $item['account'];
            $closing = $item['closing'];
            $isDebitNormal = $account?->normal_balance === 'debit';
            $side = $closing >= 0
                ? ($isDebitNormal ? 'DR' : 'CR')
                : ($isDebitNormal ? 'CR' : 'DR');

            return [
                'SUBTOTAL', '', '', '', '', '',
                number_format($item['total_dr'], 2),
                number_format($item['total_cr'], 2),
                number_format(abs($closing), 2) . ' ' . $side,
            ];
        }

        // Normal row — uses normalised plain-object shape
        $line    = $item['line'];
        $account = $item['account'];
        $bal     = $line->running_balance;
        $isDebitNormal = $account?->normal_balance === 'debit';
        $balSide = $bal >= 0
            ? ($isDebitNormal ? 'DR' : 'CR')
            : ($isDebitNormal ? 'CR' : 'DR');

        return [
            optional($line->date)->format('d/m/Y') ?? '',
            $line->je_ref ?? '',
            strtoupper($line->source ?? 'voucher') === 'JE' ? 'JE' : 'VCH',
            $line->voucher_number ?? '',
            $line->payee ?? '',
            $line->branch ?? '',
            $line->debit > 0 ? number_format($line->debit, 2) : '',
            $line->credit > 0 ? number_format($line->credit, 2) : '',
            number_format(abs($bal), 2) . ' ' . $balSide,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            5 => ['font' => ['bold' => true]],
        ];
    }
}
