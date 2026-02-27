<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 1;

public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Section::make('Transaction Details')
                ->schema([
                    Forms\Components\Select::make('type')
                        ->options(function () {
                            $options = [
                                'EXPENSE' => 'Expense (Money Out)',
                            ];

                            if (auth()->user()->isHeadOffice()) {
                                $options['REPLENISHMENT'] = 'Replenishment (Money In)';
                            }

                            return $options;
                        })
                        ->required()
                        ->live() 
                        ->afterStateUpdated(fn (callable $set) => $set('category_id', null)) // Reset category if type changes
                        ->native(false),

                    // Category Select Removed - Moved to Items Repeater
                    
                    // Dedicated business date — separate from the system-managed created_at audit timestamp.
                    // This lets users set the actual date of the expense without corrupting the audit trail.
                    Forms\Components\DateTimePicker::make('transaction_date')
                        ->label('Date & Time')
                        ->default(now())
                        ->seconds(false)
                        ->required(),
                        
                    Forms\Components\TextInput::make('payee')
                        ->label('Paid To')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('supplier')
                        ->label('Supplier Name')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('trn')
                        ->label('TRN')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('reference_number')
                        ->label('Invoice/Reference #')
                        ->maxLength(255),
                ])->columns(['default' => 1, 'sm' => 2]),

                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('category_id')
                                ->relationship('category', 'name', function (Builder $query, callable $get) {
                                    return $query->where('is_active', true);
                                })
                                ->label('Category')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->visible(fn () => auth()->user()->isHeadOffice()), // Only HO can see/set
                            Forms\Components\TextInput::make('name')
                                ->required(),
                            Forms\Components\TextInput::make('quantity')
                                ->numeric()
                                ->default(1)
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, callable $set, callable $get) => $set('total_price', ((float) $state * (float) $get('unit_price')) + (float) $get('vat'))),
                            Forms\Components\TextInput::make('unit_price')
                                ->numeric()
                                ->default(0)
                                ->required()
                                ->prefix('AED')
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, callable $set, callable $get) => $set('total_price', ((float) $state * (float) $get('quantity')) + (float) $get('vat'))),

                            // Per-item VAT — only active when vat_mode = 'per_item'
                            Forms\Components\TextInput::make('vat')
                                ->label('VAT')
                                ->numeric()
                                ->default(0)
                                ->prefix('AED')
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, callable $set, callable $get) => $set('total_price', ((float) $get('quantity') * (float) $get('unit_price')) + (float) $state))
                                // Hidden & zeroed when receipt-level VAT mode is active
                                ->visible(fn (callable $get) => $get('../../vat_mode') !== 'receipt_total')
                                ->disabled(fn (callable $get) => $get('../../vat_mode') === 'receipt_total'),

                            Forms\Components\TextInput::make('total_price')
                                ->numeric()
                                ->readOnly()
                                ->prefix('AED')
                                ->default(0)
                                ->dehydrated(),
                        ])
                        ->columns(['default' => 1, 'md' => 3, 'lg' => 6]) // Progressive breakpoints for mobile/tablet/desktop
                        ->columnSpanFull()
                        ->live()
                        ->afterStateUpdated(function (callable $get, callable $set) {
                            $vatMode    = $get('vat_mode');
                            $items      = $get('items');
                            $itemTotal  = collect($items)->sum(fn($item) => ((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0)) + (float) ($item['vat'] ?? 0));
                            $globalVat  = ($vatMode === 'receipt_total') ? (float) $get('vat') : 0;
                            $set('amount', $itemTotal + $globalVat);
                        }),

                    // ── VAT Mode Selector ─────────────────────────────────────────────────────
                    // Determines which VAT field is active. The two modes are MUTUALLY EXCLUSIVE
                    // to prevent accidental double-counting of VAT.
                    Forms\Components\ToggleButtons::make('vat_mode')
                        ->label('VAT Method')
                        ->helperText('Choose how VAT is applied. You cannot use both at the same time.')
                        ->options([
                            'per_item'      => 'Per Item',
                            'receipt_total' => 'Receipt Total',
                        ])
                        ->icons([
                            'per_item'      => 'heroicon-o-list-bullet',
                            'receipt_total' => 'heroicon-o-receipt-percent',
                        ])
                        ->colors([
                            'per_item'      => 'info',
                            'receipt_total' => 'warning',
                        ])
                        ->inline()
                        ->default('per_item')
                        ->live()
                        ->dehydrated(false) // UI-only toggle — not persisted to the DB
                        ->afterStateUpdated(function (callable $get, callable $set, string $state) {
                            // When switching modes, zero out the opposite VAT field and recalculate
                            if ($state === 'receipt_total') {
                                // Zero out all per-item VAT values
                                $items = $get('items') ?? [];
                                foreach ($items as $key => $item) {
                                    $set("items.{$key}.vat", 0);
                                    $set("items.{$key}.total_price", ((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0)));
                                }
                            } else {
                                // Zero out the receipt-level VAT field
                                $set('vat', 0);
                            }

                            // Recalculate Grand Total
                            $items     = $get('items') ?? [];
                            $itemTotal = collect($items)->sum(fn($item) => ((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0)) + (float) ($item['vat'] ?? 0));
                            $globalVat = ($state === 'receipt_total') ? (float) $get('vat') : 0;
                            $set('amount', $itemTotal + $globalVat);
                        })
                        ->columnSpanFull(),

                    Forms\Components\Group::make()
                        ->schema([
                            // Receipt-level VAT — only active when vat_mode = 'receipt_total'
                            Forms\Components\TextInput::make('vat')
                                ->label('VAT (Receipt Total)')
                                ->helperText('A single VAT amount applied to the entire receipt — not per line item.')
                                ->numeric()
                                ->default(0)
                                ->prefix('AED')
                                ->live(onBlur: true)
                                ->visible(fn (callable $get) => $get('vat_mode') === 'receipt_total')
                                ->afterStateUpdated(function (callable $get, callable $set) {
                                    $items     = $get('items') ?? [];
                                    $itemTotal = collect($items)->sum(fn($item) => ((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0)));
                                    $globalVat = (float) $get('vat');
                                    $set('amount', $itemTotal + $globalVat);
                                }),

                            Forms\Components\TextInput::make('amount')
                                ->label('Grand Total')
                                ->numeric()
                                ->prefix('AED')
                                ->readOnly()
                                ->required()
                                ->live()
                                ->default(0)
                                ->dehydrated()
                                ->rule(function (callable $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $type = $get('type');
                                        $branchId = $get('branch_id') ?? auth()->user()->branch_id;
                                        
                                        if (!$branchId) return;

                                        $branch = \App\Models\Branch::find($branchId);

                                        // Rule 1: Replenishment -> No limits
                                        if ($type === 'REPLENISHMENT') return;

                                        // Rule 2: Expense -> Check Balance
                                        if ($value > $branch->current_balance) {
                                            $fail("Insufficient funds! The branch only has AED {$branch->current_balance}.");
                                        }

                                        // Rule 3: Expense -> Check Transaction Limit
                                        if ($branch->transaction_limit && $value > $branch->transaction_limit) {
                                            $fail("Amount exceeds the branch transaction limit of AED {$branch->transaction_limit}.");
                                        }
                                    };
                                }),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('description')
                        ->label('Remarks/Description')
                        ->placeholder('Additional notes...')
                        ->columnSpanFull(),

            Forms\Components\Textarea::make('edit_reason')
                ->label('Reason for Edit')
                ->helperText('Please explain why you are modifying this record.')
                ->required(fn (string $operation, ?Transaction $record): bool => $operation === 'edit' && (auth()->user()->branch_id !== null || $record?->status !== 'pending'))
                ->visible(fn (string $operation): bool => $operation === 'edit')
                // ->dehydrated(false) // REMOVED: We need this data in the EditPage logic! We will manually unset it there.
                ->columnSpanFull(),

            // Accounting Remarks REMOVED

            Forms\Components\FileUpload::make('receipt_path')
                ->label('Upload Receipt')
                ->directory('receipts')
                ->disk('local')
                ->visibility('private')
                ->acceptedFileTypes(['image/*', 'application/pdf'])
                ->openable()
                ->downloadable()
                ->required(fn (string $operation) => $operation === 'create'),

            // Logic: If user is HQ, they can pick a branch. If Branch User, it's auto-set.
            // NOTE: user_id is set server-side in CreateTransaction::mutateFormDataBeforeCreate()
            Forms\Components\Select::make('branch_id')
                ->relationship('branch', 'name')
                ->hidden(fn () => auth()->user()->branch_id !== null) // Hide if normal user
                ->default(auth()->user()->branch_id)
                ->placeholder('Head Office')
                ->required(),
        ]);
}

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Transaction Overview')
                    ->schema([
                        Infolists\Components\Split::make([
                            Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\Group::make([
                                        Infolists\Components\TextEntry::make('transaction_date')
                                            ->dateTime('M j, Y h:i A')
                                            ->label('Transaction Date'),
                                        Infolists\Components\TextEntry::make('status')
                                            ->badge()
                                            ->color(fn (TransactionStatus $state): string => $state->color()),
                                    ]),
                                    Infolists\Components\Group::make([
                                        Infolists\Components\TextEntry::make('type')
                                            ->badge(),
                                        Infolists\Components\TextEntry::make('vat')
                                            ->money('AED')
                                            ->label('Global VAT')
                                            ->visible(fn ($state) => $state > 0),
                                        Infolists\Components\TextEntry::make('amount')
                                            ->money('AED')
                                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                            ->extraAttributes(['class' => 'privacy-mask']),
                                    ]),
                                    Infolists\Components\TextEntry::make('branch.name')
                                        ->label('Branch')
                                        ->placeholder('Head Office'),
                                    // Category removed from detailed view as it's now per-item
                                ]),
                        ])->from('md'),
                    ]),

                Infolists\Components\Section::make('Payee & Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('payee')
                            ->label('Paid To'),
                        Infolists\Components\TextEntry::make('supplier')
                            ->label('Supplier'),
                        Infolists\Components\TextEntry::make('trn')
                            ->label('TRN'),
                        Infolists\Components\TextEntry::make('reference_number')
                            ->label('Reference #'),
                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ])->columns(['default' => 1, 'sm' => 2]),

                Infolists\Components\Section::make('Line Items')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->schema([
                                Infolists\Components\TextEntry::make('category.name')
                                    ->label('Category')
                                    ->default('N/A'),
                                Infolists\Components\TextEntry::make('name')->label('Item'),
                                Infolists\Components\TextEntry::make('quantity')->label('Qty'),
                                Infolists\Components\TextEntry::make('unit_price')->money('AED')->label('Unit Price'),
                                Infolists\Components\TextEntry::make('vat')->money('AED')->label('VAT'),
                                Infolists\Components\TextEntry::make('total_price')->money('AED')->label('Total'),
                            ])
                            ->columns(5),
                    ]),

                Infolists\Components\Section::make('Receipt')
                    ->schema([
                        Infolists\Components\ImageEntry::make('receipt_path')
                            ->label('')
                            ->disk('local')
                            ->visibility('private')
                            ->width('100%')
                            ->height('auto')
                            ->visible(fn ($state) => $state && !str_ends_with(strtolower($state), '.pdf')),
                            
                        Infolists\Components\TextEntry::make('receipt_path')
                            ->label('')
                            ->formatStateUsing(fn () => 'View PDF Receipt')
                            ->url(fn ($record) => route('transaction.receipt', $record))
                            ->openUrlInNewTab()
                            ->icon('heroicon-o-document-text')
                            ->color('primary')
                            ->visible(fn ($state) => $state && str_ends_with(strtolower($state), '.pdf')),
                    ])
                    ->collapsible(),
                    
                // Accounting Section Removed
            ]);
    }

public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('transaction_date')
                ->label('Date')
                ->dateTime('M j, Y h:i A')
                ->sortable(),
            Tables\Columns\TextColumn::make('created_at')
                ->label('Recorded At')
                ->dateTime('M j, Y h:i A')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true)
                ->tooltip('System timestamp — when the record was actually entered'),
                // Category Column Removed
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (TransactionStatus $state): string => $state->color())
                    ->icon(fn (TransactionStatus $state): string => $state->icon())
                    ->tooltip(fn (Transaction $record): ?string => $record->status === TransactionStatus::Rejected ? $record->rejection_reason : null)
                    ->sortable(),
            Tables\Columns\TextColumn::make('type')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'EXPENSE' => 'danger', // Red for money out
                    'REPLENISHMENT' => 'success', // Green for money in
                }),
            Tables\Columns\TextColumn::make('amount')
                ->money('AED')
                ->extraAttributes(['class' => 'privacy-mask']),
            Tables\Columns\TextColumn::make('payee')
                ->searchable(),
            Tables\Columns\TextColumn::make('supplier')
                ->label('Supplier')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('reference_number')
                ->label('Ref #')
                ->searchable(),
            Tables\Columns\IconColumn::make('receipt_path')
                ->label('Receipt')
                ->boolean()
                ->trueIcon('heroicon-o-document-check')
                ->falseIcon('heroicon-o-x-mark')
                ->color(fn ($state) => $state ? 'success' : 'gray'),
            Tables\Columns\TextColumn::make('branch.name')
                ->label('Branch')
                ->placeholder('Head Office')
                ->sortable(),
        ])
        ->defaultSort('created_at', 'desc')
        ->filters([
            Tables\Filters\SelectFilter::make('status')
                ->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
            // Category Filter Removed
            
            Tables\Filters\SelectFilter::make('branch')
                ->relationship('branch', 'name')
                ->visible(fn () => auth()->user()->branch_id === null) // Only visible to HQ
                ->searchable()
                ->preload(),

            Tables\Filters\Filter::make('created_at')
                ->form([
                    Forms\Components\DatePicker::make('created_from'),
                    Forms\Components\DatePicker::make('created_until'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['created_from'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                        )
                        ->when(
                            $data['created_until'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                        );
                }),
        ])
        ->actions([
            Tables\Actions\ViewAction::make()
                ->slideOver() // Nice preview effect
                ->color('info')
                ->icon('heroicon-o-eye'),
            Tables\Actions\ActionGroup::make([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Transaction $record) => $record->status === TransactionStatus::Pending && auth()->user()->branch_id === null)
                    ->action(function (Transaction $record) {
                        // Server-side guard: visible() only hides the UI, it does NOT block direct Livewire calls.
                        abort_unless(auth()->user()->isHeadOffice(), 403, 'Only HQ staff can approve transactions.');
                        abort_unless($record->status === TransactionStatus::Pending, 403, 'Only pending transactions can be approved.');

                        $record->update(['status' => TransactionStatus::Approved->value]);
                        \Filament\Notifications\Notification::make()
                            ->title('Transaction Approved')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Transaction $record) => in_array($record->status, [TransactionStatus::Pending, TransactionStatus::Approved]) && auth()->user()->branch_id === null)
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required()
                            ->maxLength(65535),
                    ])
                    ->action(function (Transaction $record, array $data) {
                        // Server-side guard: visible() only hides the UI, it does NOT block direct Livewire calls.
                        abort_unless(auth()->user()->isHeadOffice(), 403, 'Only HQ staff can reject transactions.');
                        abort_unless(in_array($record->status, [TransactionStatus::Pending, TransactionStatus::Approved]), 403, 'This transaction cannot be rejected in its current state.');

                        $originalData = $record->fresh()->toArray(); // Capture before mutation

                        $record->update([
                            'status'           => TransactionStatus::Rejected->value,
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        // Log History — original_data must not be null for a complete audit trail
                        \App\Models\TransactionHistory::create([
                            'transaction_id' => $record->id,
                            'user_id'        => auth()->id(),
                            'reason'         => 'Transaction Rejected: ' . $data['rejection_reason'],
                            'original_data'  => $originalData,
                            'modified_data'  => $record->fresh()->toArray(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Transaction Rejected')
                            ->danger()
                            ->send();
                    }),

                Tables\Actions\Action::make('download_receipt')
                    ->label('Receipt')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Transaction $record) => $record->receipt_path !== null)
                    ->action(function (Transaction $record) {
                        $path = $record->receipt_path;
                        
                        // Check 'local' disk (New secure files)
                        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
                            return response()->download(\Illuminate\Support\Facades\Storage::disk('local')->path($path));
                        }

                        // Check 'public' disk (Legacy insecure files)
                        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                            return response()->download(\Illuminate\Support\Facades\Storage::disk('public')->path($path));
                        }

                        // File not found
                        \Filament\Notifications\Notification::make()
                            ->title('File Not Found')
                            ->body('The receipt file could not be located.')
                            ->danger()
                            ->send();
                    }),

                Tables\Actions\Action::make('print')
                    ->label('Print Voucher')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Transaction $record) => route('transaction.print', $record))
                    ->openUrlInNewTab(),
                    
                Tables\Actions\Action::make('history')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->modalHeading('Transaction History')
                    ->modalContent(fn ($record) => view('filament.components.transaction-history', ['histories' => $record->histories()->with('user')->latest()->get()]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                // Accounting Specs Action Removed

                Tables\Actions\EditAction::make()
                    ->slideOver(),
                Tables\Actions\DeleteAction::make()
                    ->label('Void')
                    ->modalHeading('Void Transaction')
                    ->modalDescription('Are you sure you want to void this transaction? This action cannot be undone by branch staff.')
                    ->modalSubmitActionLabel('Void')
                    ->visible(fn () => auth()->user()->branch_id === null), // HQ only
            ]),
        ])
            ->headerActions([
                // Tables\Actions\Action::make('import'), // Removed for security
                    
                    
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(), // Removed for security
                    Tables\Actions\BulkAction::make('export_selected')
                    ->label('Export Selected')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        return response()->streamDownload(function () use ($records) {
                            $file = fopen('php://output', 'w');
                            
                            // 1. Define Headers
                            $headers = [
                                'ID', 'Date', 'Type', 'Amount', 'Payee', 'Supplier', 'TRN', 
                                'Reference #', 'Description', 'Items', 'Branch', 'Category', 
                                'Status', 'Created By', 'Receipt URL'
                            ];
                            
                            // header accounting remarks removed
                            
                            fputcsv($file, $headers);
                            
                            // 2. Export Loop
                            foreach ($records as $record) {
                                $itemsSummary = $record->items->map(fn($item) => "{$item->name} (x{$item->quantity})")->join(', ');
                                
                                $row = [
                                    $record->id,
                                    $record->created_at->format('Y-m-d H:i'),
                                    $record->type,
                                    (float) $record->amount,
                                    $record->payee,
                                    $record->supplier,
                                    $record->trn,
                                    $record->reference_number,
                                    $record->description,
                                    $itemsSummary,
                                    $record->branch ? $record->branch->name : 'Head Office',
                                    // Get Unique Categories from Items
                                    $record->items->map(fn($item) => $item->category?->name)->filter()->unique()->join(', ') ?: 'N/A',
                                    $record->status,
                                    $record->user ? $record->user->name : 'Unknown',
                                    $record->receipt_path ? route('transaction.receipt', $record) : '',
                                ];
                                
                                
                                // row accounting remarks removed
                                
                                fputcsv($file, $row);
                            }
                            
                            fclose($file);
                        }, 'transactions_export_' . now()->format('Y-m-d_H-i-s') . '.csv');
                    }),
            ]),
        ]);
}

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
    public static function getEloquentQuery(): Builder
    {
        // Note: 'category' is NOT eager-loaded — Transaction has no direct category relation.
        // Categories live on transaction_items, hence 'items.category'.
        $query = parent::getEloquentQuery()
            ->with(['branch', 'user', 'items.category']);

        if (auth()->user()->branch_id) {
            $query->where('branch_id', auth()->user()->branch_id);
        }

        return $query;
    }
}
