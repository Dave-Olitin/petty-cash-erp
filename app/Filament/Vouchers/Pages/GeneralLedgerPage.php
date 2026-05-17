<?php

namespace App\Filament\Vouchers\Pages;

use App\Models\AccountCode;
use App\Models\JournalEntryLine;
use App\Models\LedgerBranch;
use App\Exports\GeneralLedgerExport;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Livewire\Attributes\Computed;

class GeneralLedgerPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Accounting';
    protected static ?string $navigationLabel = 'General Ledger';
    protected static ?int    $navigationSort  = 9;
    protected static string  $view = 'filament.vouchers.pages.general-ledger-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'from_date'   => now()->startOfMonth()->toDateString(),
            'to_date'     => now()->toDateString(),
            'account_id'  => [],
            'branch'      => [],
            'basis'       => [],
            'payee'       => null,
        ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin'])
            || auth()->user()->can('gl_report.view');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filters')
                    ->schema([
                        DatePicker::make('from_date')
                            ->label('From Date')
                            ->native(false),

                        DatePicker::make('to_date')
                            ->label('To Date')
                            ->native(false),

                        Select::make('account_id')
                            ->label('Account Code')
                            ->multiple()
                            ->options(
                                AccountCode::query()
                                    ->orderBy('code')
                                    ->get(['id', 'code', 'name'])
                                    ->mapWithKeys(fn ($a) => [$a->id => "{$a->code} — {$a->name}"])
                            )
                            ->searchable()
                            ->placeholder('All Accounts'),

                        Select::make('branch')
                            ->label('Branch')
                            ->multiple()
                            ->options(LedgerBranch::pluck('name', 'name'))
                            ->searchable()
                            ->placeholder('All Branches'),

                        Select::make('basis')
                            ->label('Basis (Voucher Type)')
                            ->multiple()
                            ->options([
                                'petty_cash' => 'Petty Cash',
                                'payment' => 'Payment Voucher',
                                'receipt' => 'Receipt Voucher',
                                'bank_encashment' => 'Bank Encashment',
                            ])
                            ->placeholder('All Types'),

                        \Filament\Forms\Components\TextInput::make('payee')
                            ->label('Payee')
                            ->placeholder('Search Payee'),
                    ])->columns(3),
            ])
            ->statePath('data');
    }

    /** Fetch all matching JE lines, grouped by account. */
    #[Computed]
    public function ledgerGroups(): Collection
    {
        $f  = $this->data;
        $from      = !empty($f['from_date'])  ? Carbon::parse($f['from_date'])  : null;
        $to        = !empty($f['to_date'])    ? Carbon::parse($f['to_date'])    : null;
        $accountId = $f['account_id'] ?? [];
        $branch    = $f['branch']     ?? [];
        $basis     = $f['basis']      ?? [];
        $payee     = $f['payee']      ?? null;

        $lines = JournalEntryLine::with(['journalEntry', 'journalEntry.voucher', 'accountCode'])
            ->when(!empty($accountId), fn ($q) => $q->whereIn('account_code_id', $accountId))
            ->when(!empty($branch),    fn ($q) => $q->whereIn('branch', $branch))
            ->when(!empty($basis),     fn ($q) => $q->whereHas('journalEntry.voucher', fn ($v) => $v->whereIn('type', $basis)))
            ->when(!empty($payee),     fn ($q) => $q->whereHas('journalEntry.voucher', fn ($v) => $v->where('payee', 'like', '%' . $payee . '%')))
            ->when($from, fn ($q) => $q->whereHas('journalEntry',
                fn ($je) => $je->whereDate('date', '>=', $from)))
            ->when($to, fn ($q) => $q->whereHas('journalEntry',
                fn ($je) => $je->whereDate('date', '<=', $to)))
            ->get()
            ->sortBy(fn ($l) => [$l->accountCode?->code, $l->journalEntry?->date]);

        // Group by account, and attach a running balance per group
        return $lines
            ->groupBy('account_code_id')
            ->map(function (Collection $group) {
                $account = $group->first()->accountCode;
                $runningBalance = 0;

                $rows = $group->map(function ($line) use ($account, &$runningBalance) {
                    $dr = (float) $line->debit;
                    $cr = (float) $line->credit;

                    // Running balance: positive = normal side, negative = contra
                    if ($account && $account->normal_balance === 'debit') {
                        $runningBalance += ($dr - $cr);
                    } else {
                        $runningBalance += ($cr - $dr);
                    }

                    $line->running_balance = $runningBalance;
                    return $line;
                });

                return [
                    'account'    => $account,
                    'rows'       => $rows,
                    'total_debit'  => $group->sum('debit'),
                    'total_credit' => $group->sum('credit'),
                    'closing_balance' => $runningBalance,
                ];
            })
            ->sortBy(fn ($g) => $g['account']?->code);
    }

    #[Computed]
    public function totals(): array
    {
        $f  = $this->data;
        $from      = !empty($f['from_date'])  ? Carbon::parse($f['from_date'])  : null;
        $to        = !empty($f['to_date'])    ? Carbon::parse($f['to_date'])    : null;
        $accountId = $f['account_id'] ?? [];
        $branch    = $f['branch']     ?? [];
        $basis     = $f['basis']      ?? [];
        $payee     = $f['payee']      ?? null;

        $query = JournalEntryLine::query()
            ->when(!empty($accountId), fn ($q) => $q->whereIn('account_code_id', $accountId))
            ->when(!empty($branch),    fn ($q) => $q->whereIn('branch', $branch))
            ->when(!empty($basis),     fn ($q) => $q->whereHas('journalEntry.voucher', fn ($v) => $v->whereIn('type', $basis)))
            ->when(!empty($payee),     fn ($q) => $q->whereHas('journalEntry.voucher', fn ($v) => $v->where('payee', 'like', '%' . $payee . '%')))
            ->when($from, fn ($q) => $q->whereHas('journalEntry',
                fn ($je) => $je->whereDate('date', '>=', $from)))
            ->when($to, fn ($q) => $q->whereHas('journalEntry',
                fn ($je) => $je->whereDate('date', '<=', $to)));

        return [
            'debit'  => (float) $query->sum('debit'),
            'credit' => (float) $query->sum('credit'),
        ];
    }

    #[Computed]
    public function grandTotalDebit(): float
    {
        return $this->totals['debit'];
    }

    #[Computed]
    public function grandTotalCredit(): float
    {
        return $this->totals['credit'];
    }

    public function updateReport(): void
    {
        // Triggers Livewire re-render; Computed properties re-evaluate automatically
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => $this->export()),
        ];
    }

    public function export()
    {
        $f     = $this->data;
        $from  = !empty($f['from_date']) ? Carbon::parse($f['from_date']) : null;
        $to    = !empty($f['to_date'])   ? Carbon::parse($f['to_date'])   : null;
        $branch = $f['branch'] ?? [];
        $branchStr = !empty($branch) ? implode(', ', $branch) : null;

        return Excel::download(
            new GeneralLedgerExport($this->ledgerGroups, $from, $to, $branchStr),
            'General_Ledger_' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
