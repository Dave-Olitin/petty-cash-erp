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
        if ($record->is_locked && !auth()->user()->hasAnyRole(['Accountant', 'Admin', 'Super Admin'])) {
            return false;
        }
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin']) || auth()->user()->can('purchase_entry.edit');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if ($record->is_locked && !auth()->user()->hasAnyRole(['Accountant', 'Admin', 'Super Admin'])) {
            return false;
        }
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin']) || auth()->user()->can('purchase_entry.delete');
    }

    // ── Eager-load ────────────────────────────────────────────────────────

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with([
            'taxRegistration',
            'supplierAccount',
            'refundAccount',
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
                                'return'   => '↩ Purchase Return — Credit Note / Supplier Return (reduces AP)',
                            ])
                            ->default('purchase')
                            ->required()
                            ->live()
                            ->native(false)
                            ->helperText(fn (Forms\Get $get) => $get('entry_type') === 'return'
                                ? new \Illuminate\Support\HtmlString('<span class="text-xs text-green-600 font-semibold">✓ Debits the Supplier Account Code (reduces what is owed to the supplier).</span>')
                                : new \Illuminate\Support\HtmlString('<span class="text-xs text-gray-500">Normal invoice from a vendor. Increases Accounts Payable until paid.</span>')
                            ),
                    ])->columns(1)->compact(),

                // ── Purchase Bill / Return Details ─────────────────────────
                Forms\Components\Section::make(fn (Forms\Get $get) => $get('entry_type') === 'return' ? 'Purchase Return Details' : 'Purchase Bill Details')->schema([

                    Forms\Components\Select::make('entity')
                        ->label('Entity')
                        ->options(\App\Models\VoucherTemplate::where('is_active', true)->pluck('company_name', 'company_name'))
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Hidden::make('branch'),

                    Forms\Components\Select::make('tax_registration_id')
                        ->label('Supplier (Name & TRN)')
                        ->relationship('taxRegistration', 'name', fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('is_active', true))
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                            if ($state) {
                                $tax = \App\Models\TaxRegistration::find($state);
                                if ($tax) {
                                    if ($tax->payment_terms && $get('date')) {
                                        $date = \Carbon\Carbon::parse($get('date'));
                                        if (preg_match('/(\d+)/', $tax->payment_terms, $matches)) {
                                            $set('due_date', $date->addDays((int) $matches[1])->format('Y-m-d'));
                                        } else {
                                            $set('due_date', $date->format('Y-m-d'));
                                        }
                                    }

                                    // Auto-link to matching Supplier Account Code (e.g. 2000-01.xx)
                                    $matchingAcct = \App\Models\AccountCode::where('code', 'like', '2000-01.%')
                                        ->where('name', $tax->name)
                                        ->first();
                                    if (!$matchingAcct) {
                                        $matchingAcct = \App\Models\AccountCode::where('name', 'like', '%' . trim($tax->name) . '%')
                                            ->where('type', 'liability')
                                            ->first();
                                    }
                                    if ($matchingAcct) {
                                        $set('supplier_account_id', $matchingAcct->id);
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

                    Forms\Components\Select::make('supplier_account_id')
                        ->label('Supplier Account Code (AP)')
                        ->relationship('supplierAccount', 'code', function ($query) {
                            return $query->where(function ($q) {
                                $q->where('code', 'like', '2000-01%')
                                  ->orWhere('type', 'liability');
                            })->where('is_active', true)->orderBy('code');
                        })
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->code . ' — ' . $record->name)
                        ->searchable(['code', 'name'])
                        ->preload()
                        ->live()
                        ->helperText('Supplier liability account in Chart of Accounts (e.g. 2000-01.xx).')
                        ->afterStateHydrated(function ($component, $state, ?\App\Models\PurchaseEntry $record) {
                            if ($record && empty($state) && $record->taxRegistration) {
                                $matching = \App\Models\AccountCode::where('code', 'like', '2000-01.%')
                                    ->where('name', $record->taxRegistration->name)
                                    ->first();
                                if ($matching) {
                                    $component->state($matching->id);
                                }
                            }
                        })
                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                            if ($state && !$get('tax_registration_id')) {
                                $acct = \App\Models\AccountCode::find($state);
                                if ($acct) {
                                    $tax = \App\Models\TaxRegistration::where('name', $acct->name)
                                        ->orWhere('name', 'like', '%' . trim($acct->name) . '%')
                                        ->first();
                                    if ($tax) {
                                        $set('tax_registration_id', $tax->id);
                                    }
                                }
                            }
                        }),

                    // ── Dates ────────────────────────────────────────────
                    Forms\Components\DatePicker::make('date')
                        ->label(fn (Forms\Get $get) => $get('entry_type') === 'return' ? 'Return Date' : 'Bill Date')
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
                        ->visible(fn (Forms\Get $get) => $get('entry_type') !== 'return')
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
                            ->label(fn (Forms\Get $get) => $get('entry_type') === 'return' ? 'Credit Memo / Invoice #' : 'Invoice Number')
                            ->placeholder('e.g. INV-2023001'),
                    ])
                    ->columns(2)
                    ->collapsed(),

                // ── Entry Lines ───────────────────────────────────────────
                Forms\Components\Section::make(fn (Forms\Get $get) => $get('entry_type') === 'return' ? 'Return Items' : 'Purchase Items')->schema([
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
                                                ->label(fn (Forms\Get $get) => $get('../../entry_type') === 'return' ? 'Account (Expense / Item Being Reversed)' : 'Account')
                                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->code . ' — ' . $record->name)
                                                ->searchable(['code', 'name'])
                                                ->native(false)
                                                ->required()
                                                ->afterStateHydrated(function ($component, $state, ?\App\Models\PurchaseEntryLine $record) {
                                                    if ($record && empty($state)) {
                                                        if ($record->credit_account_id) {
                                                            $component->state($record->credit_account_id);
                                                        }
                                                    }
                                                })
                                                ->columnSpan(8),

                                            // ── Simple, Clean Amount Field ───────
                                            Forms\Components\TextInput::make('amount')
                                                ->label(fn (Forms\Get $get) => $get('../../entry_type') === 'return' ? 'Return Amount (AED)' : 'Amount (AED)')
                                                ->numeric()
                                                ->required()
                                                ->prefix('AED')
                                                ->extraInputAttributes(['class' => 'font-bold text-primary-600'])
                                                ->live(onBlur: true)
                                                ->afterStateHydrated(function (Forms\Components\TextInput $component, $state, ?\App\Models\PurchaseEntryLine $record) {
                                                    if ($record && ($state === null || (float)$state === 0.0)) {
                                                        $amt = max((float)($record->debit ?? 0), (float)($record->credit ?? 0), (float)($record->total ?? 0), (float)($record->amount ?? 0));
                                                        $component->state($amt);
                                                    }
                                                })
                                                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                                    $val = (float) ($state ?? 0);
                                                    $set('total', $val);
                                                    $isReturn = $get('../../entry_type') === 'return';
                                                    if ($isReturn) {
                                                        $set('credit', $val);
                                                        $set('debit', 0);
                                                    } else {
                                                        $set('debit', $val);
                                                        $set('credit', 0);
                                                    }
                                                })
                                                ->columnSpan(4),

                                            // Hidden accounting columns synced automatically
                                            Forms\Components\Hidden::make('debit')->default(0),
                                            Forms\Components\Hidden::make('credit')->default(0),
                                            Forms\Components\Hidden::make('total')->default(0),
                                            Forms\Components\Hidden::make('tax_percentage')->default(0),
                                            Forms\Components\Hidden::make('tax_amount')->default(0),
                                        ])
                                        ->columnSpanFull(),
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
                                Forms\Components\Placeholder::make('grand_total_sum')
                                    ->label(fn (Forms\Get $get) => $get('entry_type') === 'return' ? 'Total Refund Amount' : 'Grand Total (Invoice)')
                                    ->content(function (Forms\Get $get) {
                                        $lines = $get('lines') ?? [];
                                        $sum = (float) collect($lines)->sum(function ($i) {
                                            return max((float)($i['amount'] ?? 0), (float)($i['debit'] ?? 0), (float)($i['credit'] ?? 0), (float)($i['total'] ?? 0));
                                        });
                                        $isReturn = $get('entry_type') === 'return';

                                        return new \Illuminate\Support\HtmlString(
                                            '<div class="flex flex-col p-3 rounded-xl bg-gray-50 border border-gray-100 dark:bg-gray-800 dark:border-gray-700">' .
                                            '<div class="flex items-center gap-2">' .
                                            ($isReturn ? '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700 uppercase">Return</span>' : '') .
                                            '<span class="text-3xl font-mono font-black text-primary-600">' . number_format($sum, 2) . '</span>' .
                                            '</div>' .
                                            '<span class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold mt-1">' . ($isReturn ? 'Total Refund — AED' : 'Invoice Total — AED') . '</span>' .
                                            '</div>'
                                        );
                                    }),

                                Forms\Components\Placeholder::make('accounting_entry_preview')
                                    ->label('GL Impact (Journal Entry Preview)')
                                    ->columnSpan(2)
                                    ->content(function (Forms\Get $get) {
                                        $lines = $get('lines') ?? [];
                                        $sum = (float) collect($lines)->sum(function ($i) {
                                            return max((float)($i['amount'] ?? 0), (float)($i['debit'] ?? 0), (float)($i['credit'] ?? 0), (float)($i['total'] ?? 0));
                                        });
                                        $isReturn = $get('entry_type') === 'return';
                                        $formattedSum = number_format($sum, 2);

                                        // Resolve Supplier AP Account
                                        $supplierAcctId = $get('supplier_account_id');
                                        $supplierAcct = null;
                                        if ($supplierAcctId) {
                                            $supplierAcct = \App\Models\AccountCode::find($supplierAcctId);
                                        } elseif ($taxId = $get('tax_registration_id')) {
                                            $tax = \App\Models\TaxRegistration::find($taxId);
                                            if ($tax) {
                                                $supplierAcct = \App\Models\AccountCode::where('code', 'like', '2000-01.%')
                                                    ->where('name', $tax->name)
                                                    ->first();
                                                if (!$supplierAcct) {
                                                    $supplierAcct = \App\Models\AccountCode::where('name', 'like', '%' . trim($tax->name) . '%')
                                                        ->where('type', 'liability')
                                                        ->first();
                                                }
                                            }
                                        }
                                        $apAccountLabel = $supplierAcct ? "{$supplierAcct->code} — {$supplierAcct->name}" : '2000-01 — Accounts Payable (Supplier)';

                                        if ($isReturn) {
                                            return new \Illuminate\Support\HtmlString(
                                                '<div class="rounded-xl border border-green-200 dark:border-green-900 bg-green-50/60 dark:bg-green-950/20 p-3 text-xs">' .
                                                '<div class="flex items-center justify-between font-bold text-green-900 dark:text-green-300 uppercase tracking-wider mb-2">' .
                                                    '<span>GL Impact Preview (Purchase Return)</span>' .
                                                    '<span class="text-[10px] px-2 py-0.5 rounded bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 font-bold">BALANCED</span>' .
                                                '</div>' .
                                                '<table class="w-full text-left font-mono">' .
                                                    '<thead>' .
                                                        '<tr class="text-[10px] text-gray-500 uppercase border-b border-green-200 dark:border-green-800">' .
                                                            '<th class="pb-1 font-semibold">Account</th>' .
                                                            '<th class="pb-1 text-right font-semibold w-24">Debit</th>' .
                                                            '<th class="pb-1 text-right font-semibold w-24">Credit</th>' .
                                                        '</tr>' .
                                                    '</thead>' .
                                                    '<tbody class="divide-y divide-green-100 dark:divide-green-900/40">' .
                                                        '<tr>' .
                                                            '<td class="py-1.5 text-gray-800 dark:text-gray-200 font-sans"><span class="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] font-mono font-black bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 mr-2">DR</span>' . e($apAccountLabel) . '</td>' .
                                                            '<td class="py-1.5 text-right font-bold text-emerald-600 dark:text-emerald-400">' . $formattedSum . '</td>' .
                                                            '<td class="py-1.5 text-right text-gray-400">—</td>' .
                                                        '</tr>' .
                                                        '<tr>' .
                                                            '<td class="py-1.5 text-gray-800 dark:text-gray-200 font-sans"><span class="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] font-mono font-black bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300 mr-2">CR</span>Item Account(s) (Cost Reversed)</td>' .
                                                            '<td class="py-1.5 text-right text-gray-400">—</td>' .
                                                            '<td class="py-1.5 text-right font-bold text-blue-600 dark:text-blue-400">' . $formattedSum . '</td>' .
                                                        '</tr>' .
                                                    '</tbody>' .
                                                '</table>' .
                                                '<div class="mt-2 text-[11px] text-green-800 dark:text-green-300 font-sans">' .
                                                    '✓ Credit Note: Debits Supplier Account (' . e($apAccountLabel) . ') to reduce what is owed to the supplier.' .
                                                '</div>' .
                                                '</div>'
                                            );
                                        }

                                        return new \Illuminate\Support\HtmlString(
                                            '<div class="rounded-xl border border-blue-200 dark:border-blue-900 bg-blue-50/60 dark:bg-blue-950/20 p-3 text-xs">' .
                                            '<div class="flex items-center justify-between font-bold text-blue-900 dark:text-blue-300 uppercase tracking-wider mb-2">' .
                                                '<span>GL Impact Preview (Purchase Bill)</span>' .
                                                '<span class="text-[10px] px-2 py-0.5 rounded bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 font-bold">BALANCED</span>' .
                                            '</div>' .
                                            '<table class="w-full text-left font-mono">' .
                                                '<thead>' .
                                                    '<tr class="text-[10px] text-gray-500 uppercase border-b border-blue-200 dark:border-blue-800">' .
                                                        '<th class="pb-1 font-semibold">Account</th>' .
                                                        '<th class="pb-1 text-right font-semibold w-24">Debit</th>' .
                                                        '<th class="pb-1 text-right font-semibold w-24">Credit</th>' .
                                                    '</tr>' .
                                                '</thead>' .
                                                '<tbody class="divide-y divide-blue-100 dark:divide-blue-900/40">' .
                                                    '<tr>' .
                                                        '<td class="py-1.5 text-gray-800 dark:text-gray-200 font-sans"><span class="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] font-mono font-black bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 mr-2">DR</span>Item Account(s) (Expense/Asset)</td>' .
                                                        '<td class="py-1.5 text-right font-bold text-emerald-600 dark:text-emerald-400">' . $formattedSum . '</td>' .
                                                        '<td class="py-1.5 text-right text-gray-400">—</td>' .
                                                    '</tr>' .
                                                    '<tr>' .
                                                        '<td class="py-1.5 text-gray-800 dark:text-gray-200 font-sans"><span class="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] font-mono font-black bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300 mr-2">CR</span>' . e($apAccountLabel) . '</td>' .
                                                        '<td class="py-1.5 text-right text-gray-400">—</td>' .
                                                        '<td class="py-1.5 text-right font-bold text-blue-600 dark:text-blue-400">' . $formattedSum . '</td>' .
                                                    '</tr>' .
                                                '</tbody>' .
                                            '</table>' .
                                            '<div class="mt-2 text-[11px] text-blue-800 dark:text-blue-300 font-sans">✓ Standard Vendor Bill: Credits Supplier AP Account (' . e($apAccountLabel) . '), increasing payable until settled via payment voucher.</div>' .
                                            '</div>'
                                        );
                                    }),
                            ]),
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
                    ->description(fn ($record) => $record->supplierAccount ? "{$record->supplierAccount->code} — {$record->supplierAccount->name}" : null)
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

                Tables\Columns\TextColumn::make('vouchers.voucher_number')
                    ->label('Linked Vouchers')
                    ->badge()
                    ->searchable()
                    ->separator(',')
                    ->toggleable(),


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
                // ── Entry Type ──────────────────────────────────────────
                Tables\Filters\SelectFilter::make('entry_type')
                    ->label('Entry Type')
                    ->multiple()
                    ->options([
                        'purchase' => 'Purchase Bills',
                        'return'   => 'Purchase Returns',
                    ]),

                // ── Entity ──────────────────────────────────────────────
                Tables\Filters\SelectFilter::make('entity')
                    ->label('Entity')
                    ->multiple()
                    ->options(\App\Models\VoucherTemplate::where('is_active', true)->pluck('company_name', 'company_name'))
                    ->searchable()
                    ->preload(),

                // ── Supplier ────────────────────────────────────────────
                Tables\Filters\SelectFilter::make('tax_registration_id')
                    ->label('Supplier')
                    ->relationship('taxRegistration', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                // ── Custodian ───────────────────────────────────────────
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Custodian')
                    ->relationship('user', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                // ── Payment Status ──────────────────────────────────────
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->multiple()
                    ->options([
                        'unpaid'  => 'Unpaid',
                        'partial' => 'Partially Paid',
                        'paid'    => 'Fully Paid',
                    ]),

                // ── Date Range ──────────────────────────────────────────
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('date_from')->label('Bill Date From'),
                                Forms\Components\DatePicker::make('date_until')->label('Bill Date Until'),
                            ]),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when($data['date_from'],  fn ($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['date_until'], fn ($q, $date) => $q->whereDate('date', '<=', $date));
                    })
                    ->columnSpan(2),

                // ── Aging Bucket ─────────────────────────────────────────
                Tables\Filters\Filter::make('aging_bucket')
                    ->label('Aging Bucket')
                    ->form([
                        Forms\Components\Select::make('aging_buckets')
                            ->label('Aging Bucket')
                            ->multiple()
                            ->options([
                                'current'  => '🟢 Current',
                                '1_30'     => '🔵 1–30 Days',
                                '31_60'    => '🟡 31–60 Days',
                                '61_90'    => '🟠 61–90 Days',
                                '90_plus'  => '🔴 90+ Days',
                            ])
                            ->placeholder('All Aging Buckets'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        $buckets = $data['aging_buckets'] ?? [];
                        if (empty($buckets)) return $query;

                        return $query->where(function ($q) use ($buckets) {
                            foreach ($buckets as $bucket) {
                                $q->orWhere(function ($sub) use ($bucket) {
                                    match ($bucket) {
                                        'current' => $sub->where(function ($s) {
                                            $s->where('payment_status', 'paid')
                                              ->orWhereNull('due_date')
                                              ->orWhere('due_date', '>=', now()->toDateString());
                                        }),
                                        '1_30'    => $sub->whereIn('payment_status', ['unpaid', 'partial'])
                                                         ->whereNotNull('due_date')
                                                         ->where('due_date', '<', now()->toDateString())
                                                         ->where('due_date', '>=', now()->subDays(30)->toDateString()),
                                        '31_60'   => $sub->whereIn('payment_status', ['unpaid', 'partial'])
                                                         ->whereNotNull('due_date')
                                                         ->where('due_date', '<', now()->subDays(30)->toDateString())
                                                         ->where('due_date', '>=', now()->subDays(60)->toDateString()),
                                        '61_90'   => $sub->whereIn('payment_status', ['unpaid', 'partial'])
                                                         ->whereNotNull('due_date')
                                                         ->where('due_date', '<', now()->subDays(60)->toDateString())
                                                         ->where('due_date', '>=', now()->subDays(90)->toDateString()),
                                        '90_plus' => $sub->whereIn('payment_status', ['unpaid', 'partial'])
                                                         ->whereNotNull('due_date')
                                                         ->where('due_date', '<', now()->subDays(90)->toDateString()),
                                        default   => null,
                                    };
                                });
                            }
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        $labels = [
                            'current' => 'Current', '1_30' => '1–30 Days',
                            '31_60'   => '31–60 Days', '61_90' => '61–90 Days', '90_plus' => '90+ Days',
                        ];
                        return collect($data['aging_buckets'] ?? [])
                            ->map(fn ($b) => 'Aging: ' . ($labels[$b] ?? $b))
                            ->all();
                    }),
            ])
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
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
                Tables\Actions\Action::make('toggle_lock')
                    ->label(fn ($record) => $record->is_locked ? 'Unlock' : 'Lock')
                    ->icon(fn ($record) => $record->is_locked ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                    ->color(fn ($record) => $record->is_locked ? 'warning' : 'danger')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->visible(fn () => auth()->user()->hasAnyRole(['Accountant', 'Admin', 'Super Admin']))
                    ->action(function ($record) {
                        $record->update(['is_locked' => !$record->is_locked]);
                        \Filament\Notifications\Notification::make()
                            ->title($record->is_locked ? 'Purchase Entry Locked' : 'Purchase Entry Unlocked')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ViewAction::make()->iconButton(),
                Tables\Actions\EditAction::make()->iconButton(),
                Tables\Actions\ReplicateAction::make()
                    ->iconButton()
                    ->label('Duplicate')
                    ->modalHeading('Duplicate Purchase Entry')
                    ->modalSubmitActionLabel('Duplicate')
                    ->modalWidth(\Filament\Support\Enums\MaxWidth::Medium)
                    ->modalDescription(fn ($record) => "Are you sure you want to duplicate purchase entry {$record->entry_no}? A new unpaid entry will be created and all line items will be copied.")
                    ->excludeAttributes(['entry_no', 'is_locked', 'payment_status', 'amount_paid', 'balance_due'])
                    ->beforeReplicaSaved(function (\Illuminate\Database\Eloquent\Model $replica): void {
                        $replica->payment_status = 'unpaid';
                        $replica->amount_paid = 0;
                        $replica->is_locked = false;
                        $replica->entry_no = '';
                        $replica->user_id = auth()->id();
                    })
                    ->afterReplicaSaved(function (\Illuminate\Database\Eloquent\Model $original, \Illuminate\Database\Eloquent\Model $replica): void {
                        foreach ($original->lines as $line) {
                            $newLine = $line->replicate();
                            $newLine->purchase_entry_id = $replica->id;
                            $newLine->save();
                        }
                    })
                    ->successRedirectUrl(fn (\Illuminate\Database\Eloquent\Model $replica): string => \App\Filament\Vouchers\Resources\PurchaseEntryResource::getUrl('view', ['record' => $replica])),
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
            ])
            ->defaultPaginationPageOption(25);
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
                                    ->label('Supplier')
                                    ->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('supplierAccount.name')
                                    ->label('Supplier Account (AP)')
                                    ->formatStateUsing(fn ($state, $record) => $record->supplierAccount ? "{$record->supplierAccount->code} — {$record->supplierAccount->name}" : '—')
                                    ->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('invoice_no')
                                    ->label('Invoice / Doc No')
                                    ->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('date')
                                    ->label(fn ($record) => $record->isReturn() ? 'Return Date' : 'Bill Date')
                                    ->date('M j, Y'),
                                \Filament\Infolists\Components\TextEntry::make('due_date')
                                    ->label('Due Date')
                                    ->date('M j, Y')
                                    ->placeholder('—')
                                    ->visible(fn ($record) => !$record->isReturn()),
                                \Filament\Infolists\Components\TextEntry::make('po_number')
                                    ->label('PO Number')
                                    ->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('user.name')
                                    ->label('Created By')
                                    ->placeholder('System'),
                                \Filament\Infolists\Components\TextEntry::make('payment_status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn ($state, $record) => $record->isReturn() ? 'SETTLED' : match ($state) {
                                        'paid'    => 'PAID',
                                        'partial' => 'PARTIAL',
                                        default   => 'UNPAID',
                                    })
                                    ->color(fn ($state, $record) => $record->isReturn() ? 'success' : match ($state) {
                                        'paid'    => 'success',
                                        'partial' => 'warning',
                                        default   => 'danger',
                                    })
                                    ->extraAttributes(['class' => 'font-bold tracking-tighter']),
                                \Filament\Infolists\Components\TextEntry::make('aging_bucket')
                                    ->label('Aging')
                                    ->badge()
                                    ->color(fn ($record) => $record->aging_color)
                                    ->visible(fn ($record) => !$record->isReturn()),
                            ])->columns(5),
                    ]),

                \Filament\Infolists\Components\Section::make(fn ($record) => $record->isReturn() ? 'Return Lines (Items)' : 'Purchase Lines (Items)')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(12)
                            ->extraAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 p-2 border-b border-gray-200 dark:border-gray-700 rounded-t-lg'])
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('hdr_acct')
                                    ->state('Account (Chart of Accounts)')
                                    ->hiddenLabel()
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                    ->columnSpan(5),
                                \Filament\Infolists\Components\TextEntry::make('hdr_desc')
                                    ->state('Description / Item')
                                    ->hiddenLabel()
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                    ->columnSpan(4),
                                \Filament\Infolists\Components\TextEntry::make('hdr_branch')
                                    ->state('Branch')
                                    ->hiddenLabel()
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                    ->columnSpan(1),
                                \Filament\Infolists\Components\TextEntry::make('hdr_amt')
                                    ->state('Amount')
                                    ->hiddenLabel()
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                    ->columnSpan(2)
                                    ->alignEnd(),
                            ]),

                        \Filament\Infolists\Components\RepeatableEntry::make('lines')
                            ->label('')
                            ->schema([
                                \Filament\Infolists\Components\Grid::make(12)
                                    ->schema([
                                        \Filament\Infolists\Components\TextEntry::make('account_display')
                                            ->label('Account')
                                            ->hiddenLabel()
                                            ->state(function ($record) {
                                                $acct = $record->debitAccount ?? $record->creditAccount;
                                                return $acct ? "{$acct->code} — {$acct->name}" : '—';
                                            })
                                            ->columnSpan(5),
                                        \Filament\Infolists\Components\TextEntry::make('description')
                                            ->label('Description')
                                            ->hiddenLabel()
                                            ->placeholder('—')
                                            ->columnSpan(4),
                                        \Filament\Infolists\Components\TextEntry::make('branch')
                                            ->label('Branch')
                                            ->hiddenLabel()
                                            ->placeholder('—')
                                            ->columnSpan(1),
                                        \Filament\Infolists\Components\TextEntry::make('line_amount')
                                            ->label('Amount')
                                            ->hiddenLabel()
                                            ->state(function ($record) {
                                                return max(
                                                    (float)($record->debit ?? 0),
                                                    (float)($record->credit ?? 0),
                                                    (float)($record->amount ?? 0),
                                                    (float)($record->total ?? 0)
                                                );
                                            })
                                            ->money('AED')
                                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                            ->extraAttributes(['class' => 'font-mono text-primary-700 dark:text-primary-400'])
                                            ->columnSpan(2)
                                            ->alignEnd(),
                                    ])
                            ])
                            ->columns(1)
                            ->contained(false)
                    ]),

                \Filament\Infolists\Components\Section::make(fn ($record) => $record->isReturn() ? 'Return Summary' : 'Purchase Summary')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(4)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('grand_total')
                                    ->label(fn ($record) => $record->isReturn() ? 'Total Refund' : 'Grand Total')
                                    ->money('AED')
                                    ->extraAttributes(['class' => 'text-2xl font-mono font-bold text-primary-600 pl-4 border-l-4 border-primary-500']),
                                \Filament\Infolists\Components\TextEntry::make('amount_paid')
                                    ->label('Amount Paid')
                                    ->money('AED')
                                    ->extraAttributes(['class' => 'text-xl font-mono font-bold text-green-600 pl-4 border-l-4 border-green-400'])
                                    ->visible(fn ($record) => !$record->isReturn()),
                                \Filament\Infolists\Components\TextEntry::make('balance_due')
                                    ->label('Balance Due')
                                    ->money('AED')
                                    ->extraAttributes(['class' => 'text-xl font-mono font-bold text-red-600 pl-4 border-l-4 border-red-400'])
                                    ->visible(fn ($record) => !$record->isReturn()),
                            ])
                    ])->compact(),

                \Filament\Infolists\Components\Section::make('Linked Payments')
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('vouchers')
                            ->label('Payment Vouchers')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('voucher_number')
                                    ->label('Voucher No')
                                    ->url(fn ($record) => \App\Filament\Vouchers\Resources\VoucherResource::getUrl('view', ['record' => $record]))
                                    ->color('primary')
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
                                \Filament\Infolists\Components\TextEntry::make('pivot.amount_applied')
                                    ->label('Amount Applied')
                                    ->money('AED'),
                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn ($state) => match ($state) { 'paid' => 'success', 'void' => 'danger', default => 'warning' }),
                            ])->columns(3)
                            ->hidden(fn ($record) => $record->vouchers->isEmpty()),
                            
                        \Filament\Infolists\Components\RepeatableEntry::make('journalEntries')
                            ->label('Journal Entries')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('entry_no')
                                    ->label('Entry No')
                                    ->url(fn ($record) => \App\Filament\Vouchers\Resources\JournalEntryResource::getUrl('edit', ['record' => $record]))
                                    ->color('primary')
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
                                \Filament\Infolists\Components\TextEntry::make('pivot.amount_applied')
                                    ->label('Amount Applied')
                                    ->money('AED'),
                            ])->columns(2)
                            ->hidden(fn ($record) => $record->journalEntries->isEmpty()),
                    ])->hidden(fn ($record) => $record->vouchers->isEmpty() && $record->journalEntries->isEmpty()),
            ]);
    }
}
