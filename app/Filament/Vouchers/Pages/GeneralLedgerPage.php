<?php

namespace App\Filament\Vouchers\Pages;

use App\Models\AccountCode;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Liquidation;
use App\Models\VoucherItem;
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

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Accounting';
    protected static ?string $navigationLabel = 'General Ledger';
    protected static ?int $navigationSort = 6;
    protected static string $view = 'filament.vouchers.pages.general-ledger-page';



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
            'only_with_je'=> true,
            'data_source' => 'both',
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
                                    ->mapWithKeys(fn($a) => [$a->id => "{$a->code} — {$a->name}"])
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

                        \Filament\Forms\Components\Select::make('data_source')
                            ->label('Data Source')
                            ->options([
                                'both'          => '🔀 Both (Vouchers + JEs)',
                                'vouchers_only' => '📄 Vouchers Only',
                                'je_only'       => '📒 JE Lines Only',
                            ])
                            ->default('both')
                            ->selectablePlaceholder(false),

                        \Filament\Forms\Components\Toggle::make('only_with_je')
                            ->label('Only Show Liquidations with Official JE')
                            ->inline(false),
                    ])->columns(4),
            ])
            ->statePath('data');
    }

    /**
     * Fetch all matching lines from Vouchers and/or JEs, grouped by account.
     *
     * Strategy (data_source = 'both'):
     *   • VoucherItem rows → only for vouchers WITHOUT a linked JE (no double-count)
     *   • JournalEntryLine rows → all JEs (covers both voucher-backed and standalone)
     *
     * Each row is normalised into a plain object with a common shape so the blade
     * and export can handle both sources identically.
     */
    #[Computed]
    public function ledgerGroups(): Collection
    {
        $f          = $this->data;
        $from       = !empty($f['from_date']) ? Carbon::parse($f['from_date']) : null;
        $to         = !empty($f['to_date'])   ? Carbon::parse($f['to_date'])   : null;
        $accountId  = $f['account_id'] ?? [];
        $branch     = $f['branch']     ?? [];
        $basis      = $f['basis']      ?? [];
        $payee      = $f['payee']      ?? null;
        $dataSource = $f['data_source'] ?? 'both';

        $rows = collect();

        // ── 1. VoucherItem rows ──────────────────────────────────────────────
        if ($dataSource !== 'je_only') {
            // Include all vouchers, even if they have a linked Journal Entry.
            $voucherItems = VoucherItem::with(['voucher', 'voucher.journalEntry', 'accountCode'])
                ->whereHas('voucher', fn($q) => $q->where('status', 'paid'))
                ->when(!empty($accountId), function ($q) use ($accountId) {
                    $codes = AccountCode::whereIn('id', $accountId)->pluck('code');
                    $q->whereIn('account_code', $codes);
                })
                ->when(!empty($branch), fn($q) => $q->whereIn('branch_code', $branch))
                ->when(!empty($basis),  fn($q) => $q->whereHas('voucher', fn($v) => $v->whereIn('type', $basis)))
                ->when(!empty($payee),  fn($q) => $q->whereHas('voucher', fn($v) => $v->where('payee', 'like', '%' . $payee . '%')))
                ->when($from, fn($q) => $q->whereHas('voucher', fn($v) => $v->whereDate('created_at', '>=', $from)))
                ->when($to,   fn($q) => $q->whereHas('voucher', fn($v) => $v->whereDate('created_at', '<=', $to)))
                ->get()
                ->filter(fn($item) => $item->accountCode !== null)
                ->map(fn($item) => (object) [
                    'date'            => $item->voucher?->created_at,
                    'je_ref'          => $item->voucher?->journalEntry?->entry_no,
                    'je_id'           => $item->voucher?->journalEntry?->id,
                    'voucher_id'      => $item->voucher_id,
                    'voucher_number'  => $item->voucher?->voucher_number,
                    'voucher_type'    => $item->voucher?->type,
                    'voucher_amount'  => $item->voucher?->amount,
                    'payee'           => $item->voucher?->payee,
                    'branch'          => $item->branch_code,
                    'debit'           => (float) $item->debit,
                    'credit'          => (float) $item->credit,
                    'source'          => 'voucher',
                    'is_info_only'    => ($dataSource === 'both' && $item->voucher?->journalEntry !== null),
                    'account_code_id' => $item->accountCode->id,
                    'account'         => $item->accountCode,
                    'running_balance' => 0.0,
                    'description'     => $item->description,
                ]);

            $rows = $rows->merge($voucherItems);
        }

        // ── 2. JournalEntryLine rows ─────────────────────────────────────────
        if ($dataSource !== 'vouchers_only') {
            $jeLines = JournalEntryLine::with(['journalEntry', 'journalEntry.voucher', 'accountCode'])
                ->when(!empty($accountId), fn($q) => $q->whereIn('account_code_id', $accountId))
                ->when(!empty($branch),    fn($q) => $q->whereIn('branch', $branch))
                ->when(!empty($basis),     fn($q) => $q->whereHas('journalEntry.voucher', fn($v) => $v->whereIn('type', $basis)))
                ->when(!empty($payee), fn($q) => $q->where(function ($sub) use ($payee) {
                    $sub->where('supplier_name', 'like', '%' . $payee . '%')
                        ->orWhereHas('journalEntry.voucher', fn($v) => $v->where('payee', 'like', '%' . $payee . '%'));
                }))
                ->when($from, fn($q) => $q->whereHas('journalEntry', fn($j) => $j->whereDate('date', '>=', $from)))
                ->when($to,   fn($q) => $q->whereHas('journalEntry', fn($j) => $j->whereDate('date', '<=', $to)))
                ->get()
                ->filter(fn($line) => $line->accountCode !== null)
                ->map(fn($line) => (object) [
                    'date'            => $line->journalEntry?->date,
                    'je_ref'          => $line->journalEntry?->entry_no,
                    'je_id'           => $line->journal_entry_id,
                    'voucher_id'      => $line->journalEntry?->voucher_id,
                    'voucher_number'  => $line->journalEntry?->voucher?->voucher_number,
                    'voucher_type'    => $line->journalEntry?->voucher?->type,
                    'voucher_amount'  => $line->journalEntry?->voucher?->amount,
                    'payee'           => $line->supplier_name ?: ($line->journalEntry?->voucher?->payee ?? null),
                    'branch'          => $line->branch,
                    'debit'           => (float) $line->debit,
                    'credit'          => (float) $line->credit,
                    'source'          => 'je',
                    'is_info_only'    => false,
                    'account_code_id' => $line->account_code_id,
                    'account'         => $line->accountCode,
                    'running_balance' => 0.0,
                    'description'     => $line->remarks,
                ]);

            $rows = $rows->merge($jeLines);
        }

        // ── 3. Sort → group by account → running balance ─────────────────────
        $sorted = $rows->sortBy(fn($r) => [
            $r->account?->code ?? 'zzz',
            optional($r->date)->timestamp ?? 0,
        ]);

        return $sorted
            ->groupBy(fn($r) => $r->account_code_id ?? 0)
            ->filter(fn($group, $key) => $key > 0)
            ->map(function (Collection $group) {
                $account        = $group->first()->account;
                $runningBalance = 0.0;

                $rows = $group->map(function ($line) use ($account, &$runningBalance) {
                    $dr = !empty($line->is_info_only) ? 0.0 : $line->debit;
                    $cr = !empty($line->is_info_only) ? 0.0 : $line->credit;

                    if ($account && $account->normal_balance === 'debit') {
                        $runningBalance += ($dr - $cr);
                    } else {
                        $runningBalance += ($cr - $dr);
                    }

                    $line->running_balance = $runningBalance;
                    return $line;
                });

                return [
                    'account'         => $account,
                    'rows'            => $rows,
                    'total_debit'     => $group->reject(fn($r) => !empty($r->is_info_only))->sum('debit'),
                    'total_credit'    => $group->reject(fn($r) => !empty($r->is_info_only))->sum('credit'),
                    'closing_balance' => $runningBalance,
                ];
            })
            ->sortBy(fn($g) => $g['account']?->code);
    }

    #[Computed]
    public function totals(): array
    {
        // Derived from the already-computed (and Livewire-cached) ledgerGroups.
        // No redundant DB queries — computed properties are cached per request.
        $groups = $this->ledgerGroups;

        return [
            'debit'  => (float) $groups->sum('total_debit'),
            'credit' => (float) $groups->sum('total_credit'),
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

    /**
     * PCV Liquidation Reconciliation — shows the variance between what was
     * advanced to each employee and what they actually accounted for.
     * Filtered by the same date range, branch, and payee as the GL.
     */
    #[Computed]
    public function liquidationSummary(): \Illuminate\Support\Collection
    {
        $f = $this->data;
        $from = !empty($f['from_date']) ? \Carbon\Carbon::parse($f['from_date']) : null;
        $to = !empty($f['to_date']) ? \Carbon\Carbon::parse($f['to_date']) : null;
        $branch = $f['branch'] ?? [];
        $payee = $f['payee'] ?? null;
        $accountId = $f['account_id'] ?? [];
        $basis = $f['basis'] ?? [];
        $onlyWithJE = $f['only_with_je'] ?? false;

        // If basis filter is set and does not include 'petty_cash', then no PCV liquidations should show
        if (!empty($basis) && !in_array('petty_cash', $basis)) {
            return collect();
        }

        return Liquidation::with(['voucher.journalEntry', 'custodian'])
            ->whereHas('voucher', function ($q) use ($branch, $payee, $accountId, $onlyWithJE) {
                $q->where('type', 'petty_cash');

                if (!empty($payee)) {
                    $q->where('payee', 'like', '%' . $payee . '%');
                }

                // If account code or branch is specified, the voucher MUST have a JE that hits that account/branch
                if (!empty($accountId) || !empty($branch)) {
                    $q->whereHas('journalEntry.lines', function ($lineQuery) use ($accountId, $branch) {
                        if (!empty($accountId)) {
                            $lineQuery->whereIn('account_code_id', $accountId);
                        }
                        if (!empty($branch)) {
                            $lineQuery->whereIn('branch', $branch);
                        }
                    });
                } elseif ($onlyWithJE) {
                    // If toggle is enabled but no account code/branch filter is used, just enforce existence of ANY linked JE
                    $q->whereHas('journalEntry');
                }
            })
            ->when($from, fn($q) => $q->where(function ($sub) use ($from) {
                $sub->whereDate('liquidated_at', '>=', $from)
                    ->orWhere(function ($s2) use ($from) {
                        $s2->whereNull('liquidated_at')
                            ->whereHas('voucher', fn($v) => $v->whereDate('updated_at', '>=', $from));
                    });
            }))
            ->when($to, fn($q) => $q->where(function ($sub) use ($to) {
                $sub->whereDate('liquidated_at', '<=', $to)
                    ->orWhere(function ($s2) use ($to) {
                        $s2->whereNull('liquidated_at')
                            ->whereHas('voucher', fn($v) => $v->whereDate('updated_at', '<=', $to));
                    });
            }))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    #[Computed]
    public function liquidationTotals(): array
    {
        $rows = $this->liquidationSummary;
        return [
            'total_advanced' => $rows->sum(fn($l) => (float) ($l->voucher?->amount ?? 0)),
            'total_spent' => $rows->sum(fn($l) => (float) $l->amount_spent),
            'total_returned' => $rows->sum(fn($l) => (float) $l->amount_returned),
            'total_variance' => $rows->sum(fn($l) => (float) $l->variance),
            'pending_count' => $rows->where('status', 'pending')->count(),
            'complete_count' => $rows->where('status', 'complete')->count(),
            'short_count' => $rows->where('status', 'short')->count(),
            'excess_count' => $rows->where('status', 'excess')->count(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn() => $this->export()),
        ];
    }

    public function export()
    {
        $f = $this->data;
        $from = !empty($f['from_date']) ? Carbon::parse($f['from_date']) : null;
        $to = !empty($f['to_date']) ? Carbon::parse($f['to_date']) : null;
        $branch = $f['branch'] ?? [];
        $branchStr = !empty($branch) ? implode(', ', $branch) : null;

        return Excel::download(
            new GeneralLedgerExport($this->ledgerGroups, $from, $to, $branchStr),
            'General_Ledger_' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
