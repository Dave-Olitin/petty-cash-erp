<?php

namespace App\Filament\Vouchers\Pages;

use App\Models\Voucher;
use App\Models\Category;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Livewire\WithPagination;

class VoucherReportPage extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Report';
    protected static ?string $title = 'Vouchers Overview Report';
    protected static ?string $navigationGroup = null;
    protected static ?int $navigationSort = 10;
    protected static string $view = 'filament.vouchers.pages.voucher-report-page';

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin']) || auth()->user()->can('report.view');
    }

    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?string $account_code = null;
    public ?string $type = null;

    public function mount(): void
    {
        $this->date_from = now()->startOfMonth()->toDateString();
        $this->date_to   = now()->toDateString();
    }

    public function updating($name)
    {
        if (in_array($name, ['date_from', 'date_to', 'type', 'account_code'])) {
            $this->resetPage();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('date_from')
                    ->label('From')
                    ->required()
                    ->live(),
                DatePicker::make('date_to')
                    ->label('To')
                    ->required()
                    ->live(),
                Select::make('type')
                    ->options([
                        'petty_cash' => 'Petty Cash',
                        'payment'    => 'Payment Voucher',
                    ])
                    ->placeholder('All Types')
                    ->live(),
                Select::make('account_code')
                    ->label('Account Code')
                    ->options(\App\Models\AccountCode::orderBy('name')->get()->mapWithKeys(fn ($acct) => [$acct->code => $acct->code . ' - ' . $acct->name])->toArray())
                    ->placeholder('All Account Codes')
                    ->searchable()
                    ->live(),
            ])
            ->columns(['sm' => 2, 'xl' => 4]);
    }

    public function getReportData(): array
    {
        $query = Voucher::query()
            ->where('status', 'paid')
            ->with(['user', 'category', 'items'])
            ->whereBetween('created_at', [$this->date_from . ' 00:00:00', $this->date_to . ' 23:59:59']);

        if ($this->type) {
            $query->where('type', $this->type);
        }
        if ($this->account_code) {
            $query->whereHas('items', function ($q) {
                $q->where('account_code', $this->account_code);
            });
        }

        $baseQuery = clone $query;
        $totalAmount = $baseQuery->sum('amount');
        $totalPayment = (clone $baseQuery)->where('type', 'payment')->sum('amount');
        $totalPettyCash = (clone $baseQuery)->where('type', 'petty_cash')->sum('amount');
        $totalCount = $baseQuery->count();

        $vouchers = $query->orderByDesc('created_at')->paginate(10);

        // Group by account codes using voucher items
        $byAccountData = DB::table('voucher_items')
            ->join('vouchers', 'vouchers.id', '=', 'voucher_items.voucher_id')
            ->whereNull('vouchers.deleted_at')
            ->where('vouchers.status', 'paid')
            ->where('voucher_items.entry_type', 'debit')
            ->whereBetween('vouchers.created_at', [$this->date_from . ' 00:00:00', $this->date_to . ' 23:59:59']);

        if ($this->type) {
            $byAccountData->where('vouchers.type', $this->type);
        }
        if ($this->account_code) {
            $byAccountData->where('voucher_items.account_code', $this->account_code);
        }

        $accountData = $byAccountData
            ->select('voucher_items.account_code', DB::raw('SUM(voucher_items.amount) as total_amount'))
            ->groupBy('voucher_items.account_code')
            ->get();

        $byAccount = $accountData->mapWithKeys(function ($item) {
            if (!$item->account_code) {
                return ['Uncategorized' => (float) $item->total_amount];
            }
            $account = \App\Models\AccountCode::where('code', $item->account_code)->first();
            $name = $account ? "{$item->account_code} - " . \Illuminate\Support\Str::limit($account->name, 25) : $item->account_code;
            return [$name => (float) $item->total_amount];
        })->sortDesc();

        return [
            'vouchers'         => $vouchers,
            'total_amount'     => $totalAmount,
            'total_payment'    => $totalPayment,
            'total_petty_cash' => $totalPettyCash,
            'total_count'      => $totalCount,
            'by_category'      => $byAccount, // Kept the key name for blade compatibility
        ];
    }

    public function exportExcel(): mixed
    {
        $query = Voucher::query()
            ->where('status', 'paid')
            ->with(['user', 'category'])
            ->whereBetween('created_at', [$this->date_from . ' 00:00:00', $this->date_to . ' 23:59:59']);

        if ($this->type) {
            $query->where('type', $this->type);
        }
        if ($this->account_code) {
            $query->whereHas('items', function ($q) {
                $q->where('account_code', $this->account_code);
            });
        }

        $query->orderByDesc('created_at');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\VouchersExport($query),
            'petty_cash_report_' . $this->date_from . '_to_' . $this->date_to . '.xlsx'
        );
    }
}
