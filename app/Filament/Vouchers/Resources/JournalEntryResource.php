<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\JournalEntryResource\Pages;
use App\Models\JournalEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;

class JournalEntryResource extends Resource
{
    protected static ?string $model = JournalEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationGroup = 'Accounting';

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin']) || auth()->user()->can('journal_entry.view');
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin']) || auth()->user()->can('journal_entry.view');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin']) || auth()->user()->can('journal_entry.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin']) || auth()->user()->can('journal_entry.edit');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin']) || auth()->user()->can('journal_entry.delete');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with([
            'voucher',
            'lines.accountCode',
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Journal Entry Details')->schema([
                    Forms\Components\DatePicker::make('date')
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    Forms\Components\Select::make('voucher_id')
                        ->relationship('voucher', 'voucher_number')
                        ->searchable()
                        ->preload()
                        ->label('Linked Voucher'),
                    Forms\Components\TextInput::make('po_number')
                        ->label('PO Number')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('invoice_no')
                        ->label('Invoice #')
                        ->maxLength(255),

                    // ── HIDDEN (kept for data integrity) ──────────────────────
                    Forms\Components\Hidden::make('reference'),
                    Forms\Components\Hidden::make('currency')->default('AED'),
                ])->columns(4),

                Forms\Components\Section::make('Entry Lines')->schema([
                    Forms\Components\Repeater::make('lines')
                        ->relationship('lines')
                        ->live()
                        ->schema([
                            Forms\Components\Select::make('account_code_id')
                                ->relationship('accountCode', 'code')
                                ->label('Account')
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->code . ' — ' . $record->name)
                                ->searchable()
                                ->native(false)
                                ->columnSpan(2),
                            Forms\Components\Select::make('branch')
                                ->label('Branch')
                                ->options(\App\Models\LedgerBranch::pluck('name', 'name'))
                                ->searchable()
                                ->preload()
                                ->columnSpan(1),
                            Forms\Components\Select::make('supplier_name')
                                ->label('Supplier')
                                ->options(\App\Models\TaxRegistration::pluck('name', 'name'))
                                ->searchable()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                                    if ($state) {
                                        $tax = \App\Models\TaxRegistration::where('name', $state)->first();
                                        if ($tax && $tax->trn) {
                                            $set('trn', $tax->trn);
                                        }
                                    }
                                })
                                ->columnSpan(2),
                            Forms\Components\TextInput::make('trn')
                                ->label('TRN')
                                ->maxLength(255)
                                ->columnSpan(1),
                            Forms\Components\TextInput::make('remarks')
                                ->label('Description')
                                ->columnSpan(2),
                            Forms\Components\TextInput::make('debit')
                                ->label('Debit')
                                ->numeric()
                                ->default(0)
                                ->required()
                                ->prefix('DR')
                                ->columnSpan(1)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                    if ((float)$state > 0) $set('credit', 0);
                                }),
                            Forms\Components\TextInput::make('credit')
                                ->label('Credit')
                                ->numeric()
                                ->default(0)
                                ->required()
                                ->prefix('CR')
                                ->columnSpan(1)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                    if ((float)$state > 0) $set('debit', 0);
                                }),
                        ])->columns(10)
                        ->rule(function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                // Ensure user provides balanced lines before saving
                                $totalDebit = collect($value)->sum('debit');
                                $totalCredit = collect($value)->sum('credit');
                                if (abs($totalDebit - $totalCredit) > 0.001) {
                                    $fail("Total Debits (AED {$totalDebit}) must exactly equal Total Credits (AED {$totalCredit}).");
                                }
                            };
                        })
                ]),

                Forms\Components\Section::make('Totals & Balance')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('total_debit_sum')
                                    ->label('Total Debit')
                                    ->content(function (Forms\Get $get) {
                                        $lines = $get('lines') ?? [];
                                        $sum = (float) collect($lines)->sum(fn ($i) => (float)($i['debit'] ?? 0));
                                        return new \Illuminate\Support\HtmlString('<div class="text-xl font-mono text-gray-700">' . number_format($sum, 2) . ' <span class="text-xs text-gray-500">AED</span></div>');
                                    }),
                                Forms\Components\Placeholder::make('total_credit_sum')
                                    ->label('Total Credit')
                                    ->content(function (Forms\Get $get) {
                                        $lines = $get('lines') ?? [];
                                        $sum = (float) collect($lines)->sum(fn ($i) => (float)($i['credit'] ?? 0));
                                        return new \Illuminate\Support\HtmlString('<div class="text-xl font-mono text-gray-700">' . number_format($sum, 2) . ' <span class="text-xs text-gray-500">AED</span></div>');
                                    }),
                                Forms\Components\Placeholder::make('balance_indicator')
                                    ->label('Status')
                                    ->content(function (Forms\Get $get) {
                                        $lines = $get('lines') ?? [];
                                        $debit = (float) collect($lines)->sum(fn ($i) => (float)($i['debit'] ?? 0));
                                        $credit = (float) collect($lines)->sum(fn ($i) => (float)($i['credit'] ?? 0));
                                        
                                        if ($debit === 0.0 && $credit === 0.0) {
                                            return new \Illuminate\Support\HtmlString('<div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">No Entries</div>');
                                        }
                                        
                                        if (abs($debit - $credit) < 0.001) {
                                            return new \Illuminate\Support\HtmlString('<div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">✓ Balanced</div>');
                                        }
                                        
                                        $diff = number_format(abs($debit - $credit), 2);
                                        return new \Illuminate\Support\HtmlString('<div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">⚠ Out of Balance (' . $diff . ')</div>');
                                    }),
                            ])
                    ])->compact(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entry_no')
                    ->label('Entry No.')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('po_number')
                    ->label('PO #')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('voucher.voucher_number')
                    ->label('Ref. Voucher')
                    ->searchable()
                    ->default('—'),
                Tables\Columns\TextColumn::make('total_debit')
                    ->label('Total Debit')
                    ->money('AED')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_credit')
                    ->label('Total Credit')
                    ->money('AED')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from'),
                        Forms\Components\DatePicker::make('date_until'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('date', '<=', $date),
                            );
                    })
            ])
            ->headerActions([
                \Filament\Tables\Actions\Action::make('export_filtered')
                    ->label('Export All')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($livewire) {
                        return \Maatwebsite\Excel\Facades\Excel::download(
                            new \App\Exports\JournalEntriesExport($livewire->getFilteredTableQuery()),
                            'journal_entries_' . now()->format('Y-m-d_His') . '.xlsx'
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('export_selected')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $ids = $records->pluck('id')->toArray();
                            return \Maatwebsite\Excel\Facades\Excel::download(
                                new \App\Exports\JournalEntriesExport(\App\Models\JournalEntry::whereIn('id', $ids)),
                                'selected_journal_entries_' . now()->format('Y-m-d_His') . '.xlsx'
                            );
                        })->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournalEntries::route('/'),
            'create' => Pages\CreateJournalEntry::route('/create'),
            'view' => Pages\ViewJournalEntry::route('/{record}'),
            'edit' => Pages\EditJournalEntry::route('/{record}/edit'),
        ];
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('General Information')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(4)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('entry_no')
                                    ->label('Entry Number')
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                    ->copyable(),
                                \Filament\Infolists\Components\TextEntry::make('date')
                                    ->label('Post Date')
                                    ->date('M j, Y'),
                                \Filament\Infolists\Components\TextEntry::make('po_number')
                                    ->label('PO #')
                                    ->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('invoice_no')
                                    ->label('Invoice #')
                                    ->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('voucher.voucher_number')
                                    ->label('Ref. Voucher')
                                    ->placeholder('—')
                                    ->url(fn ($record) => $record->voucher_id ? \App\Filament\Vouchers\Resources\VoucherResource::getUrl('view', ['record' => $record->voucher_id]) : null),
                            ])->columns(4),
                    ]),

                \Filament\Infolists\Components\Section::make('Accounting Lines')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(12)
                            ->extraAttributes(['class' => 'bg-gray-100 p-2 border-b border-gray-200 rounded-t-lg'])
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('header_account')->state('Account / Ledger')->hiddenLabel()->weight(\Filament\Support\Enums\FontWeight::Bold)->columnSpan(3),
                                \Filament\Infolists\Components\TextEntry::make('header_branch')->state('Branch')->hiddenLabel()->weight(\Filament\Support\Enums\FontWeight::Bold)->columnSpan(2),
                                \Filament\Infolists\Components\TextEntry::make('header_supplier')->state('Supplier')->hiddenLabel()->weight(\Filament\Support\Enums\FontWeight::Bold)->columnSpan(2),
                                \Filament\Infolists\Components\TextEntry::make('header_trn')->state('TRN')->hiddenLabel()->weight(\Filament\Support\Enums\FontWeight::Bold)->columnSpan(1),
                                \Filament\Infolists\Components\TextEntry::make('header_remarks')->state('Description')->hiddenLabel()->weight(\Filament\Support\Enums\FontWeight::Bold)->columnSpan(2),
                                \Filament\Infolists\Components\TextEntry::make('header_debit')->state('Debit (DR)')->hiddenLabel()->weight(\Filament\Support\Enums\FontWeight::Bold)->columnSpan(1),
                                \Filament\Infolists\Components\TextEntry::make('header_credit')->state('Credit (CR)')->hiddenLabel()->weight(\Filament\Support\Enums\FontWeight::Bold)->columnSpan(1),
                            ]),

                        \Filament\Infolists\Components\RepeatableEntry::make('lines')
                            ->label('')
                            ->schema([
                                \Filament\Infolists\Components\Grid::make(12)
                                    ->schema([
                                        \Filament\Infolists\Components\TextEntry::make('accountCode.code')
                                            ->label('Account')
                                            ->hiddenLabel()
                                            ->formatStateUsing(fn ($state, $record) => $state . ' - ' . $record->accountCode?->name)
                                            ->columnSpan(3),
                                        \Filament\Infolists\Components\TextEntry::make('branch')
                                            ->label('Branch')
                                            ->hiddenLabel()
                                            ->placeholder('—')
                                            ->columnSpan(2),
                                        \Filament\Infolists\Components\TextEntry::make('supplier_name')
                                            ->label('Supplier')
                                            ->hiddenLabel()
                                            ->placeholder('—')
                                            ->columnSpan(2),
                                        \Filament\Infolists\Components\TextEntry::make('trn')
                                            ->label('TRN')
                                            ->hiddenLabel()
                                            ->placeholder('—')
                                            ->columnSpan(1),
                                        \Filament\Infolists\Components\TextEntry::make('remarks')
                                            ->label('Description')
                                            ->hiddenLabel()
                                            ->placeholder('—')
                                            ->columnSpan(2),
                                        \Filament\Infolists\Components\TextEntry::make('debit')
                                            ->label('Debit')
                                            ->hiddenLabel()
                                            ->money('AED')
                                            ->extraAttributes(['class' => 'font-mono text-gray-700'])
                                            ->columnSpan(1),
                                        \Filament\Infolists\Components\TextEntry::make('credit')
                                            ->label('Credit')
                                            ->hiddenLabel()
                                            ->money('AED')
                                            ->extraAttributes(['class' => 'font-mono text-gray-700'])
                                            ->columnSpan(1),
                                    ])
                            ])
                            ->columns(1)
                            ->contained(false)
                    ]),

                \Filament\Infolists\Components\Section::make('Summary Totals')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(3)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('total_debit')
                                    ->label('Grand Total (Debit)')
                                    ->money('AED')
                                    ->extraAttributes(['class' => 'text-xl font-mono text-gray-900 font-bold border-l-4 border-primary-500 pl-3']),
                                \Filament\Infolists\Components\TextEntry::make('total_credit')
                                    ->label('Grand Total (Credit)')
                                    ->money('AED')
                                    ->extraAttributes(['class' => 'text-xl font-mono text-gray-900 font-bold border-l-4 border-primary-500 pl-3']),
                                \Filament\Infolists\Components\TextEntry::make('ledger_status')
                                    ->label('Ledger Status')
                                    ->badge()
                                    ->state(function ($record): string {
                                        $debit = (float)($record->total_debit ?? 0);
                                        $credit = (float)($record->total_credit ?? 0);
                                        return (abs($debit - $credit) < 0.001) ? 'BALANCED' : 'UNBALANCED';
                                    })
                                    ->color(fn ($state) => $state === 'BALANCED' ? 'success' : 'danger'),
                            ])
                    ])->compact(),
            ]);
    }
}
