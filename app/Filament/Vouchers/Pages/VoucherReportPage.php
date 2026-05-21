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
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

class VoucherReportPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Report';
    protected static ?string $title = 'Vouchers Overview Report';
    protected static ?string $navigationGroup = null;
    protected static ?int $navigationSort = 9;
    protected static string $view = 'filament.vouchers.pages.voucher-report-page';

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin']) || auth()->user()->can('report.view');
    }

    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?string $account_code = null;
    public ?string $type = null;
    public int $perPage = 10;

    public function mount(): void
    {
        $this->date_from = now()->startOfMonth()->toDateString();
        $this->date_to   = now()->toDateString();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('downloadDailySummary')
                ->label('Download Daily Cash Summary')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->form([
                    DatePicker::make('report_date')
                        ->label('Select Date to Audit')
                        ->default(today())
                        ->required(),
                ])
                ->action(function (array $data) {
                    $dateStr = \Carbon\Carbon::parse($data['report_date'])->format('Y-m-d');
                    return \Maatwebsite\Excel\Facades\Excel::download(
                        new \App\Exports\DailySummaryExport($dateStr), 
                        "Daily_Cash_Summary_{$dateStr}.xlsx"
                    );
                }),
        ];
    }

    public function updating($name)
    {
        if (in_array($name, ['date_from', 'date_to', 'type', 'account_code', 'perPage'])) {
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
                        'receipt'    => 'Receipt Voucher',
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

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Voucher::query()
                    ->where('status', 'paid')
                    ->whereBetween('created_at', [($this->date_from ?: now()->startOfMonth()->toDateString()) . ' 00:00:00', ($this->date_to ?: now()->toDateString()) . ' 23:59:59'])
                    ->when($this->type, fn ($q) => $q->where('type', $this->type))
                    ->when($this->account_code, fn ($q) => $q->whereHas('items', fn ($sub) => $sub->where('account_code', $this->account_code)))
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('voucher_number')
                    ->label('Voucher #')
                    ->fontFamily('mono')
                    ->weight('semibold')
                    ->copyable()
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => \App\Filament\Vouchers\Resources\VoucherResource::getUrl('view', ['record' => $record->id]))
                    ->color('primary'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'receipt' => 'success',
                        'payment' => 'warning',
                        'petty_cash' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'receipt' => 'Receipt',
                        'payment' => 'Payment',
                        'petty_cash' => 'Petty Cash',
                        default => $state
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('payee')
                    ->label('Payee')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('items.account_code')
                    ->label('Account Code')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn ($record) => $record->items->first()?->account_code ?? '—'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Requester')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('AED')
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn ($record) => $record->type === 'receipt' ? 'success' : 'primary')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportExcel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(fn () => $this->exportExcel()),

                Tables\Actions\Action::make('exportPdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(fn () => $this->exportPdf()),

                Tables\Actions\Action::make('exportDenominations')
                    ->label('Export Denominations')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->action(fn () => $this->exportDenominationsExcel()),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10);
    }

    public function getReportData(): array
    {
        $query = Voucher::query()
            ->where('status', 'paid')
            ->with(['user', 'category', 'items'])
            ->whereBetween('created_at', [($this->date_from ?: now()->startOfMonth()->toDateString()) . ' 00:00:00', ($this->date_to ?: now()->toDateString()) . ' 23:59:59']);

        if ($this->type) {
            $query->where('type', $this->type);
        }
        if ($this->account_code) {
            $query->whereHas('items', function ($q) {
                $q->where('account_code', $this->account_code);
            });
        }

        $baseQuery = clone $query;
        $totalPayment = (clone $baseQuery)->where('type', 'payment')->sum('amount');
        $totalPettyCash = (clone $baseQuery)->where('type', 'petty_cash')->sum('amount');
        $totalReceipt = (clone $baseQuery)->where('type', 'receipt')->sum('amount');
        
        $netExpenditure = ($totalPayment + $totalPettyCash) - $totalReceipt;
        $totalCount = $baseQuery->count();

        // Group by account codes using voucher items
        $byAccountData = DB::table('voucher_items')
            ->join('vouchers', 'vouchers.id', '=', 'voucher_items.voucher_id')
            ->whereNull('vouchers.deleted_at')
            ->where('vouchers.status', 'paid')
            ->where('voucher_items.entry_type', 'debit')
            ->whereBetween('vouchers.created_at', [($this->date_from ?: now()->startOfMonth()->toDateString()) . ' 00:00:00', ($this->date_to ?: now()->toDateString()) . ' 23:59:59']);

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
            'total_payment'    => $totalPayment,
            'total_petty_cash' => $totalPettyCash,
            'total_receipt'    => $totalReceipt,
            'net_expenditure'  => $netExpenditure,
            'total_count'      => $totalCount,
            'by_category'      => $byAccount,
        ];
    }

    public function exportExcel(): mixed
    {
        $query = Voucher::query()
            ->where('status', 'paid')
            ->with(['user', 'category'])
            ->whereBetween('created_at', [($this->date_from ?: now()->startOfMonth()->toDateString()) . ' 00:00:00', ($this->date_to ?: now()->toDateString()) . ' 23:59:59']);

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
            'petty_cash_report_' . ($this->date_from ?: now()->startOfMonth()->toDateString()) . '_to_' . ($this->date_to ?: now()->toDateString()) . '.xlsx'
        );
    }

    public function exportPdf()
    {
        $data = $this->getReportData();

        $query = Voucher::query()
            ->where('status', 'paid')
            ->with(['user', 'category', 'items'])
            ->whereBetween('created_at', [($this->date_from ?: now()->startOfMonth()->toDateString()) . ' 00:00:00', ($this->date_to ?: now()->toDateString()) . ' 23:59:59'])
            ->orderByDesc('created_at');

        if ($this->type) {
            $query->where('type', $this->type);
        }
        if ($this->account_code) {
            $query->whereHas('items', function ($q) {
                $q->where('account_code', $this->account_code);
            });
        }

        $allVouchers = $query->get();
        $data['vouchers_all'] = $allVouchers;
        $data['date_from']    = $this->date_from ?: now()->startOfMonth()->toDateString();
        $data['date_to']      = $this->date_to ?: now()->toDateString();
        $data['pdf_template'] = \App\Models\VoucherTemplate::first();

        return response()->streamDownload(function () use ($data) {
            echo \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.report', $data)
                ->setPaper('A4', 'landscape')
                ->output();
        }, 'vouchers_report_' . ($this->date_from ?: now()->startOfMonth()->toDateString()) . '_to_' . ($this->date_to ?: now()->toDateString()) . '.pdf');
    }

    public function exportDenominationsExcel()
    {
        $query = Voucher::query()
            ->where('status', 'paid')
            ->whereBetween('created_at', [($this->date_from ?: now()->startOfMonth()->toDateString()) . ' 00:00:00', ($this->date_to ?: now()->toDateString()) . ' 23:59:59']);

        if ($this->type) {
            $query->where('type', $this->type);
        }
        if ($this->account_code) {
            $query->whereHas('items', function ($q) {
                $q->where('account_code', $this->account_code);
            });
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\DenominationsExport($query, ($this->date_from ?: now()->startOfMonth()->toDateString()), ($this->date_to ?: now()->toDateString())),
            'denominations_report_' . ($this->date_from ?: now()->startOfMonth()->toDateString()) . '_to_' . ($this->date_to ?: now()->toDateString()) . '.xlsx'
        );
    }
}
