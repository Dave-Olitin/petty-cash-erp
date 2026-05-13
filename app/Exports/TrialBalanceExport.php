<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Carbon\Carbon;

class TrialBalanceExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected Collection $accounts;
    protected ?Carbon $from;
    protected ?Carbon $to;
    protected ?string $branch;

    public function __construct(Collection $accounts, ?Carbon $from, ?Carbon $to, ?string $branch)
    {
        $this->accounts = $accounts;
        $this->from = $from;
        $this->to = $to;
        $this->branch = $branch;
    }

    public function collection()
    {
        return $this->accounts->filter(fn($a) => $a->total_debit > 0 || $a->total_credit > 0);
    }

    public function headings(): array
    {
        return [
            ['TRIAL BALANCE REPORT'],
            ['Period:', ($this->from ? $this->from->format('d/m/Y') : 'Beginning') . ' to ' . ($this->to ? $this->to->format('d/m/Y') : 'Today')],
            ['Branch:', $this->branch ?? 'All Branches'],
            [],
            ['Code', 'Account Name', 'Type', 'Debit (AED)', 'Credit (AED)', 'Net Balance', 'Side']
        ];
    }

    public function map($account): array
    {
        $bal = abs($account->net_balance);
        $suffix = $account->net_balance >= 0 ? ($account->normal_balance === 'debit' ? 'DR' : 'CR') : ($account->normal_balance === 'debit' ? 'CR' : 'DR');

        return [
            $account->code,
            $account->name,
            $account->type->label(),
            $account->total_debit,
            $account->total_credit,
            $bal,
            $suffix
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            5 => ['font' => ['bold' => true]],
        ];
    }
}
