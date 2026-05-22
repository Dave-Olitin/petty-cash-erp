<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\PurchaseEntryResource\Pages;
use App\Models\PurchaseEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Components\Tab;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseEntryResource extends Resource
{
    protected static ?string $model = PurchaseEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Accounting';
    protected static ?string $navigationLabel = 'Purchases & Returns';
    protected static ?int $navigationSort = 4;

    // ── Permissions ───────────────────────────────────────────────────────

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

    // ── Eager-load ────────────────────────────────────────────────────────

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with([
            'taxRegistration',
            'lines.debitAccount',
            'lines.creditAccount',
            'user',
        ]);
    }

    // ── Form ─────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ── Entry Type Banner ─────────────────────────────────────
                Forms\Components\Section::make('Entry Type')
                    ->schema([
                        Forms\Components\Select::make('entry_type')
                            ->label('Type')
                            ->options([
                                'purchase' => '🛒 Purchase Bill — Normal supplier invoice (increases AP)',
                                'return'   => '↩ Purchase Return — Debit note to supplier (reduces AP)',
                            ])
                            ->default('purchase')
                            ->required()
                            ->live()
                            ->native(false)
                            ->helperText(fn (Forms\Get $get) => $get('entry_type') === 'return'
                                ? new \Illuminate\Support\HtmlString('<span class="text-xs text-amber-600 font-semibold">⚠ A Purchase Return reverses the original bill. The grand total will reduce the supplier balance.</span>')
                                : null
                            ),
                    ])->columns(1)->compact(),

                // ── Purchase Bill Details ─────────────────────────────────
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
                        ->relationship('taxRegistration', 'name', fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('is_active', true))
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

                    // ── Dates ────────────────────────────────────────────
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

                // ── Supplier Reference ────────────────────────────────────
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

                // ── Entry Lines ───────────────────────────────────────────
                Forms\Components\Section::make('Entry Lines')->schema([
                    Forms\Components\Repeater::make('lines')
                        ->relationship('lines')
                        ->live()
                        ->schema([
                            Forms\Components\Grid::make(12)
                                ->schema([
                                    // ── ROW 1: Context ─────────────────────
                                    Forms\Components\Grid::make(12)
                                        ->schema([
                                            Forms\Components\TextInput::make('description')
                                                ->label('Item Description')
                                                ->placeholder('Nature of expense / item name...')
                                                ->required()
                                                ->columnSpan(8),

                                            Forms\Components\Select::make('branch')
                                                ->label('Branch')
                                                ->options(\App\Models\LedgerBranch::pluck('name', 'name'))
                                                ->searchable()
                                                ->preload()
                                                ->placeholder('Select Branch')
                                                ->columnSpan(4),
                                        ])
                                        ->columnSpanFull(),

                                    // ── ROW 2: Accounting ──────────────────
                                    Forms\Components\Grid::make(12)
                                        ->schema([
                                            // ── Expense/Item Account ──────────────
                                            Forms\Components\Select::make('debit_account_id')
                                                ->relationship('debitAccount', 'code')
                                                ->label('Account')
                                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->code . ' — ' . $record->name)
                                                ->searchable(['code', 'name'])
                                                ->native(false)
                                                ->required()
                                                ->columnSpan(6),

                                            // ── DR Amount ───────────────────────
                                            Forms\Components\TextInput::make('debit')
                                                ->label('Debit Amount')
                                                ->numeric()
                                                ->default(0)
                                                ->live(onBlur: true)
                                                ->rules([
                                                    fn (Forms\Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                                        $debit = (float) $value;
                                                        $credit = (float) $get('credit');
                                                        if ($debit <= 0 && $credit <= 0) {
                                                            $fail('Either Debit Amount or Credit Amount must be greater than 0.');
                                                        }
                                                    },
                                                ])
                                                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                                    $debit = (float) $state;
                                                    if ($debit > 0) {
                                                        $set('total', $debit);
                                                        $set('amount', $debit);
                                                        $set('tax_amount', 0);
                                                    }
                                                })
                                                ->prefix('DR')
                                                ->prefixIcon('heroicon-m-plus-circle')
                                                ->prefixIconColor('success')
                                                ->extraInputAttributes(['class' => 'font-bold text-success-600'])
                                                ->columnSpan(3),

                                            // ── CR Amount ───────────────────────
                                            Forms\Components\TextInput::make('credit')
                                                ->label('Credit Amount')
                                                ->numeric()
                                                ->default(0)
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                                    $credit = (float) $state;
                                                    if ($credit > 0 && (float) $get('debit') === 0.0) {
                                                        $set('total', $credit);
                                                        $set('amount', $credit);
                                                        $set('tax_amount', 0);
                                                    }
                                                })
                                                ->prefix('CR')
                                                ->prefixIcon('heroicon-m-minus-circle')
                                                ->prefixIconColor('danger')
                                                ->extraInputAttributes(['class' => 'font-bold text-danger-600'])
                                                ->columnSpan(3),
                                        ])
                                        ->columnSpanFull(),

                                    // ── Line Total (hidden fallback) ─────
                                    Forms\Components\TextInput::make('total')
                                        ->label('Total Amount')
                                        ->numeric()
                                        ->default(0)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                            $total = (float) $state;
                                            $set('amount', $total);
                                            $set('tax_amount', 0);
                                            if ((float) $get('credit') === 0.0) {
                                                $set('debit', $total);
                                            }
                                        })
                                        ->prefix('AED')
                                        ->extraInputAttributes(['class' => 'font-bold text-primary-600'])
                                        ->hidden(),

                                    // Hidden fields kept for data integrity
                                    Forms\Components\Hidden::make('amount')->default(0),
                                    Forms\Components\Hidden::make('tax_percentage')->default(0),
                                    Forms\Components\Hidden::make('tax_amount')->default(0),
                                ])
                        ])
                        ->itemLabel(fn (array $state): ?string => $state['description'] ?? 'New Line Item')
                        ->collapsible()
                        ->cloneable()
                        ->defaultItems(1)
                ]),

                // ── Totals & Balance ──────────────────────────────────────
                Forms\Components\Section::make('Totals & Balance')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('total_debit_sum')
                                    ->label('Total Debit (DR)')
                                    ->content(function (Forms\Get $get) {
                                        $lines = $get('lines') ?? [];
                                        $sum = (float) collect($lines)->sum(fn ($i) => (float)($i['debit'] ?? 0));
                                        return new \Illuminate\Support\HtmlString(
                                            '<div class="flex flex-col">' .
                                            '<span class="text-2xl font-mono font-bold text-success-600">' . number_format($sum, 2) . '</span>' .
                                            '<span class="text-[10px] uppercase tracking-wider text-gray-400">Total DR — AED</span>' .
                                            '</div>'
                                        );
                                    }),

                                Forms\Components\Placeholder::make('total_credit_sum')
                                    ->label('Total Credit (CR)')
                                    ->content(function (Forms\Get $get) {
                                        $lines = $get('lines') ?? [];
                                        $sum = (float) collect($lines)->sum(fn ($i) => (float)($i['credit'] ?? 0));
                                        return new \Illuminate\Support\HtmlString(
                                            '<div class="flex flex-col">' .
                                            '<span class="text-2xl font-mono font-bold text-danger-600">' . number_format($sum, 2) . '</span>' .
                                            '<span class="text-[10px] uppercase tracking-wider text-gray-400">Total CR — AED</span>' .
                                            '</div>'
                                        );
                                    }),

                                Forms\Components\Placeholder::make('grand_total_sum')
                                    ->label('Grand Total (Invoice)')
                                    ->content(function (Forms\Get $get) {
                                        $lines = $get('lines') ?? [];
                                        // Use max(debit, credit, total) per line — matches model logic.
                                        // A purchase entry line typically has ONLY debit filled (expense DR),
                                        // with the AP credit posted separately. So debit IS the line total.
                                        $sum = (float) collect($lines)->sum(function ($i) {
                                            $debit  = (float)($i['debit']  ?? 0);
                                            $credit = (float)($i['credit'] ?? 0);
                                            $total  = (float)($i['total']  ?? 0);
                                            return max($debit, $credit, $total);
                                        });
                                        $isReturn = $get('entry_type') === 'return';

                                        return new \Illuminate\Support\HtmlString(
                                            '<div class="flex flex-col p-3 rounded-xl bg-gray-50 border border-gray-100 dark:bg-gray-800 dark:border-gray-700">' .
                                            '<div class="flex items-center gap-2">' .
                                            ($isReturn ? '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700 uppercase">Return</span>' : '') .
                                            '<span class="text-3xl font-mono font-black text-primary-600">' . number_format($sum, 2) . '</span>' .
                                            '</div>' .
                                            '<span class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold mt-1">Invoice Total — AED</span>' .
                                            '</div>'
                                        );
                                    }),
                            ]),

                        // ── Informational balance note ────────────────────────────────────
                        // For Purchase Entries, DR ≠ CR is NORMAL until payment is made.
                        // The Debit side records the expense; the Credit (AP) is posted
                        // later when the supplier payment voucher is raised.
                        // This indicator simply shows if you have chosen to post both sides.
                        Forms\Components\Placeholder::make('entry_balance')
                            ->label('')
                            ->content(function (Forms\Get $get) {
                                $lines = $get('lines') ?? [];
                                $dr = (float) collect($lines)->sum(fn ($i) => (float)($i['debit'] ?? 0));
                                $cr = (float) collect($lines)->sum(fn ($i) => (float)($i['credit'] ?? 0));
                                $diff = round(abs($dr - $cr), 2);

                                if ($dr === 0.0 && $cr === 0.0) return null;

                                if ($diff <= 0.01) {
                                    return new \Illuminate\Support\HtmlString(
                                        '<div class="flex items-center gap-2 text-xs text-success-700 bg-success-50 px-3 py-1.5 rounded-lg border border-success-200 w-fit">' .
                                        '<svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>' .
                                        '<span class="font-semibold">Double-entry balanced — DR = CR</span>' .
                                        '</div>'
                                    );
                                }

                                // DR ≠ CR is fine for simple purchase bills — show as info, not error
                                return new \Illuminate\Support\HtmlString(
                                    '<div class="flex items-center gap-2 text-xs text-blue-700 bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-200 w-fit">' .
                                    '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' .
                                    '<span class="font-semibold">Single-side entry (DR ' . ($dr > $cr ? '>' : '<') . ' CR). The Accounts Payable credit will be posted when the supplier payment is made — this is normal.</span>' .
                                    '</div>'
                                );
                            })
                    ])->compact(),


                // ── Payment Status ────────────────────────────────────────
                Forms\Components\Section::make('Payment Status')
                    ->description('Track how much of this bill has been paid.')
                    ->schema([
                        Forms\Components\Select::make('payment_status')
                            ->label('Status')
                            ->options([
                                'unpaid'  => '🔴 Unpaid',
                                'partial' => '🟡 Partially Paid',
                                'paid'    => '🟢 Fully Paid',
                            ])
                            ->default('unpaid')
                            ->native(false)
                            ->live(),

                        Forms\Components\TextInput::make('amount_paid')
                            ->label('Amount Paid')
                            ->numeric()
                            ->default(0)
                            ->prefix('AED')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                $paid  = (float) $state;
                                $lines = $get('lines') ?? [];
                                $total = (float) collect($lines)->sum(fn ($i) => max((float)($i['debit'] ?? 0), (float)($i['total'] ?? 0)));
                                $balance = max(0, $total - $paid);
                                $set('balance_due', $balance);
                                if ($paid <= 0) {
                                    $set('payment_status', 'unpaid');
                                } elseif ($paid < $total) {
                                    $set('payment_status', 'partial');
                                } else {
                                    $set('payment_status', 'paid');
                                }
                            })
                            ->visible(fn (Forms\Get $get) => $get('payment_status') !== 'unpaid'),

                        Forms\Components\Placeholder::make('balance_due_display')
                            ->label('Balance Due')
                            ->content(function (Forms\Get $get) {
                                $bal = (float) $get('balance_due');
                                $color = $bal > 0 ? 'text-red-600' : 'text-green-600';
                                return new \Illuminate\Support\HtmlString('<div class="text-lg font-mono font-bold ' . $color . '">' . number_format($bal, 2) . ' <span class="text-xs font-normal text-gray-500">AED</span></div>');
                            })
                            ->visible(fn (Forms\Get $get) => $get('payment_status') !== 'unpaid'),

                        Forms\Components\Hidden::make('balance_due'),
                    ])
                    ->columns(3)
                    ->collapsed(fn ($context) => $context === 'create'),
            ]);
    }

    // ── Table ─────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entry_no')
                    ->label('Entry No.')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('entry_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'return' ? 'PURCHASE RETURN' : 'PURCHASE BILL')
                    ->color(fn ($state) => $state === 'return' ? 'warning' : 'info')
                    ->extraAttributes(['class' => 'font-bold tracking-tighter']),

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
                    ->color(function ($record) {
                        if ($record->payment_status === 'paid') return 'success';
                        if ($record->due_date && $record->due_date->isPast()) return 'danger';
                        return null;
                    }),

                Tables\Columns\TextColumn::make('taxRegistration.name')
                    ->label('Supplier')
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Custodian')
                    ->placeholder('System')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'paid'    => 'PAID',
                        'partial' => 'PARTIAL',
                        default   => 'UNPAID',
                    })
                    ->color(fn ($state) => match ($state) {
                        'paid'    => 'success',
                        'partial' => 'warning',
                        default   => 'danger',
                    })
                    ->extraAttributes(['class' => 'font-bold tracking-tighter']),

                Tables\Columns\TextColumn::make('aging_bucket')
                    ->label('Aging')
                    ->badge()
                    ->color(fn ($record) => $record->aging_color)
                    ->sortable(false),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Grand Total')
                    ->getStateUsing(fn ($record) => $record->isReturn() ? -(float)$record->grand_total : (float)$record->grand_total)
                    ->money('AED')
                    ->sortable()
                    ->color(fn ($record) => $record->isReturn() ? 'warning' : null)
                    ->weight('bold')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('balance_due')
                    ->label('Balance Due')
                    ->getStateUsing(fn ($record) => $record->isReturn() ? -(float)$record->balance_due : (float)$record->balance_due)
                    ->money('AED')
                    ->sortable()
                    ->color(fn ($record) => $record->isReturn() ? 'warning' : ((float) $record->balance_due > 0 ? 'danger' : 'success'))
                    ->weight('bold')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('price_type')
                    ->label('Price Type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'inclusive' ? 'warning' : 'info')
                    ->formatStateUsing(fn ($state) => $state === 'inclusive' ? 'VAT Inclusive' : 'VAT Exclusive')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // ── Date Range ──────────────────────────────────────────
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from'),
                        Forms\Components\DatePicker::make('date_until'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when($data['date_from'],  fn ($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['date_until'], fn ($q, $date) => $q->whereDate('date', '<=', $date));
                    }),

                // ── Payment Status ──────────────────────────────────────
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        'unpaid'  => 'Unpaid',
                        'partial' => 'Partially Paid',
                        'paid'    => 'Fully Paid',
                    ]),

                // ── Aging Bucket ────────────────────────────────────────
                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue Only')
                    ->query(fn ($query) => $query->overdue()),

                // ── Entry Type ──────────────────────────────────────────
                Tables\Filters\SelectFilter::make('entry_type')
                    ->label('Entry Type')
                    ->options([
                        'purchase' => 'Purchase Bills',
                        'return'   => 'Purchase Returns',
                    ]),

                // ── Supplier ────────────────────────────────────────────
                Tables\Filters\SelectFilter::make('tax_registration_id')
                    ->label('Supplier')
                    ->relationship('taxRegistration', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                \Filament\Tables\Actions\Action::make('aging_report')
                    ->label('Aging Report')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->url(fn () => \App\Filament\Vouchers\Pages\AgingReportPage::getUrl()),

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

                    Tables\Actions\BulkAction::make('mark_paid')
                        ->label('Mark as Paid')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $records->each(fn ($r) => $r->update([
                                'payment_status' => 'paid',
                                'amount_paid'    => $r->grand_total,
                                'balance_due'    => 0,
                            ]));
                        }),
                ]),
            ]);
    }

    // ── Tabs ─────────────────────────────────────────────────────────────

    public static function getTabs(): array
    {
        return [
            'all'      => Tab::make('All Entries'),
            'purchase' => Tab::make('Purchase Bills')
                ->modifyQueryUsing(fn ($query) => $query->purchases()),
            'return'   => Tab::make('Purchase Returns')
                ->modifyQueryUsing(fn ($query) => $query->returns()),
            'unpaid'   => Tab::make('Unpaid / Overdue')
                ->modifyQueryUsing(fn ($query) => $query->unpaid()),
        ];
    }

    // ── Pages ─────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPurchaseEntries::route('/'),
            'create' => Pages\CreatePurchaseEntry::route('/create'),
            'view'   => Pages\ViewPurchaseEntry::route('/{record}'),
            'edit'   => Pages\EditPurchaseEntry::route('/{record}/edit'),
        ];
    }

    // ── Infolist (View Page) ──────────────────────────────────────────────

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('General Information')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(5)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('entry_no')
                                    ->label('Entry Number')
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                    ->copyable(),
                                \Filament\Infolists\Components\TextEntry::make('entry_type')
                                    ->label('Type')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state === 'return' ? 'PURCHASE RETURN' : 'PURCHASE BILL')
                                    ->color(fn ($state) => $state === 'return' ? 'warning' : 'info')
                                    ->extraAttributes(['class' => 'font-bold tracking-tighter']),
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
                                \Filament\Infolists\Components\TextEntry::make('payment_status')
                                    ->label('Payment Status')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => match ($state) {
                                        'paid'    => 'PAID',
                                        'partial' => 'PARTIAL',
                                        default   => 'UNPAID',
                                    })
                                    ->color(fn ($state) => match ($state) {
                                        'paid'    => 'success',
                                        'partial' => 'warning',
                                        default   => 'danger',
                                    })
                                    ->extraAttributes(['class' => 'font-bold tracking-tighter']),
                                \Filament\Infolists\Components\TextEntry::make('aging_bucket')
                                    ->label('Aging')
                                    ->badge()
                                    ->color(fn ($record) => $record->aging_color),
                            ])->columns(5),
                    ]),

                \Filament\Infolists\Components\Section::make('Purchase Lines (Items)')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(14)
                            ->extraAttributes(['class' => 'bg-gray-100 p-2 border-b border-gray-200 rounded-t-lg'])
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('header_dr_acct')->state('Debit Account (DR)')->hiddenLabel()->weight(\Filament\Support\Enums\FontWeight::Bold)->columnSpan(3),
                                \Filament\Infolists\Components\TextEntry::make('header_cr_acct')->state('Credit Account (CR)')->hiddenLabel()->weight(\Filament\Support\Enums\FontWeight::Bold)->columnSpan(3),
                                \Filament\Infolists\Components\TextEntry::make('header_desc')->state('Description / Item')->hiddenLabel()->weight(\Filament\Support\Enums\FontWeight::Bold)->columnSpan(3),
                                \Filament\Infolists\Components\TextEntry::make('header_cc')->state('Branch')->hiddenLabel()->weight(\Filament\Support\Enums\FontWeight::Bold)->columnSpan(1),
                                \Filament\Infolists\Components\TextEntry::make('header_dr')->state('DR Amount')->hiddenLabel()->weight(\Filament\Support\Enums\FontWeight::Bold)->columnSpan(2)->alignEnd(),
                                \Filament\Infolists\Components\TextEntry::make('header_cr')->state('CR Amount')->hiddenLabel()->weight(\Filament\Support\Enums\FontWeight::Bold)->columnSpan(2)->alignEnd(),
                            ]),

                        \Filament\Infolists\Components\RepeatableEntry::make('lines')
                            ->label('')
                            ->schema([
                                \Filament\Infolists\Components\Grid::make(14)
                                    ->schema([
                                        \Filament\Infolists\Components\TextEntry::make('debitAccount.code')
                                            ->label('Debit Account')
                                            ->hiddenLabel()
                                            ->formatStateUsing(fn ($state, $record) => $state ? $state . ' — ' . $record->debitAccount?->name : '—')
                                            ->columnSpan(3),
                                        \Filament\Infolists\Components\TextEntry::make('creditAccount.code')
                                            ->label('Credit Account')
                                            ->hiddenLabel()
                                            ->formatStateUsing(fn ($state, $record) => $state ? $state . ' — ' . $record->creditAccount?->name : '—')
                                            ->placeholder('—')
                                            ->columnSpan(3),
                                        \Filament\Infolists\Components\TextEntry::make('description')
                                            ->label('Description')
                                            ->hiddenLabel()
                                            ->columnSpan(3),
                                        \Filament\Infolists\Components\TextEntry::make('branch')
                                            ->label('Branch')
                                            ->hiddenLabel()
                                            ->placeholder('—')
                                            ->columnSpan(1),
                                        \Filament\Infolists\Components\TextEntry::make('debit')
                                            ->label('DR')
                                            ->hiddenLabel()
                                            ->money('AED')
                                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                            ->extraAttributes(['class' => 'font-mono text-blue-700'])
                                            ->columnSpan(2)
                                            ->alignEnd(),
                                        \Filament\Infolists\Components\TextEntry::make('credit')
                                            ->label('CR')
                                            ->hiddenLabel()
                                            ->money('AED')
                                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                            ->extraAttributes(['class' => 'font-mono text-amber-700'])
                                            ->columnSpan(2)
                                            ->alignEnd(),
                                    ])
                            ])
                            ->columns(1)
                            ->contained(false)
                    ]),

                \Filament\Infolists\Components\Section::make('Purchase Summary')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(4)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('total_debit')
                                    ->label('Total DR')
                                    ->money('AED')
                                    ->extraAttributes(['class' => 'text-xl font-mono font-bold text-blue-700 pl-4 border-l-4 border-blue-400']),
                                \Filament\Infolists\Components\TextEntry::make('total_credit')
                                    ->label('Total CR')
                                    ->money('AED')
                                    ->extraAttributes(['class' => 'text-xl font-mono font-bold text-amber-700 pl-4 border-l-4 border-amber-400']),
                                \Filament\Infolists\Components\TextEntry::make('grand_total')
                                    ->label('Grand Total')
                                    ->money('AED')
                                    ->extraAttributes(['class' => 'text-2xl font-mono font-bold text-primary-600 pl-4 border-l-4 border-primary-500']),
                                \Filament\Infolists\Components\TextEntry::make('balance_due')
                                    ->label('Balance Due')
                                    ->money('AED')
                                    ->extraAttributes(['class' => 'text-xl font-mono font-bold text-red-600 pl-4 border-l-4 border-red-400']),
                            ])
                    ])->compact(),
            ]);
    }
}
