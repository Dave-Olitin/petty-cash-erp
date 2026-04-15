<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\PurchaseEntryResource\Pages;
use App\Models\PurchaseEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseEntryResource extends Resource
{
    protected static ?string $model = PurchaseEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Accounting';

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin']) || auth()->user()->can('purchase_entry.view');
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin']) || auth()->user()->can('purchase_entry.view');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin']) || auth()->user()->can('purchase_entry.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin']) || auth()->user()->can('purchase_entry.edit');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin']) || auth()->user()->can('purchase_entry.delete');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with([
            'taxRegistration',
            'lines.debitAccount',
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Purchase Bill Details')->schema([

                    Forms\Components\Select::make('entity')
                        ->label('Entity')
                        ->options(\App\Models\VoucherTemplate::where('is_active', true)->pluck('company_name', 'company_name'))
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Hidden::make('branch'),



                    Forms\Components\Select::make('tax_registration_id')
                        ->label('Supplier')
                        ->relationship('taxRegistration', 'name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                            if ($state) {
                                $tax = \App\Models\TaxRegistration::find($state);
                                if ($tax && $tax->payment_terms && $get('date')) {
                                    $date = \Carbon\Carbon::parse($get('date'));
                                    if (preg_match('/(\d+)/', $tax->payment_terms, $matches)) {
                                        $set('due_date', $date->addDays((int) $matches[1])->format('Y-m-d'));
                                    } else {
                                        $set('due_date', $date->format('Y-m-d'));
                                    }
                                }
                            }
                        })
                        ->createOptionForm([
                            Forms\Components\TextInput::make('trn')->label('TRN Number')->required()->unique('tax_registrations', 'trn'),
                            Forms\Components\TextInput::make('name')->label('Supplier / Company Name')->required(),
                        ])
                        ->createOptionUsing(function (array $data) {
                            $tax = \App\Models\TaxRegistration::create($data);
                            return $tax->id;
                        }),

                    // ── Dates ─────────────────────────────────
                    Forms\Components\DatePicker::make('date')
                        ->label('Bill Date')
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->live()
                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                            $taxId = $get('tax_registration_id');
                            if ($state && $taxId) {
                                $tax = \App\Models\TaxRegistration::find($taxId);
                                if ($tax && $tax->payment_terms) {
                                    $date = \Carbon\Carbon::parse($state);
                                    if (preg_match('/(\d+)/', $tax->payment_terms, $matches)) {
                                        $set('due_date', $date->addDays((int) $matches[1])->format('Y-m-d'));
                                    } else {
                                        $set('due_date', $date->format('Y-m-d'));
                                    }
                                }
                            }
                        }),

                    Forms\Components\DatePicker::make('due_date')
                        ->label('Due Date')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->helperText(function (Forms\Get $get) {
                            $taxId = $get('tax_registration_id');
                            if (!$taxId) {
                                return new \Illuminate\Support\HtmlString(
                                    '<span class="text-xs text-gray-400 italic">⚡ Select a supplier to auto-calculate the due date from their payment terms.</span>'
                                );
                            }
                            $tax = \App\Models\TaxRegistration::find($taxId);
                            if (!$tax || !$tax->payment_terms) {
                                return new \Illuminate\Support\HtmlString(
                                    '<span class="text-xs text-amber-500 italic">⚠ No payment terms set for this supplier. Enter due date manually or update the supplier record.</span>'
                                );
                            }
                            $terms = $tax->payment_terms;
                            if (preg_match('/(\d+)/', $terms, $matches)) {
                                return new \Illuminate\Support\HtmlString(
                                    '<span class="text-xs text-green-600 italic">✓ Auto-calculated: <strong>' . e($terms) . '</strong> (' . $matches[1] . ' days from bill date). You can override this manually.</span>'
                                );
                            }
                            return new \Illuminate\Support\HtmlString(
                                '<span class="text-xs text-amber-500 italic">⚠ Payment terms "<strong>' . e($terms) . '</strong>" (e.g. COD / On Receipt) — due date defaulted to bill date. Override if needed.</span>'
                            );
                        }),

                    // Hidden fields kept for data integrity
                    Forms\Components\Hidden::make('supplier_name'),
                    Forms\Components\Hidden::make('supplier_trn'),
                    Forms\Components\Hidden::make('currency')->default('AED'),
                    Forms\Components\Hidden::make('price_type')->default('exclusive'),
                ])->columns(4),

                Forms\Components\Section::make('Supplier Reference')
                    ->description('Provide optional supplier document numbers.')
                    ->schema([
                        Forms\Components\TextInput::make('po_number')
                            ->label('PO Number')
                            ->placeholder('e.g. PO-2024-001'),
                        Forms\Components\TextInput::make('invoice_no')
                            ->label('Invoice Number')
                            ->placeholder('e.g. INV-2023001'),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Forms\Components\Section::make('Entry Lines')->schema([
                    Forms\Components\Repeater::make('lines')
                        ->relationship('lines')
                        ->live()
                        ->schema([
                            Forms\Components\Grid::make(12)
                                ->schema([
                                    Forms\Components\Select::make('debit_account_id')
                                        ->relationship('debitAccount', 'code')
                                        ->label('Account')
                                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->code . ' — ' . $record->name)
                                        ->searchable()
                                        ->native(false)
                                        ->columnSpan(4),
                                    Forms\Components\TextInput::make('description')
                                        ->label('Item Description')
                                        ->required()
                                        ->columnSpan(4),
                                    Forms\Components\Select::make('branch')
                                        ->label('Branch')
                                        ->options(\App\Models\LedgerBranch::pluck('name', 'name'))
                                        ->searchable()
                                        ->preload()
                                        ->columnSpan(2),
                                    Forms\Components\TextInput::make('total')
                                        ->label('Total Amount')
                                        ->numeric()
                                        ->default(0)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                            // Keep amount and tax_amount in sync for backward compatibility
                                            $total = (float) $state;
                                            $set('amount', $total);
                                            $set('tax_amount', 0);
                                        })
                                        ->prefix('AED')
                                        ->extraInputAttributes(['class' => 'font-bold text-primary-600'])
                                        ->columnSpan(2),
                                    // Hidden fields kept for data integrity
                                    Forms\Components\Hidden::make('amount'),
                                    Forms\Components\Hidden::make('tax_percentage')->default(0),
                                    Forms\Components\Hidden::make('tax_amount')->default(0),
                                ])
                        ])
                        ->itemLabel(fn (array $state): ?string => $state['description'] ?? 'New Line Item')
                        ->collapsible()
                        ->cloneable()
                        ->defaultItems(1)
                ]),

                Forms\Components\Section::make('Grand Total')
                    ->schema([
                        Forms\Components\Placeholder::make('grand_total_sum')
                            ->label('Total')
                            ->content(function (Forms\Get $get) {
                                $lines = $get('lines') ?? [];
                                $sum = (float) collect($lines)->sum(fn ($i) => (float)($i['total'] ?? 0));
                                return new \Illuminate\Support\HtmlString('<div class="text-2xl font-mono font-bold text-primary-600">' . number_format($sum, 2) . ' <span class="text-xs font-normal text-gray-500">AED</span></div>');
                            }),
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
                Tables\Columns\TextColumn::make('entity')
                    ->label('Entity')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Bill Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('taxRegistration.name')
                    ->label('Supplier')
                    ->searchable(),

                Tables\Columns\TextColumn::make('price_type')
                    ->label('Price Type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'inclusive' ? 'warning' : 'info')
                    ->formatStateUsing(fn ($state) => $state === 'inclusive' ? 'VAT Inclusive' : 'VAT Exclusive')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Grand Total')
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
                            new \App\Exports\PurchaseEntriesExport($livewire->getFilteredTableQuery()),
                            'purchase_entries_' . now()->format('Y-m-d_His') . '.xlsx'
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
                                new \App\Exports\PurchaseEntriesExport(\App\Models\PurchaseEntry::whereIn('id', $ids)),
                                'selected_purchase_entries_' . now()->format('Y-m-d_His') . '.xlsx'
                            );
                        })->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseEntries::route('/'),
            'create' => Pages\CreatePurchaseEntry::route('/create'),
            'view' => Pages\ViewPurchaseEntry::route('/{record}'),
            'edit' => Pages\EditPurchaseEntry::route('/{record}/edit'),
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
                                \Filament\Infolists\Components\TextEntry::make('entity')
                                    ->label('Entity'),
                                \Filament\Infolists\Components\TextEntry::make('taxRegistration.name')
                                    ->label('Supplier'),
                                \Filament\Infolists\Components\TextEntry::make('invoice_no')
                                    ->label('Invoice No')
                                    ->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('date')
                                    ->label('Bill Date')
                                    ->date('M j, Y'),
                                \Filament\Infolists\Components\TextEntry::make('due_date')
                                    ->label('Due Date')
                                    ->date('M j, Y')
                                    ->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('po_number')
                                    ->label('PO Number')
                                    ->placeholder('—'),
                            ])->columns(4),
                    ]),

                \Filament\Infolists\Components\Section::make('Purchase Lines (Items)')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(12)
                            ->extraAttributes(['class' => 'bg-gray-100 p-2 border-b border-gray-200 rounded-t-lg'])
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('header_account')->state('Account')->hiddenLabel()->weight(\Filament\Support\Enums\FontWeight::Bold)->columnSpan(4),
                                \Filament\Infolists\Components\TextEntry::make('header_desc')->state('Description / Item')->hiddenLabel()->weight(\Filament\Support\Enums\FontWeight::Bold)->columnSpan(4),
                                \Filament\Infolists\Components\TextEntry::make('header_cc')->state('Branch')->hiddenLabel()->weight(\Filament\Support\Enums\FontWeight::Bold)->columnSpan(2),
                                \Filament\Infolists\Components\TextEntry::make('header_total')->state('Total Amount')->hiddenLabel()->weight(\Filament\Support\Enums\FontWeight::Bold)->columnSpan(2)->alignEnd(),
                            ]),

                        \Filament\Infolists\Components\RepeatableEntry::make('lines')
                            ->label('')
                            ->schema([
                                \Filament\Infolists\Components\Grid::make(12)
                                    ->schema([
                                        \Filament\Infolists\Components\TextEntry::make('debitAccount.code')
                                            ->label('Account')
                                            ->hiddenLabel()
                                            ->formatStateUsing(fn ($state, $record) => $state . ' — ' . $record->debitAccount?->name)
                                            ->columnSpan(4),
                                        \Filament\Infolists\Components\TextEntry::make('description')
                                            ->label('Description')
                                            ->hiddenLabel()
                                            ->columnSpan(4),
                                        \Filament\Infolists\Components\TextEntry::make('branch')
                                            ->label('Branch')
                                            ->hiddenLabel()
                                            ->placeholder('—')
                                            ->columnSpan(2),
                                        \Filament\Infolists\Components\TextEntry::make('total')
                                            ->label('Total')
                                            ->hiddenLabel()
                                            ->money('AED')
                                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                            ->extraAttributes(['class' => 'font-mono text-gray-900'])
                                            ->columnSpan(2)
                                            ->alignEnd(),
                                    ])
                            ])
                            ->columns(1)
                            ->contained(false)
                    ]),

                \Filament\Infolists\Components\Section::make('Purchase Summary')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(3)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('grand_total')
                                    ->label('Purchase Total')
                                    ->money('AED')
                                    ->extraAttributes(['class' => 'text-2xl font-mono font-bold text-primary-600 pl-4 border-l-4 border-primary-500']),
                            ])
                    ])->compact(),
            ]);
    }
}
