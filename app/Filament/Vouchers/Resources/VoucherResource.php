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
     */
    public static function recomputeChange(Forms\Get $get, Forms\Set $set): void
    {
        $tendered = ((int) $get('bill_1000') * 1000) + ((int) $get('bill_500') * 500) + ((int) $get('bill_200') * 200)
            + ((int) $get('bill_100') * 100) + ((int) $get('bill_50') * 50) + ((int) $get('bill_20') * 20)
            + ((int) $get('bill_10') * 10) + ((int) $get('bill_5') * 5) + ((int) $get('coin_1') * 1)
            + ((int) $get('coin_0_50') * 0.50) + ((int) $get('coin_0_25') * 0.25);

        // 'voucher_amount' is used in the action modal (hidden field via mountUsing).
        // 'amount' is used if called from the main form context.
        $targetAmount = (float) ($get('voucher_amount') ?: $get('amount') ?: 0);
        $deduction    = (float) ($get('prior_deduction') ?: 0);
        $cashToPay    = max(0, $targetAmount - $deduction);
        
        // Final calculation for change is tendered - cashToPay, but we only show it as change if it's positive.
        // If it's negative, it means we are 'short'.
        $change = max(0, round($tendered - $cashToPay, 2));
        $set('change_given', $change);
    }

    /**
     * Auto-computes voucher total amount from items.
     */
    public static function recomputeVoucherTotal(Forms\Get $get, Forms\Set $set): void
    {
        $items = $get('items') ?? [];
        $type  = $get('type');
        $field = ($type === 'receipt') ? 'credit' : 'debit';

        $total = 0;
        foreach ($items as $item) {
            $total += (float) ($item[$field] ?? 0);
        }
        $set('amount', number_format($total, 2, '.', ''));
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



                                        Forms\Components\Select::make('purchaseEntries')
                                            ->label('Linked Purchase Entries')
                                            ->relationship(
                                                'purchaseEntries', 
                                                'entry_no', 
                                                fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with('taxRegistration')
                                            )
                                            ->multiple()
                                            ->getOptionLabelFromRecordUsing(fn ($record) =>
                                                $record->entry_no . ' — ' . ($record->taxRegistration?->name ?? $record->supplier_name ?? 'No Supplier') . ' — AED ' . number_format($record->grand_total ?? $record->total_amount ?? 0, 2)
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->placeholder('Optional — link purchase entries')
                                            ->columnSpanFull(),

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
                                            // Row 1: Branch, Account Code, Debit, Credit
                                            Forms\Components\Grid::make(['default' => 1, 'md' => 12])->schema([
                                                Forms\Components\Hidden::make('entry_type')
                                                    ->default('debit'),

                                                Forms\Components\Grid::make(12)->schema([
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
                                                        ->columnSpan(6),

                                                    Forms\Components\Select::make('account_code')
                                                        ->label('Account Code')
                                                        ->searchable()
                                                        ->allowHtml()
                                                        ->getSearchResultsUsing(function (Forms\Get $get, string $search) {
                                                            $templateId = $get('../../voucher_template_id');
                                                            return \App\Models\AccountCode::where('is_active', true)
                                                                ->where(function ($query) use ($search) {
                                                                    $query->where('code', 'like', "%{$search}%")
                                                                        ->orWhere('name', 'like', "%{$search}%");
                                                                })
                                                                ->when($templateId, function ($query) use ($templateId) {
                                                                    $query->where(function ($q) use ($templateId) {
                                                                        $q->whereNull('entity')
                                                                            ->orWhereJsonLength('entity', 0)
                                                                            ->orWhereJsonContains('entity', (string) $templateId)
                                                                            ->orWhereJsonContains('entity', (int) $templateId);
                                                                    });
                                                                })
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
                                                        ->columnSpan(6),
                                                ]),

                                                Forms\Components\Grid::make(12)->schema([
                                                    Forms\Components\Select::make('trn')
                                                        ->label('Supplier (TRN)')
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
                                                        ->columnSpan(6),

                                                    Forms\Components\TextInput::make('debit')
                                                        ->label('Debit (DR)')
                                                        ->numeric()
                                                        ->required()
                                                        ->prefix('DR')
                                                        ->default(0)
                                                        ->live(debounce: 500)
                                                        ->columnSpan(3)
                                                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                                            $val = (float)($state ?? 0);
                                                            if ($val > 0) {
                                                                $set('credit', 0);
                                                                $set('entry_type', 'debit');
                                                            }
                                                            $set('amount', $val);
                                                        })
                                                        ->extraInputAttributes([
                                                            'class' => 'font-mono text-right text-green-700 font-bold border-green-500 focus:border-green-600 ring-green-200',
                                                            'style' => 'border-width: 2px;',
                                                        ]),

                                                    Forms\Components\TextInput::make('credit')
                                                        ->label('Credit (CR)')
                                                        ->numeric()
                                                        ->required()
                                                        ->prefix('CR')
                                                        ->default(0)
                                                        ->live(debounce: 500)
                                                        ->columnSpan(3)
                                                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                                            $val = (float)($state ?? 0);
                                                            if ($val > 0) {
                                                                $set('debit', 0);
                                                                $set('entry_type', 'credit');
                                                            }
                                                            $set('amount', $val);
                                                        })
                                                        ->extraInputAttributes([
                                                            'class' => 'font-mono text-right text-red-700 font-bold border-red-500 focus:border-red-600 ring-red-200',
                                                            'style' => 'border-width: 2px;',
                                                        ]),
                                                ]),

                                                Forms\Components\Grid::make(12)->schema([
                                                    Forms\Components\TextInput::make('po_number')
                                                        ->label('PO #')
                                                        ->maxLength(255)
                                                        ->placeholder('Optional')
                                                        ->columnSpan(6),

                                                    Forms\Components\TextInput::make('invoice_number')
                                                        ->label('Inv #')
                                                        ->maxLength(255)
                                                        ->placeholder('Optional')
                                                        ->columnSpan(6),
                                                ]),


                                                Forms\Components\Hidden::make('amount'),
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
                                            ((float)($state['credit'] ?? 0) > 0 ? '🔴 CR' : '🟢 DR') .
                                            ' — ' . ($state['description'] ?: ($state['account_code'] ?: 'New Entry')) .
                                            ' — DR: AED ' . number_format((float)($state['debit'] ?? 0), 2) .
                                            ' | CR: AED ' . number_format((float)($state['credit'] ?? 0), 2)
                                        )
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => static::recomputeVoucherTotal($get, $set))
                                        ->live(),
                        ]),

                    // ── SUMMARY & ATTACHMENTS ─────────────────────────────────
                    Forms\Components\Wizard\Step::make('Summary & Attachments')
                        ->icon('heroicon-o-calculator')
                        ->description('STEP 3')
                        ->schema([
                                Forms\Components\Group::make()->schema([
                                    Forms\Components\Placeholder::make('amount_placeholder')
                                        ->label('Voucher Total')
                                        ->content(fn ($get) => new \Illuminate\Support\HtmlString(
                                            '<div class="text-3xl font-mono font-bold text-primary-600">' .
                                            number_format((float)($get('amount') ?? 0), 2) .
                                            ' <span class="text-xs font-normal text-gray-400">AED</span></div>'
                                        )),

                                    Forms\Components\Placeholder::make('balance_check')
                                        ->label('Debit / Credit Balance')
                                        ->content(function ($get) {
                                            $items  = $get('items') ?? [];
                                            $totalDr = 0;
                                            $totalCr = 0;

                                            foreach ($items as $item) {
                                                $totalDr += (float) ($item['debit']  ?? 0);
                                                $totalCr += (float) ($item['credit'] ?? 0);
                                            }

                                            $diff      = round(abs($totalDr - $totalCr), 2);
                                            $balanced  = $diff <= 0.01;

                                            $drLine = '<div class="flex justify-between"><span class="text-gray-500 text-sm">Total Debit&nbsp;(DR)</span><span class="font-mono font-semibold text-green-700">AED ' . number_format($totalDr, 2) . '</span></div>';
                                            $crLine = '<div class="flex justify-between"><span class="text-gray-500 text-sm">Total Credit (CR)</span><span class="font-mono font-semibold text-red-700">AED '  . number_format($totalCr, 2) . '</span></div>';

                                            if ($balanced) {
                                                $badge = '<div class="mt-2 flex items-center gap-2 rounded-lg" style="background-color: #f0fdf4; border: 1px solid #bdf2cb; color: #166534; padding: 8px 12px;">'
                                                    . '<svg style="width: 20px; height: 20px; flex-shrink: 0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
                                                    . '<span style="font-weight: 600; font-size: 0.875rem;">Balanced — Debits equal Credits</span>'
                                                    . '</div>';
                                            } else {
                                                $badge = '<div class="mt-2 flex items-center gap-2 rounded-lg" style="background-color: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 8px 12px;">'
                                                    . '<svg style="width: 20px; height: 20px; flex-shrink: 0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>'
                                                    . '<span style="font-weight: 600; font-size: 0.875rem;">Out of Balance — Difference: AED ' . number_format($diff, 2) . '</span>'
                                                    . '</div>';
                                            }

                                            return new \Illuminate\Support\HtmlString(
                                                '<div class="space-y-1">' . $drLine . $crLine . $badge . '</div>'
                                            );
                                        }),

                                    Forms\Components\Hidden::make('amount')->required(),

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
                        'voided' => 'heroicon-m-no-symbol',
                        default => 'heroicon-m-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending_checker' => 'warning',
                        'pending_approver' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'paid' => 'success',
                        'voided' => 'danger',
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
                        'voided' => 'Voided',
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
                            'multiple_payments' => $record->multiple_payments ?? [
                                [
                                    'cheque_no' => $record->cheque_no,
                                    'cheque_date' => $record->cheque_date,
                                    'bank' => $record->bank,
                                    'amount' => $record->amount,
                                ]
                            ],
                        ]);
                    })
                    ->form([
                        Forms\Components\Hidden::make('voucher_amount'),
                        
                        Forms\Components\Repeater::make('multiple_payments')
                            ->label('Payment References')
                            ->visible(fn (Voucher $record) => in_array($record->type, ['payment', 'bank_encashment']))
                            ->schema([
                                Forms\Components\Grid::make(4)->schema([
                                    Forms\Components\TextInput::make('cheque_no')
                                        ->label('Ref/Cheque #')
                                        ->required(),
                                    Forms\Components\DatePicker::make('cheque_date')
                                        ->label('Date')
                                        ->required()
                                        ->native(false),
                                    Forms\Components\Select::make('bank')
                                        ->label('Bank / Account')
                                        ->searchable()
                                        ->allowHtml()
                                        ->getSearchResultsUsing(function (string $search, ?Voucher $record = null) {
                                            $templateId = $record?->voucher_template_id;
                                            return \App\Models\AccountCode::where('is_active', true)
                                                ->where(function ($query) use ($search) {
                                                    $query->where('code', 'like', "%{$search}%")
                                                        ->orWhere('name', 'like', "%{$search}%");
                                                })
                                                ->when($templateId, function ($query) use ($templateId) {
                                                    $query->where(function ($q) use ($templateId) {
                                                        $q->whereNull('entity')
                                                            ->orWhereJsonLength('entity', 0)
                                                            ->orWhereJsonContains('entity', (string) $templateId)
                                                            ->orWhereJsonContains('entity', (int) $templateId);
                                                    });
                                                })
                                                ->limit(50)
                                                ->get()
                                                ->mapWithKeys(fn ($ac) => [$ac->code => "{$ac->code} — {$ac->name}"])
                                                ->toArray();
                                        })
                                        ->getOptionLabelUsing(fn (?string $value) => $value
                                            ? ($ac = \App\Models\AccountCode::where('code', $value)->first())
                                                ? "{$ac->code} — {$ac->name}"
                                                : $value
                                            : null
                                        )
                                        ->required(),
                                    Forms\Components\TextInput::make('amount')
                                        ->label('Amount')
                                        ->numeric()
                                        ->prefix('AED')
                                        ->required()
                                        ->live(debounce: 500),
                                ]),
                            ])
                            ->default([])
                            ->reorderable(false)
                            ->itemLabel(fn (array $state): ?string => 
                                ($state['cheque_no'] ?? 'Payment') . 
                                ($state['amount'] ? ' — AED ' . number_format((float) $state['amount'], 2) : '')
                            )
                            ->hint(function (Forms\Get $get) {
                                $payments = $get('multiple_payments') ?? [];
                                $total = collect($payments)->sum(fn($p) => (float)($p['amount'] ?? 0));
                                $target = (float)$get('voucher_amount');
                                
                                if (abs($total - $target) < 0.01) {
                                    return new \Illuminate\Support\HtmlString('<span class="text-success-600 font-bold">✅ Total Matches (AED ' . number_format($total, 2) . ')</span>');
                                }
                                return new \Illuminate\Support\HtmlString('<span class="text-danger-600 font-bold">⚠️ Total (AED ' . number_format($total, 2) . ') must equal AED ' . number_format($target, 2) . '</span>');
                            }),

                        Forms\Components\Section::make('Physical Cash Breakdown')
                            ->description(fn (Voucher $record) => new \Illuminate\Support\HtmlString("Voucher Total: <strong>AED " . number_format((float) $record->amount, 2) . "</strong> &mdash; Please count the currency notes and coins."))
                            ->visible(fn (Voucher $record) => !in_array($record->type, ['payment', 'bank_encashment']))
                            ->schema([
                                \Filament\Forms\Components\Fieldset::make('Currency Notes (Bills)')
                                    ->schema([
                                        Forms\Components\TextInput::make('bill_1000')->label('1000s')->numeric()->default(0)->live()->minValue(0)
                                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                        Forms\Components\TextInput::make('bill_500')->label('500s')->numeric()->default(0)->live()->minValue(0)
                                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                        Forms\Components\TextInput::make('bill_200')->label('200s')->numeric()->default(0)->live()->minValue(0)
                                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                        Forms\Components\TextInput::make('bill_100')->label('100s')->numeric()->default(0)->live()->minValue(0)
                                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                        Forms\Components\TextInput::make('bill_50')->label('50s')->numeric()->default(0)->live()->minValue(0)
                                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                        Forms\Components\TextInput::make('bill_20')->label('20s')->numeric()->default(0)->live()->minValue(0)
                                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                        Forms\Components\TextInput::make('bill_10')->label('10s')->numeric()->default(0)->live()->minValue(0)
                                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                        Forms\Components\TextInput::make('bill_5')->label('5s')->numeric()->default(0)->live()->minValue(0)
                                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                    ])->columns(4),

                                \Filament\Forms\Components\Fieldset::make('Coins')
                                    ->schema([
                                        Forms\Components\TextInput::make('coin_1')->label('1.00')->numeric()->default(0)->live()->minValue(0)
                                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                        Forms\Components\TextInput::make('coin_0_50')->label('0.50')->numeric()->default(0)->live()->step('1')->minValue(0)
                                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                        Forms\Components\TextInput::make('coin_0_25')->label('0.25')->numeric()->default(0)->live()->step('1')->minValue(0)
                                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),
                                    ])->columns(3),

                                \Filament\Forms\Components\Section::make()
                                    ->schema([
                                        Forms\Components\Grid::make(3)->schema([
                                            Forms\Components\TextInput::make('prior_deduction')
                                                ->label('Cash Advance / Prior Deduction')
                                                ->numeric()
                                                ->default(0)
                                                ->live()
                                                ->prefix('AED')
                                                ->extraInputAttributes(['class' => 'font-bold'])
                                                ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recomputeChange($get, $set)),

                                            Forms\Components\TextInput::make('change_given')
                                                ->label('Cash Return / Change Due')
                                                ->numeric()
                                                ->default(0)
                                                ->readOnly()
                                                ->extraInputAttributes(['class' => 'font-bold text-lg text-primary-600'])
                                                ->helperText('Change automatically calculated based on tendered amount.'),
                                            
                                            Forms\Components\Placeholder::make('net_summary')
                                                ->label('Financial Summary')
                                                ->content(function (Forms\Get $get, Voucher $record) {
                                                    $tendered = ((int) $get('bill_1000') * 1000) + ((int) $get('bill_500') * 500) + ((int) $get('bill_200') * 200) + ((int) $get('bill_100') * 100) + ((int) $get('bill_50') * 50) + ((int) $get('bill_20') * 20) + ((int) $get('bill_10') * 10) + ((int) $get('bill_5') * 5) + ((int) $get('coin_1') * 1) + ((int) $get('coin_0_50') * 0.50) + ((int) $get('coin_0_25') * 0.25);
                                                    $target   = (float) $record->amount;
                                                    $deduction = (float) ($get('prior_deduction') ?? 0);
                                                    $netCashTarget = max(0, round($target - $deduction, 2));
                                                    
                                                    $change   = (float) ($get('change_given') ?? 0);
                                                    $netPhysical = round($tendered - $change, 2);
                                                    $diff     = round($netPhysical - $netCashTarget, 2);

                                                    $panelColor = match(true) {
                                                        abs($diff) < 0.01 => 'background-color:#f0fdf4; border-color:#86efac;',
                                                        $diff < 0         => 'background-color:#fef2f2; border-color:#fca5a5;',
                                                        default           => 'background-color:#eff6ff; border-color:#93c5fd;',
                                                    };
                                                    $statusBadge = match(true) {
                                                        abs($diff) < 0.01 => '<span style="padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;background:#dcfce7;color:#15803d;">✓ BALANCED</span>',
                                                        $diff < 0         => '<span style="padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;background:#fee2e2;color:#b91c1c;">⚠ SHORT BY AED '.number_format(abs($diff), 2).'</span>',
                                                        $diff > 0         => '<span style="padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;background:#dbeafe;color:#1d4ed8;">ℹ EXCESS BY AED '.number_format($diff, 2).'</span>',
                                                    };

                                                    return new \Illuminate\Support\HtmlString(
                                                        "<div style='padding:12px;border-radius:8px;border:1px solid;{$panelColor}'>" .
                                                        "<div style='display:grid;grid-template-columns:1fr 1fr;gap:4px 0;font-size:13px;'>" .
                                                        "<div style='color:#6b7280;'>Voucher Total (Gross):</div><div style='text-align:right;font-family:monospace;'>AED " . number_format($target, 2) . "</div>" .
                                                        "<div style='color:#6b7280;'>Less: Cash Advance:</div><div style='text-align:right;font-family:monospace; color:#b91c1c;'>- AED " . number_format($deduction, 2) . "</div>" .
                                                        "<div style='grid-column:span 2;margin:4px 0;border-top:1px dashed #d1d5db;'></div>" .
                                                        "<div style='font-weight:700;'>Net Cash to Pay:</div><div style='text-align:right;font-family:monospace;font-weight:700; font-size:14px;'>AED " . number_format($netCashTarget, 2) . "</div>" .
                                                        "<div style='color:#6b7280;'>Physical Cash:</div><div style='text-align:right;font-family:monospace;'>AED " . number_format($netPhysical, 2) . "</div>" .
                                                        "<div style='grid-column:span 2;margin:6px 0;border-top:1px solid #d1d5db;'></div>" .
                                                        "<div style='font-weight:700;'>Verification:</div><div style='text-align:right;'>{$statusBadge}</div>" .
                                                        "</div>" .
                                                        "</div>"
                                                    );
                                                }),
                                        ]),

                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\Textarea::make('remarks')
                                                ->label('Remarks / Notes')
                                                ->placeholder('Brief notes about physical cash state if needed...')
                                                ->columnSpanFull(),
                                            Forms\Components\Toggle::make('is_change_received')
                                                ->label('Exact change has been verified and accounted for.')
                                                ->inline(false)
                                                ->default(true)
                                                ->visible(fn (Forms\Get $get) => (float)($get('change_given') ?? 0) > 0)
                                                ->columnSpanFull(),
                                        ]),
                                    ])
                            ])
                    ])
                    ->visible(fn (Voucher $record): bool => in_array($record->status, ['pending_checker', 'pending_approver', 'approved']) && auth()->user()->can('voucher.pay'))
                    ->action(function (Voucher $record, array $data) {
                        if (in_array($record->type, ['payment', 'bank_encashment'])) {
                            $payments = $data['multiple_payments'] ?? [];
                            $error = app(\App\Services\VoucherApprovalService::class)->markPaid($record, auth()->user(), $payments, []);
                            if ($error) {
                                Notification::make()->title($error)->danger()->send();
                                return;
                            }
                            Notification::make()->title('Cheque details successfully recorded')->success()->send();
                            $record->refresh();
                            return;
                        }

                        $error = app(\App\Services\VoucherApprovalService::class)->markPaid($record, auth()->user(), [], $data);
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
            RelationManagers\PurchaseEntriesRelationManager::class,
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
                    \Filament\Infolists\Components\Section::make('Approval Progress')
                        ->schema([
                            \Filament\Infolists\Components\ViewEntry::make('approvals_timeline')
                                ->label('')
                                ->view('filament.infolists.approval-timeline'),
                        ])
                        ->columnSpan(1),
                ]),

                // ── MIDDLE ROW: Voucher Overview — Premium Split Layout ─────────────────────
                \Filament\Infolists\Components\Section::make('Voucher Overview')
                    ->icon('heroicon-o-information-circle')
                    ->compact()
                    ->schema([
                        \Filament\Infolists\Components\Split::make([
                            // Major Column: Financials & Identity
                            \Filament\Infolists\Components\Grid::make(1)->schema([
                                \Filament\Infolists\Components\Grid::make(3)->schema([
                                    \Filament\Infolists\Components\TextEntry::make('voucher_number')
                                        ->label('Voucher Number')
                                        ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                        ->size(\Filament\Infolists\Components\TextEntry\TextEntrySize::Large)
                                        ->color('primary')
                                        ->icon('heroicon-m-hashtag')
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
                                        ->formatStateUsing(fn (string $state): string => strtoupper(str_replace('_', ' ', $state))),

                                    \Filament\Infolists\Components\TextEntry::make('type')
                                        ->label('Classification')
                                        ->weight(\Filament\Support\Enums\FontWeight::SemiBold)
                                        ->icon('heroicon-m-tag')
                                        ->formatStateUsing(fn ($state) => match($state) {
                                            'petty_cash'      => 'Petty Cash',
                                            'receipt'         => 'Receipt Voucher',
                                            'bank_encashment' => 'Bank Encashment',
                                            default           => 'Payment Voucher'
                                        }),
                                ]),

                                \Filament\Infolists\Components\Grid::make(2)->schema([
                                    \Filament\Infolists\Components\TextEntry::make('amount')
                                        ->label('Disbursement Amount')
                                        ->money('aed')
                                        ->weight(\Filament\Support\Enums\FontWeight::ExtraBold)
                                        ->size(\Filament\Infolists\Components\TextEntry\TextEntrySize::Large)
                                        ->color('primary')
                                        ->extraAttributes(['class' => 'font-mono text-2xl px-4 py-2 bg-primary-50 dark:bg-primary-900/10 rounded-lg inline-block']),

                                    \Filament\Infolists\Components\TextEntry::make('payee')
                                        ->label('Payee / Beneficiary')
                                        ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                        ->icon('heroicon-m-user-group'),
                                ]),

                                \Filament\Infolists\Components\TextEntry::make('description')
                                    ->label('Purpose of Expenditure')
                                    ->placeholder('N/A')
                                    ->prose()
                                    ->columnSpanFull(),
                                
                                \Filament\Infolists\Components\TextEntry::make('transaction_summary')
                                    ->label('Accountant Remarks')
                                    ->placeholder('—')
                                    ->prose()
                                    ->columnSpanFull(),
                            ])->columnSpan(3),

                            // Minor Column: Audit sidebar
                            \Filament\Infolists\Components\Section::make('Identity & Date')
                                ->compact()
                                ->schema([
                                    \Filament\Infolists\Components\TextEntry::make('user.name')
                                        ->label('Prepared By')
                                        ->icon('heroicon-m-user-circle'),

                                    \Filament\Infolists\Components\TextEntry::make('department')
                                        ->label('Department')
                                        ->placeholder('General')
                                        ->icon('heroicon-m-briefcase'),

                                    \Filament\Infolists\Components\TextEntry::make('category.name')
                                        ->label('Expense Category')
                                        ->placeholder('Uncategorized')
                                        ->icon('heroicon-m-square-3-stack-3d'),
                                    
                                    \Filament\Infolists\Components\TextEntry::make('created_at')
                                        ->label('Submitted On')
                                        ->dateTime('d M Y, h:i A')
                                        ->icon('heroicon-m-calendar-days'),
                                ])
                                ->columnSpan(1)
                                ->grow(false),
                        ])->from('lg'),

                        \Filament\Infolists\Components\Section::make('Bank Settlement Details')
                            ->visible(fn ($record) => !empty($record->cheque_no) || !empty($record->multiple_payments))
                            ->schema([
                                \Filament\Infolists\Components\RepeatableEntry::make('multiple_payments')
                                    ->label('Recorded Payments')
                                    ->hidden(fn ($record) => empty($record->multiple_payments))
                                    ->schema([
                                        \Filament\Infolists\Components\Grid::make(4)->schema([
                                            \Filament\Infolists\Components\TextEntry::make('cheque_no')
                                                ->label('Reference')
                                                ->weight(\Filament\Support\Enums\FontWeight::Bold),
                                            \Filament\Infolists\Components\TextEntry::make('cheque_date')
                                                ->label('Date')
                                                ->date('d M Y'),
                                            \Filament\Infolists\Components\TextEntry::make('bank')
                                                ->label('Bank'),
                                            \Filament\Infolists\Components\TextEntry::make('amount')
                                                ->label('Amount')
                                                ->money('aed')
                                                ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                                ->color('primary'),
                                        ]),
                                    ])
                                    ->columnSpanFull(),

                                // Legacy view for single payment vouchers without multiple_payments array
                                \Filament\Infolists\Components\Grid::make(3)
                                    ->hidden(fn ($record) => !empty($record->multiple_payments))
                                    ->schema([
                                        \Filament\Infolists\Components\TextEntry::make('cheque_no')
                                            ->label('Cheque Reference')
                                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                            ->fontFamily('mono')
                                            ->icon('heroicon-m-credit-card'),
                                        \Filament\Infolists\Components\TextEntry::make('cheque_date')
                                            ->label('Release Date')
                                            ->date('d M Y')
                                            ->icon('heroicon-m-calendar'),
                                        \Filament\Infolists\Components\TextEntry::make('bank')
                                            ->label('Issuing Bank')
                                            ->icon('heroicon-m-building-library'),
                                    ]),
                            ]),

                        \Filament\Infolists\Components\TextEntry::make('attachment_paths')
                            ->label('Supporting Evidence')
                            ->columnSpanFull()
                            ->formatStateUsing(fn (string $state): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                                '<a href="' . asset('storage/' . implode('/', array_map('rawurlencode', explode('/', $state)))) . '" target="_blank" class="text-primary-600 dark:text-primary-400 hover:underline inline-flex items-center gap-1.5 font-medium italic underline decoration-primary-500/30 underline-offset-4">' .
                                '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>' .
                                basename($state) . '</a>'
                            ))
                            ->html()
                            ->listWithLineBreaks(),
                    ]),

                // ── LINE ITEMS: Heavy Tabular View ──────────────────────────────────────────
                \Filament\Infolists\Components\Section::make('Line Items')
                    ->icon('heroicon-o-list-bullet')
                    ->schema([
                        \Filament\Infolists\Components\ViewEntry::make('items')
                            ->label('')
                            ->view('filament.infolists.voucher-items-table')
                            ->columnSpanFull(),
                    ]),

                // ── OPTIONAL: Cash Breakdown ────────────────────────────────────────────────
                \Filament\Infolists\Components\Section::make('Physical Cash Breakdown')
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
                                ->label(str_contains($col, 'coin') ? "AED " . number_format($val, 2) : "AED {$val} Banknote")
                                ->formatStateUsing(fn ($state) => "{$state} units × AED " . number_format($val, (str_contains($col, 'coin') && $val < 1 ? 2 : 0)) . " = AED " . number_format($state * $val, 2))
                                ->visible(fn ($record) => optional($record->denominations)->{$col} > 0);
                        }
                        $fields[] = \Filament\Infolists\Components\TextEntry::make('denominations.total_amount')
                                ->label('Physical Cash Counted')
                                ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                ->color('primary')
                                ->money('AED');

                        $fields[] = \Filament\Infolists\Components\TextEntry::make('denominations.change_given')
                                ->label('Less: Change Returned')
                                ->color('warning')
                                ->money('AED')
                                ->visible(fn ($record) => optional($record->denominations)->change_given > 0);

                        $fields[] = \Filament\Infolists\Components\TextEntry::make('denominations.prior_deduction')
                                ->label('Plus: Cash Advance / Deductions')
                                ->color('danger')
                                ->money('AED')
                                ->visible(fn ($record) => optional($record->denominations)->prior_deduction > 0);

                        $fields[] = \Filament\Infolists\Components\TextEntry::make('denominations.final_balanced_amount')
                                ->label('Total Balanced Amount')
                                ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                ->color('success')
                                ->money('AED')
                                ->state(fn ($record) => (optional($record->denominations)->total_amount - optional($record->denominations)->change_given) + optional($record->denominations)->prior_deduction)
                                ->columnSpanFull();

                        return $fields;
                    })->columns(['sm' => 2, 'md' => 4]),

                // ── FOOTER: Historical Timeline ─────────────────────────────────────────────
                \Filament\Infolists\Components\Section::make('System Log / History')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        \Filament\Infolists\Components\ViewEntry::make('activity_log')
                            ->label('')
                            ->view('filament.infolists.activity-log'),
                    ])
                    ->collapsed(),
            ])->columns(1);
    }
}

