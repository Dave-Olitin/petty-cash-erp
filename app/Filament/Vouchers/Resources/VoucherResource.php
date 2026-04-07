<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\VoucherResource\Pages;
use App\Filament\Vouchers\Resources\VoucherResource\RelationManagers;
use App\Models\Voucher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    /**
     * Auto-computes change_given = max(0, tendered - voucher_amount).
     * Called by afterStateUpdated on every denomination field.
     */
    public static function recomputeChange(Forms\Get $get, Forms\Set $set): void
    {
        $tendered = ((int) $get('bill_1000') * 1000) + ((int) $get('bill_500') * 500) + ((int) $get('bill_200') * 200)
            + ((int) $get('bill_100') * 100) + ((int) $get('bill_50') * 50) + ((int) $get('bill_20') * 20)
            + ((int) $get('bill_10') * 10) + ((int) $get('bill_5') * 5) + ((int) $get('coin_1') * 1)
            + ((int) $get('coin_0_50') * 0.50) + ((int) $get('coin_0_25') * 0.25);

        $voucherAmount = (float) ($get('voucher_amount') ?? 0);
        $change = max(0, round($tendered - $voucherAmount, 2));
        $set('change_given', $change);
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Payee'  => $record->payee,
            'Amount' => 'AED ' . number_format($record->amount, 2),
            'Status' => ucwords(str_replace('_', ' ', $record->status)),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['voucher_number', 'payee', 'description'];
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (!$user) return null;

        // Cache per-user for 30 seconds — this query fires on every Filament page render.
        $count = \Illuminate\Support\Facades\Cache::remember(
            "voucher_badge_{$user->id}",
            30,
            fn () => static::getModel()::actionRequired($user)->count()
        );

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return static::getNavigationBadge() !== null ? 'danger' : null;
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('voucher.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return true; // Delegation to Policy
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // Scenario 4: Prevent deletion of paid physical cash vouchers
        if ($record->status === 'paid') {
            return false;
        }
        return true; // Delegation to Policy
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return true; // Delegation to Policy
    }

    public static function form(Form $form): Form
    {
        $previewSection = Forms\Components\Group::make()->schema([
            Forms\Components\Section::make('Live Preview')
                ->extraAttributes(['class' => 'preview-section-no-padding', 'style' => 'padding:0!important; margin:0!important; position: sticky; top: 1rem;'])
                ->schema([
                    Forms\Components\Placeholder::make('live_preview')
                        ->label('')
                        ->content(fn (Forms\Get $get) => view('filament.forms.components.voucher-preview', ['get' => $get])),
                ])
                ->collapsible()
                ->collapsed(false),
        ])->columnSpan(['default' => 'full', 'lg' => 1]);

        return $form
            ->schema([
                Forms\Components\Grid::make(['default' => 1, 'lg' => 3])->schema([
                    Forms\Components\Group::make()->schema([
                        Forms\Components\Wizard::make([
                            // ── VOUCHER HEADER ───────────────────────────────────────
                            Forms\Components\Wizard\Step::make('Voucher & Payment Details')
                                ->icon('heroicon-o-document-text')
                                ->description('STEP 1')
                                ->schema([
                                    Forms\Components\Group::make()->schema([
                                        Forms\Components\Select::make('type')
                                            ->options([
                                                'petty_cash' => 'Petty Cash Request',
                                                'payment' => 'Payment Voucher',
                                                'receipt' => 'Receipt Voucher',
                                            ])
                                            ->required()
                                            ->default('payment')
                                            ->live()
                                            ->afterStateUpdated(function (\Filament\Forms\Set $set, $state) {
                                                if ($state === 'petty_cash') {
                                                    $template = \App\Models\VoucherTemplate::where('company_name', 'Erick Trading Co.')->first();
                                                    if ($template) {
                                                        $set('voucher_template_id', $template->id);
                                                    }
                                                } else {
                                                    $set('voucher_template_id', null);
                                                }
                                            }),

                                        Forms\Components\Select::make('voucher_template_id')
                                            ->label('Company / Header Template')
                                            ->relationship('template', 'company_name', function (\Illuminate\Database\Eloquent\Builder $query, \Filament\Forms\Get $get) {
                                                $query->where('is_active', true);
                                                if ($get('type') === 'petty_cash') {
                                                    $query->where('company_name', 'Erick Trading Co.');
                                                }
                                                return $query;
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function ($state, \Filament\Forms\Set $set, \Filament\Forms\Get $get) {
                                                if ($state) {
                                                    $template = \App\Models\VoucherTemplate::find($state);
                                                    if ($template) {
                                                        $items = $get('items') ?? [];
                                                        foreach ($items as $key => $item) {
                                                            $set("items.{$key}.branch_code", $template->branch_code);
                                                        }
                                                    }
                                                }
                                            }),

                                        Forms\Components\Select::make('department')
                                            ->label('Department')
                                            ->options(\App\Models\Department::active()->pluck('name', 'name')->toArray())
                                            ->searchable()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('New Department Name')
                                                    ->required()
                                                    ->maxLength(100),
                                            ])
                                            ->createOptionUsing(function (array $data) {
                                                \App\Models\Department::firstOrCreate(
                                                    ['name' => $data['name']],
                                                    ['is_active' => true]
                                                );
                                                return $data['name'];
                                            }),

                                        Forms\Components\Select::make('category_id')
                                            ->label('Trans Cat (Category)')
                                            ->relationship('category', 'name', fn ($query) => $query->where('is_active', true))
                                            ->searchable()
                                            ->getSearchResultsUsing(fn (string $search) =>
                                                \App\Models\Category::where('is_active', true)
                                                    ->where('name', 'like', "%{$search}%")
                                                    ->limit(20)
                                                    ->pluck('name', 'id')
                                                    ->toArray()
                                            )
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('Category Name')
                                                    ->required()
                                                    ->unique('categories', 'name'),
                                                Forms\Components\Select::make('type')
                                                    ->label('Type')
                                                    ->options([
                                                        'expense'        => 'Expense',
                                                        'replenishment'  => 'Replenishment',
                                                        'petty_cash'     => 'Petty Cash',
                                                    ])
                                                    ->required()
                                                    ->default('expense'),
                                            ])
                                            ->createOptionUsing(function (array $data) {
                                                $cat = \App\Models\Category::create([
                                                    'name'      => $data['name'],
                                                    'type'      => $data['type'],
                                                    'is_active' => true,
                                                ]);
                                                return $cat->id;
                                            }),

                                        Forms\Components\TextInput::make('payee')
                                            ->label('Paid To')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(debounce: 500),

                                        Forms\Components\Textarea::make('description')
                                            ->label('Being (Purpose)')
                                            ->rows(4)
                                            ->columnSpanFull()
                                            ->live(debounce: 500),
                                    ])->columns(['default' => 1, 'md' => 3]),

                                    // ── CHEQUE / PAYMENT INFO ─────────────────────────────────
                                    Forms\Components\Section::make('Payment / Cheque Details')
                                        ->collapsed()
                                        ->icon('heroicon-o-credit-card')
                                        ->description('Fill in cheque or payment details if applicable.')
                                        ->schema([
                                            Forms\Components\TextInput::make('cheque_no')
                                                ->label('Cheque No.')
                                                ->maxLength(50)
                                                ->placeholder('Enter cheque number'),

                                            Forms\Components\DatePicker::make('cheque_date')
                                                ->label('Cheque Date')
                                                ->native(false)
                                                ->displayFormat('d/m/Y'),

                                            Forms\Components\TextInput::make('bank')
                                                ->label('Bank')
                                                ->maxLength(100)
                                                ->placeholder('e.g. Emirates NBD'),
                                        ])->columns(['default' => 1, 'md' => 3]),
                        ]),

                    // ── LEDGER ENTRIES ────────────────────────────────────────
                    Forms\Components\Wizard\Step::make('Ledger Entries')
                        ->icon('heroicon-o-table-cells')
                        ->description('STEP 2')
                        ->schema([
                                    Forms\Components\Repeater::make('items')
                                        ->relationship()
                                        ->label('')
                                        ->schema([
                                            // Row 1: Type, Branch, Account Code
                                            Forms\Components\Grid::make(['default' => 1, 'md' => 12])->schema([
                                                Forms\Components\Select::make('entry_type')
                                                    ->label('Entry type')
                                                    ->options([
                                                        'debit'  => 'DR — Debit',
                                                        'credit' => 'CR — Credit',
                                                    ])
                                                    ->default('debit')
                                                    ->required()
                                                    ->live()
                                                    ->columnSpan(['default' => 1, 'md' => 6]),

                                                Forms\Components\Select::make('branch_code')
                                                    ->label('Branch')
                                                    ->searchable()
                                                    ->options(\App\Models\LedgerBranch::pluck('name', 'name'))
                                                    ->createOptionForm([
                                                        Forms\Components\TextInput::make('name')
                                                            ->label('New Branch Name')
                                                            ->required()
                                                            ->unique('ledger_branches', 'name'),
                                                    ])
                                                    ->createOptionUsing(function (array $data) {
                                                        $branch = \App\Models\LedgerBranch::create(['name' => $data['name']]);
                                                        return $branch->name;
                                                    })
                                                    ->default(fn (Forms\Get $get) => \App\Models\VoucherTemplate::find($get('../../voucher_template_id'))?->branch_code)
                                                    ->columnSpan(['default' => 1, 'md' => 6]),

                                                Forms\Components\Select::make('account_code')
                                                    ->label('Account Code')
                                                    ->searchable()
                                                    ->allowHtml()
                                                    ->getSearchResultsUsing(function (string $search) {
                                                        return \App\Models\AccountCode::where('code', 'like', "%{$search}%")
                                                            ->orWhere('name', 'like', "%{$search}%")
                                                            ->limit(30)
                                                            ->get()
                                                            ->mapWithKeys(fn ($ac) => [$ac->code => $ac->code . ' — ' . $ac->name])
                                                            ->toArray();
                                                    })
                                                    ->getOptionLabelUsing(fn (?string $value) => $value
                                                        ? ($ac = \App\Models\AccountCode::where('code', $value)->first())
                                                            ? "{$ac->code} — {$ac->name}"
                                                            : $value
                                                        : null
                                                    )
                                                    ->createOptionForm([
                                                        Forms\Components\TextInput::make('code')->required()->unique('account_codes', 'code'),
                                                        Forms\Components\TextInput::make('name')->required(),
                                                    ])
                                                    ->createOptionUsing(function (array $data) {
                                                        \App\Models\AccountCode::create($data);
                                                        return $data['code'];
                                                    })
                                                    ->live(debounce: 500)
                                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state) {
                                                        if (blank($state)) return;
                                                        $account = \App\Models\AccountCode::where('code', $state)->first();
                                                        if ($account) {
                                                            $set('description', $account->name);
                                                        }
                                                    })
                                                    ->columnSpan(['default' => 1, 'md' => 12]),

                                                Forms\Components\Select::make('trn')
                                                    ->label('TRN (Optional)')
                                                    ->searchable()
                                                    ->allowHtml()
                                                    ->getSearchResultsUsing(function (string $search) {
                                                        return \App\Models\TaxRegistration::where('trn', 'like', "%{$search}%")
                                                            ->orWhere('name', 'like', "%{$search}%")
                                                            ->limit(30)
                                                            ->get()
                                                            ->mapWithKeys(fn ($tax) => [$tax->trn => $tax->trn . ' — <span class="text-xs text-gray-500">' . $tax->name . '</span>'])
                                                            ->toArray();
                                                    })
                                                    ->getOptionLabelUsing(fn (?string $value) => $value
                                                        ? (($tax = \App\Models\TaxRegistration::where('trn', $value)->first())
                                                            ? "{$tax->trn} — {$tax->name}"
                                                            : $value)
                                                        : null
                                                    )
                                                    ->createOptionForm([
                                                        Forms\Components\TextInput::make('trn')
                                                            ->label('TRN Number')
                                                            ->required()
                                                            ->unique('tax_registrations', 'trn'),
                                                        Forms\Components\TextInput::make('name')
                                                            ->label('Vendor / Company Name')
                                                            ->required(),
                                                    ])
                                                    ->createOptionUsing(function (array $data) {
                                                        \App\Models\TaxRegistration::create($data);
                                                        return $data['trn'];
                                                    })
                                                    ->columnSpan(['default' => 1, 'md' => 12]),

                                                Forms\Components\TextInput::make('amount')
                                                    ->numeric()
                                                    ->required()
                                                    ->prefix('AED')
                                                    ->default(0)
                                                    ->live(debounce: 500)
                                                    ->columnSpan(['default' => 1, 'md' => 12]),

                                                Forms\Components\TextInput::make('po_number')
                                                    ->label('PO Number')
                                                    ->maxLength(255)
                                                    ->placeholder('Optional')
                                                    ->columnSpan(['default' => 1, 'md' => 6]),

                                                Forms\Components\TextInput::make('invoice_number')
                                                    ->label('Invoice Number')
                                                    ->maxLength(255)
                                                    ->placeholder('Optional')
                                                    ->columnSpan(['default' => 1, 'md' => 6]),

                                                Forms\Components\Hidden::make('description'),
                                            ]),
                                        ])
                                        ->defaultItems(2)
                                        ->reorderable()
                                        ->reorderableWithButtons()
                                        ->collapsible()
                                        ->cloneable()
                                        ->addActionLabel('+ Add Ledger Entry')
                                        ->itemLabel(fn (array $state): ?string =>
                                            (($state['entry_type'] ?? 'debit') === 'credit' ? '🔴 CR' : '🟢 DR') .
                                            ' — ' . ($state['description'] ?: ($state['account_code'] ?: 'New Entry')) .
                                            ' — AED ' . number_format((float) ($state['amount'] ?? 0), 2)
                                        )
                                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                            $items = $get('items') ?? [];
                                            $type  = $get('type'); // e.g. 'receipt', 'petty_cash', 'payment'

                                            // Receipt vouchers are credit-based; all others are debit-based.
                                            $targetType = ($type === 'receipt') ? 'credit' : 'debit';

                                            $total = 0;
                                            foreach ($items as $item) {
                                                if (($item['entry_type'] ?? 'debit') === $targetType) {
                                                    $total += (float) ($item['amount'] ?? 0);
                                                }
                                            }
                                            $set('amount', number_format($total, 2, '.', ''));
                                        })
                                        ->live(),
                        ]),

                    // ── SUMMARY & ATTACHMENTS ─────────────────────────────────
                    Forms\Components\Wizard\Step::make('Summary & Attachments')
                        ->icon('heroicon-o-calculator')
                        ->description('STEP 3')
                        ->schema([
                                Forms\Components\Group::make()->schema([
                                    Forms\Components\TextInput::make('amount')
                                        ->label('Total Amount')
                                        ->numeric()
                                        ->prefix('AED')
                                        ->required()
                                        ->readOnly()
                                        ->helperText('Auto-calculated from debit entries above.')
                                        ->columnSpanFull(),

                                    Forms\Components\Textarea::make('transaction_summary')
                                        ->label('Remarks')
                                        ->rows(3)
                                        ->helperText('Optional notes or remarks to print on the voucher at the bottom.')
                                        ->live(debounce: 500)
                                        ->columnSpanFull(),

                                    Forms\Components\Repeater::make('invoice_breakdown')
                                        ->label('Invoice / PO Breakdown (Optional)')
                                        ->schema([
                                            Forms\Components\Grid::make(2)->schema([
                                                Forms\Components\TextInput::make('category')->label('Category'),
                                                Forms\Components\TextInput::make('vendor_staff')->label('Vendor / Staff'),
                                            ]),
                                            Forms\Components\Grid::make(3)->schema([
                                                Forms\Components\TextInput::make('lpo_number')->label('PO #'),
                                                Forms\Components\TextInput::make('invoice_number')->label('Invoice #'),
                                                Forms\Components\TextInput::make('total_amount')->label('Total Amount')->numeric()->prefix('AED')->live(debounce: 500),
                                            ]),
                                            Forms\Components\TextInput::make('description')->label('Description')->columnSpanFull(),
                                        ])
                                        ->default([])
                                        ->collapsible()
                                        ->collapsed()
                                        ->itemLabel(fn (array $state): ?string => 
                                            ($state['invoice_number'] ? 'INV# ' . $state['invoice_number'] : 'New breakdown item') . 
                                            ($state['total_amount'] ? ' — AED ' . number_format((float) $state['total_amount'], 2) : '')
                                        )
                                        ->live()
                                        ->hint(function (Forms\Get $get) {
                                            $items = $get('invoice_breakdown') ?? [];
                                            if (count($items) === 0) return null;
                                            
                                            $sum = collect($items)->sum(fn($i) => (float)($i['total_amount'] ?? 0));
                                            $master = (float)($get('amount') ?? 0);
                                            
                                            if ($sum > 0) {
                                                if (abs($sum - $master) <= 0.01) {
                                                    return new \Illuminate\Support\HtmlString('<span class="text-success-600 font-bold dark:text-success-400">✅ Breakdown matches Master Total (AED ' . number_format($sum, 2) . ')</span>');
                                                }
                                                return new \Illuminate\Support\HtmlString('<span class="text-danger-600 font-bold dark:text-danger-400">⚠️ Breakdown sum (AED ' . number_format($sum, 2) . ') differs from Master</span>');
                                            }
                                            return null;
                                        })
                                        ->columnSpanFull(),

                                    Forms\Components\FileUpload::make('attachment_paths')
                                        ->multiple()
                                        ->preserveFilenames()
                                        ->directory('voucher-attachments')
                                        ->disk('public')
                                        ->downloadable()
                                        ->openable()
                                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                                        ->maxSize(10240)
                                        ->label('Invoices & Receipts')
                                        ->helperText('Upload receipts, invoices, or supporting documents (JPG, PNG, PDF, max 10MB each).')
                                        ->columnSpanFull(),
                                ])->columns(['default' => 1, 'md' => 2]),
                        ]),
                ])->skippable()->contained(false),
                    ])->columnSpan(['default' => 'full', 'lg' => 2]),

                    $previewSection,
                ]),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->recordAction(\Filament\Tables\Actions\ViewAction::class)
            ->columns([
                Tables\Columns\TextColumn::make('voucher_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'petty_cash' => 'info',
                        'payment' => 'warning',
                        'receipt' => 'success',
                        'bank_encashment' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'petty_cash' => 'Petty Cash',
                        'payment' => 'Payment',
                        'receipt' => 'Receipt',
                        'bank_encashment' => 'Bank Encashment',
                        default => ucwords(str_replace('_', ' ', $state)),
                    }),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Requester')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payee')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('aed', true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->icon(fn (string $state): string => match ($state) {
                        'draft' => 'heroicon-m-pencil-square',
                        'pending_checker' => 'heroicon-m-clock',
                        'pending_approver' => 'heroicon-m-clock',
                        'approved' => 'heroicon-m-check-circle',
                        'rejected' => 'heroicon-m-x-circle',
                        'paid' => 'heroicon-m-banknotes',
                        default => 'heroicon-m-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending_checker' => 'warning',
                        'pending_approver' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'paid' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'pending_checker' => 'Pending Checker',
                        'pending_approver' => 'Pending Approver',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'paid' => 'Paid',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'petty_cash' => 'Petty Cash',
                        'payment' => 'Payment',
                        'receipt' => 'Receipt',
                        'bank_encashment' => 'Bank Encashment',
                    ]),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Requester')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('created_until')->label('Until Date'),
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
            ->filtersFormColumns(2)
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::Modal)
            ->groups([
                \Filament\Tables\Grouping\Group::make('status')
                    ->label('Status')
                    ->collapsible(),
                \Filament\Tables\Grouping\Group::make('type')
                    ->label('Type')
                    ->collapsible(),
                \Filament\Tables\Grouping\Group::make('user.name')
                    ->label('Requester')
                    ->collapsible(),
            ])
            ->headerActions([
                \Filament\Tables\Actions\ImportAction::make()
                    ->importer(\App\Filament\Imports\VoucherImporter::class),
                \Filament\Tables\Actions\Action::make('export_custom')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($livewire) {
                        return \Maatwebsite\Excel\Facades\Excel::download(
                            new \App\Exports\VouchersExport($livewire->getFilteredTableQuery()),
                            'vouchers_export_' . now()->format('Y-m-d_H-i-s') . '.xlsx'
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton(),
                Tables\Actions\Action::make('print')
                    ->label('Print Voucher')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->iconButton()
                    ->url(fn (Voucher $record) => route('voucher.pdf', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->visible(fn (Voucher $record): bool => auth()->user()->can('update', $record)),

                Tables\Actions\Action::make('submit')
                    ->label('Submit for Checking')
                    ->icon('heroicon-m-paper-airplane')
                    ->color('primary')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->visible(fn (Voucher $record): bool => $record->status === 'draft' && auth()->user()->can('voucher.submit'))
                    ->action(function (Voucher $record) {
                        $error = app(\App\Services\VoucherApprovalService::class)->submit($record, auth()->user());
                        if ($error) {
                            Notification::make()->title($error)->danger()->send();
                            return;
                        }
                        Notification::make()->title('Voucher submitted successfully')->success()->send();
                        $record->refresh();
                    }),

                Tables\Actions\Action::make('check')
                    ->label('Verify & Forward')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->color('warning')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->visible(fn (Voucher $record): bool => $record->status === 'pending_checker' && auth()->user()->can('voucher.check'))
                    ->action(function (Voucher $record) {
                        $error = app(\App\Services\VoucherApprovalService::class)->check($record, auth()->user());
                        if ($error) {
                            Notification::make()->title($error)->danger()->send();
                            return;
                        }
                        Notification::make()->title('Voucher forwarded to Approver')->success()->send();
                        $record->refresh();
                    }),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->visible(function (Voucher $record): bool {
                        if ($record->status !== 'pending_approver') return false;
                        if (!auth()->user()->can('voucher.approve')) return false;

                        // If a workflow chain is configured, only show to the correct step's user
                        if (\App\Models\ApprovalWorkflow::isConfigured()) {
                            $step = \App\Models\ApprovalWorkflow::getApproverAtStep((int) ($record->current_approval_step ?? 1));
                            return $step && $step->user_id == auth()->id();
                        }

                        // No chain configured — any Approver-role user can act
                        return auth()->user()->hasRole('Approver');
                    })
                    ->action(function (Voucher $record) {
                        $service = app(\App\Services\VoucherApprovalService::class);
                        $wasMultiStep = \App\Models\ApprovalWorkflow::totalSteps() > 0;
                        $currentStep  = (int) ($record->current_approval_step ?? 1);
                        $totalSteps   = \App\Models\ApprovalWorkflow::totalSteps();
                        $isLastStep   = $totalSteps === 0 || $currentStep >= $totalSteps;

                        $error = $service->approve($record, auth()->user());
                        if ($error) {
                            Notification::make()->title($error)->danger()->send();
                            return;
                        }

                        $title = $isLastStep
                            ? 'Voucher fully approved'
                            : 'Step ' . $currentStep . ' approved — forwarded to next approver';

                        Notification::make()->title($title)->success()->send();
                        $record->refresh();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Return / Reject')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('comments')->required()->label('Reason for Rejection'),
                    ])
                    ->visible(fn (Voucher $record): bool => in_array($record->status, ['pending_checker', 'pending_approver']) && auth()->user()->can('voucher.reject'))
                    ->action(function (Voucher $record, array $data) {
                        $error = app(\App\Services\VoucherApprovalService::class)->reject($record, auth()->user(), $data['comments']);
                        if ($error) {
                            Notification::make()->title($error)->danger()->send();
                            return;
                        }
                        Notification::make()->title('Voucher rejected')->danger()->send();
                        $record->refresh();
                    }),

                Tables\Actions\Action::make('mark_paid')
                    ->label(fn (Voucher $record) => $record->type === 'receipt' ? 'Collect Funds' : 'Disburse Funds')
                    ->icon('heroicon-m-banknotes')
                    ->color('success')
                    ->iconButton()
                    ->modalHeading(fn (Voucher $record) => in_array($record->type, ['payment', 'bank_encashment']) ? 'Disburse via Cheque / Bank' : ($record->type === 'receipt' ? 'Collect Cash Denominations' : 'Disburse Cash Denominations'))
                    ->modalSubmitActionLabel('Process & Mark Paid')
                    ->mountUsing(function (Forms\Form $form, Voucher $record) {
                        $form->fill([
                            'voucher_amount' => $record->amount,
                            'cheque_no'      => $record->cheque_no,
                            'cheque_date'    => $record->cheque_date,
                            'bank'           => $record->bank,
                        ]);
                    })
                    ->form([
                        Forms\Components\Hidden::make('voucher_amount'),
                        
                        Forms\Components\Section::make('Cheque / Bank Transfer Details')
                            ->description('Confirm the final payment references for this voucher.')
                            ->visible(fn (Voucher $record) => in_array($record->type, ['payment', 'bank_encashment']))
                            ->schema([
                                Forms\Components\TextInput::make('cheque_no')
                                    ->label('Cheque / Ref No.')
                                    ->maxLength(50),
                                Forms\Components\DatePicker::make('cheque_date')
                                    ->label('Cheque Date')
                                    ->native(false),
                                Forms\Components\TextInput::make('bank')
                                    ->label('Bank Name')
                                    ->maxLength(100),
                            ])->columns(3),

                        Forms\Components\Section::make('Cash Handover / Collection')
                            ->description(fn (Voucher $record) => new \Illuminate\Support\HtmlString("Voucher Amount: <strong>AED " . number_format((float) $record->amount, 2) . "</strong> &mdash; Enter the bills/coins you hand over. Change back is auto-calculated."))
                            ->visible(fn (Voucher $record) => !in_array($record->type, ['payment', 'bank_encashment']))
                            ->schema([
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('bill_1000')->label('1000 Bills')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('bill_500')->label('500 Bills')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('bill_200')->label('200 Bills')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('bill_100')->label('100 Bills')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('bill_50')->label('50 Bills')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('bill_20')->label('20 Bills')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('bill_10')->label('10 Bills')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('bill_5')->label('5 Bills')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('coin_1')->label('1 Coins')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('coin_0_50')->label('0.50 Coins')->numeric()->default(0)->live()->step('1')->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('coin_0_25')->label('0.25 Coins')->numeric()->default(0)->live()->step('1')->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                ]),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('change_given')
                                        ->label('Change Received Back (AED)')
                                        ->numeric()
                                        ->default(0)
                                        ->readOnly()
                                        ->helperText('Auto-calculated: Cash tendered minus voucher amount. Adjust denominations above to update.'),
                                    Forms\Components\Placeholder::make('net_summary')
                                        ->label('Live Summary')
                                        ->content(function (Forms\Get $get) {
                                            $total  = ((int) $get('bill_1000') * 1000) + ((int) $get('bill_500') * 500) + ((int) $get('bill_200') * 200) + ((int) $get('bill_100') * 100) + ((int) $get('bill_50') * 50) + ((int) $get('bill_20') * 20) + ((int) $get('bill_10') * 10) + ((int) $get('bill_5') * 5) + ((int) $get('coin_1') * 1) + ((int) $get('coin_0_50') * 0.50) + ((int) $get('coin_0_25') * 0.25);
                                            $change = (float) ($get('change_given') ?? 0);
                                            $net    = $total - $change;
                                            return new \Illuminate\Support\HtmlString(
                                                "<div style='line-height:1.9'>" .
                                                "💵 <strong>Cash Tendered:</strong> AED " . number_format($total, 2) . "<br>" .
                                                "🔄 <strong>Change Back:</strong> AED " . number_format($change, 2) . "<br>" .
                                                "✅ <strong>Net Disbursed:</strong> AED " . number_format($net, 2) .
                                                "</div>"
                                            );
                                        }),
                                    Forms\Components\Textarea::make('remarks')
                                    ->label('Remarks / Notes')
                                    ->placeholder('Optional: explain differences in denominations or general notes.')
                                    ->columnSpanFull(),
                                Forms\Components\Toggle::make('is_change_received')
                                    ->label('Employee has already returned the exact change for this transaction.')
                                    ->default(true)
                                    ->visible(fn (Forms\Get $get) => (float)($get('change_given') ?? 0) > 0)
                                    ->columnSpanFull(),
                                ]),
                            ])
                    ])
                    ->visible(fn (Voucher $record): bool => in_array($record->status, ['pending_checker', 'pending_approver', 'approved']) && auth()->user()->can('voucher.pay'))
                    ->action(function (Voucher $record, array $data) {
                        if (in_array($record->type, ['payment', 'bank_encashment'])) {
                            $record->update([
                                'cheque_no' => $data['cheque_no'] ?? null,
                                'cheque_date' => $data['cheque_date'] ?? null,
                                'bank' => $data['bank'] ?? null,
                            ]);
                            
                            $error = app(\App\Services\VoucherApprovalService::class)->markPaid($record, auth()->user());
                            if ($error) {
                                Notification::make()->title($error)->danger()->send();
                                return;
                            }
                            Notification::make()->title('Cheque details successfully recorded')->success()->send();
                            $record->refresh();
                            return;
                        }

                        $tendered = ((int) ($data['bill_1000'] ?? 0) * 1000) 
                            + ((int) ($data['bill_500'] ?? 0) * 500)
                            + ((int) ($data['bill_200'] ?? 0) * 200)
                            + ((int) ($data['bill_100'] ?? 0) * 100)
                            + ((int) ($data['bill_50'] ?? 0) * 50)
                            + ((int) ($data['bill_20'] ?? 0) * 20)
                            + ((int) ($data['bill_10'] ?? 0) * 10)
                            + ((int) ($data['bill_5'] ?? 0) * 5)
                            + ((int) ($data['coin_1'] ?? 0) * 1)
                            + ((int) ($data['coin_0_50'] ?? 0) * 0.50)
                            + ((int) ($data['coin_0_25'] ?? 0) * 0.25);

                        $changeGiven = round((float) ($data['change_given'] ?? 0), 2);
                        $net         = round($tendered - $changeGiven, 2);

                        if ($net !== round((float) $record->amount, 2)) {
                            Notification::make()
                                ->title('Denomination validation failed')
                                ->danger()
                                ->body('Net amount (Cash Tendered − Change) must equal the voucher amount. ' .
                                       'You tendered AED ' . number_format($tendered, 2) .
                                       ', change back AED ' . number_format($changeGiven, 2) .
                                       ', net AED ' . number_format($net, 2) .
                                       ' ≠ voucher AED ' . number_format((float) $record->amount, 2) . '.')
                                ->send();
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'bill_1000' => 'Net amount mismatch.'
                            ]);
                        }

                        // Save denominations securely
                        $record->denominations()->create([
                            'bill_1000'    => $data['bill_1000'] ?: 0,
                            'bill_500'     => $data['bill_500'] ?: 0,
                            'bill_200'     => $data['bill_200'] ?: 0,
                            'bill_100'     => $data['bill_100'] ?: 0,
                            'bill_50'      => $data['bill_50'] ?: 0,
                            'bill_20'      => $data['bill_20'] ?: 0,
                            'bill_10'      => $data['bill_10'] ?: 0,
                            'bill_5'       => $data['bill_5'] ?: 0,
                            'coin_1'       => $data['coin_1'] ?: 0,
                            'coin_0_50'    => $data['coin_0_50'] ?: 0,
                            'coin_0_25'    => $data['coin_0_25'] ?: 0,
                            'total_amount' => $tendered,
                            'change_given' => $changeGiven,
                            'is_change_received' => $data['is_change_received'] ?? true,
                            'remarks'      => $data['remarks'] ?? null,
                        ]);

                        $error = app(\App\Services\VoucherApprovalService::class)->markPaid($record, auth()->user());
                        if ($error) {
                            Notification::make()->title($error)->danger()->send();
                            return;
                        }
                        Notification::make()->title($record->type === 'receipt' ? 'Receipt funds safely collected' : 'Voucher funds safely disbursed')->success()->send();
                        $record->refresh();
                    }),

                Tables\Actions\Action::make('update_attachments')
                    ->label('Manage Attachments')
                    ->icon('heroicon-o-paper-clip')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Add/update receipts and descriptions for this paid voucher')
                    ->visible(fn (Voucher $record): bool => $record->status === 'paid' && (auth()->user()->hasAnyRole(['Super Admin', 'Admin', 'Accountant', 'Approver']) || auth()->id() === $record->user_id))
                    ->fillForm(fn (Voucher $record): array => [
                        'attachment_paths' => $record->attachment_paths,
                        'description'      => $record->description,
                    ])
                    ->form([
                        \Filament\Forms\Components\FileUpload::make('attachment_paths')
                            ->label('Upload Receipts / Invoices')
                            ->multiple()
                            ->directory('voucher-attachments')
                            ->maxFiles(5)
                            ->maxSize(10240)
                            ->openable()
                            ->downloadable()
                            ->panelLayout('grid'),
                        \Filament\Forms\Components\Textarea::make('description')
                            ->label('Description / Follow-up Notes')
                            ->rows(3),
                    ])
                    ->action(function (array $data, Voucher $record): void {
                        $record->update([
                            'attachment_paths' => $data['attachment_paths'] ?? null,
                            'description'      => $data['description'] ?? null,
                        ]);

                        activity()
                            ->performedOn($record)
                            ->causedBy(auth()->user())
                            ->log('Attachments and descriptions updated post-disbursement');

                        Notification::make()->title('Attachments and notes securely updated')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export_selected')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            return \Maatwebsite\Excel\Facades\Excel::download(
                                new \App\Exports\VouchersExport(\App\Models\Voucher::query()->whereIn('vouchers.id', $records->pluck('id'))),
                                'vouchers_selected_' . now()->format('Y-m-d_H-i-s') . '.xlsx'
                            );
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVouchers::route('/'),
            'create' => Pages\CreateVoucher::route('/create'),
            'view' => Pages\ViewVoucher::route('/{record}'),
            'edit' => Pages\EditVoucher::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['approvals.user.roles', 'items', 'template', 'denominations']);
            
        $user = auth()->user();

        // Scope queries based on user permissions/branch
        if ($user && !$user->isHeadOffice() && !$user->hasAnyRole(['Accountant', 'Approver', 'Admin', 'Super Admin'])) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                // ── TOP ROW: PDF preview (Left, 75%) | Approval Trail (Right, 25%) ──────────
                \Filament\Infolists\Components\Grid::make(4)->schema([
                    // Left: PDF iframe (takes up 3 columns)
                    \Filament\Infolists\Components\Section::make()
                        ->schema([
                            \Filament\Infolists\Components\ViewEntry::make('pdf_preview')
                                ->view('filament.infolists.pdf-preview')
                                ->label(''),
                        ])
                        ->compact()
                        ->columnSpan(3),

                    // Right: Approval Trail (takes up 1 column)
                    \Filament\Infolists\Components\Section::make()
                        ->schema([
                            \Filament\Infolists\Components\ViewEntry::make('approvals_timeline')
                                ->label('')
                                ->view('filament.infolists.approval-timeline'),
                        ])
                        ->columnSpan(1),
                ]),

                // ── BOTTOM ROW: Voucher Details — full width ───────────────────────
                \Filament\Infolists\Components\Section::make('Voucher Details')
                    ->compact()
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('voucher_number')
                            ->label('Voucher #')
                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                            ->copyable(),
                        \Filament\Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->icon(fn (string $state): string => match ($state) {
                                'draft'            => 'heroicon-m-pencil-square',
                                'pending_checker'  => 'heroicon-m-clock',
                                'pending_approver' => 'heroicon-m-clock',
                                'approved'         => 'heroicon-m-check-circle',
                                'rejected'         => 'heroicon-m-x-circle',
                                'paid'             => 'heroicon-m-banknotes',
                                default            => 'heroicon-m-question-mark-circle',
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'draft'            => 'gray',
                                'pending_checker'  => 'warning',
                                'pending_approver' => 'warning',
                                'approved'         => 'success',
                                'rejected'         => 'danger',
                                'paid'             => 'success',
                                default            => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),
                        \Filament\Infolists\Components\TextEntry::make('user.name')
                            ->label('Requester')
                            ->icon('heroicon-m-user-circle')
                            ->iconColor('primary')
                            ->weight(\Filament\Support\Enums\FontWeight::Bold),
                        \Filament\Infolists\Components\TextEntry::make('type')
                            ->formatStateUsing(fn ($state) => match($state) {
                                'petty_cash' => 'Petty Cash',
                                'receipt' => 'Receipt Voucher',
                                'bank_encashment' => 'Bank Encashment',
                                default => 'Payment Voucher'
                            }),
                        \Filament\Infolists\Components\TextEntry::make('account_codes')
                            ->label('Account Code(s)')
                            ->getStateUsing(fn ($record) => $record->items->pluck('account_code')->unique()->implode(', ') ?: '—'),
                        \Filament\Infolists\Components\TextEntry::make('po_numbers')
                            ->label('PO Number(s)')
                            ->getStateUsing(fn ($record) => $record->items->pluck('po_number')->filter()->unique()->implode(', ') ?: '—')
                            ->visible(fn ($record) => $record->items->pluck('po_number')->filter()->isNotEmpty()),
                        \Filament\Infolists\Components\TextEntry::make('invoice_numbers')
                            ->label('Invoice Number(s)')
                            ->getStateUsing(fn ($record) => $record->items->pluck('invoice_number')->filter()->unique()->implode(', ') ?: '—')
                            ->visible(fn ($record) => $record->items->pluck('invoice_number')->filter()->isNotEmpty()),
                        \Filament\Infolists\Components\TextEntry::make('department')
                            ->label('Department')
                            ->placeholder('—'),
                        \Filament\Infolists\Components\TextEntry::make('category.name')
                            ->label('Trans Cat')
                            ->placeholder('—'),
                        \Filament\Infolists\Components\TextEntry::make('payee')
                            ->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                        \Filament\Infolists\Components\TextEntry::make('amount')
                            ->money('aed')
                            ->weight(\Filament\Support\Enums\FontWeight::Bold),
                        \Filament\Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided'),
                        \Filament\Infolists\Components\TextEntry::make('transaction_summary')
                            ->label('Remarks')
                            ->columnSpanFull()
                            ->placeholder('—'),

                        \Filament\Infolists\Components\TextEntry::make('cheque_no')
                            ->label('Cheque #')
                            ->visible(fn ($record) => !empty($record->cheque_no)),
                        \Filament\Infolists\Components\TextEntry::make('cheque_date')
                            ->date()
                            ->visible(fn ($record) => !empty($record->cheque_no)),
                        \Filament\Infolists\Components\TextEntry::make('bank')
                            ->visible(fn ($record) => !empty($record->cheque_no)),

                        \Filament\Infolists\Components\TextEntry::make('attachment_paths')
                            ->label('Invoices / Receipts')
                            ->columnSpanFull()
                            ->formatStateUsing(fn (string $state): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                                '<a href="' . asset('storage/' . implode('/', array_map('rawurlencode', explode('/', $state)))) . '" target="_blank" class="text-primary-600 underline flex items-center gap-2">' .
                                '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>' .
                                basename($state) . '</a>'
                            ))
                            ->html()
                            ->listWithLineBreaks(),
                    ])->columns(4),

                // ── CASH DENOMINATIONS: Displayed if filled ──────────────────────
                \Filament\Infolists\Components\Section::make('Cash Denominations breakdown')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn ($record) => $record->denominations !== null)
                    ->collapsed()
                    ->schema(function () {
                        $denoms = [
                            'bill_1000' => 1000, 'bill_500' => 500, 'bill_200' => 200,
                            'bill_100' => 100, 'bill_50' => 50, 'bill_20' => 20,
                            'bill_10' => 10, 'bill_5' => 5, 'coin_1' => 1,
                            'coin_0_50' => 0.50, 'coin_0_25' => 0.25
                        ];
                        
                        $fields = [];
                        foreach ($denoms as $col => $val) {
                            $fields[] = \Filament\Infolists\Components\TextEntry::make("denominations.{$col}")
                                ->label(str_contains($col, 'coin') ? "AED " . number_format($val, 2) . " (Coin)" : "AED {$val} (Bill)")
                                ->formatStateUsing(fn ($state) => "{$state} qty = AED " . number_format($state * $val, 2))
                                ->visible(fn ($record) => optional($record->denominations)->{$col} > 0);
                        }
                        
                        $fields[] = \Filament\Infolists\Components\TextEntry::make('denominations.total_amount')
                                ->label('Total Cash')
                                ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                ->color('success')
                                ->money('AED')
                                ->columnSpanFull();

                        $fields[] = \Filament\Infolists\Components\TextEntry::make('denominations.remarks')
                                ->label('Remarks / Notes')
                                ->visible(fn ($record) => !empty(optional($record->denominations)->remarks))
                                ->columnSpanFull();

                        return $fields;
                    })->columns(['sm' => 2, 'md' => 4]),

                // ── ACTIVITY LOG: Full width chronological timeline ──────────────────────
                \Filament\Infolists\Components\Section::make('Activity Log')
                    ->compact()
                    ->schema([
                        \Filament\Infolists\Components\ViewEntry::make('activity_log')
                            ->label('')
                            ->view('filament.infolists.activity-log'),
                    ])
                    ->collapsed(),

            ])->columns(1);
    }
}

