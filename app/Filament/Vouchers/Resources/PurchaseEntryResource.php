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
                                'return' => '↩ Purchase Return — Credit Note / Supplier Return (reduces AP)',
                            ])
                            ->default('purchase')
                            ->required()
                            ->live()
                            ->native(false)
                            ->helperText(
                                fn(Forms\Get $get) => $get('entry_type') === 'return'
                                    ? new \Illuminate\Support\HtmlString('<span class="text-xs text-green-600 font-semibold">✓ Debits the Supplier Account Code (reduces what is owed to the supplier).</span>')
                                    : new \Illuminate\Support\HtmlString('<span class="text-xs text-gray-500">Normal invoice from a vendor. Increases Accounts Payable until paid.</span>')
                            ),
                    ])->columns(1)->compact(),

                // ── Purchase Bill / Return Details ─────────────────────────
                Forms\Components\Section::make(fn(Forms\Get $get) => $get('entry_type') === 'return' ? 'Purchase Return Details' : 'Purchase Bill Details')->schema([

                    Forms\Components\Select::make('entity')
                        ->label('Entity')
                        ->options(\App\Models\VoucherTemplate::where('is_active', true)->pluck('company_name', 'company_name'))
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Hidden::make('branch'),

                    Forms\Components\Select::make('tax_registration_id')
                        ->label('Supplier (Name & TRN)')
                        ->relationship('taxRegistration', 'name', fn(\Illuminate\Database\Eloquent\Builder $query) => $query->where('is_active', true))
                        ->getOptionLabelFromRecordUsing(fn($record) => $record->name)
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
                        ->getOptionLabelFromRecordUsing(fn($record) => $record->code . ' — ' . $record->name)
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
                        ->label(fn(Forms\Get $get) => $get('entry_type') === 'return' ? 'Return Date' : 'Bill Date')
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
                        ->visible(fn(Forms\Get $get) => $get('entry_type') !== 'return')
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
                            ->label(fn(Forms\Get $get) => $get('entry_type') === 'return' ? 'Credit Memo / Invoice #' : 'Invoice Number')
                            ->placeholder('e.g. INV-2023001'),
                    ])
                    ->columns(2)
                    ->collapsed(),

                // ── Entry Lines ───────────────────────────────────────────
                Forms\Components\Section::make(fn(Forms\Get $get) => $get('entry_type') === 'return' ? 'Return Items' : 'Purchase Items')->schema([
                    Forms\Components\Repeater::make('lines')
                        ->relationship('lines')
                        ->live()
                        ->schema([
                            Forms\Components\Grid::make(12)
                                ->schema([
                                    Forms\Components\TextInput::make('description')
                                        ->label('Item Description')
                                        ->placeholder('Nature of expense / item name...')
                                        ->required()
                                        ->columnSpan(['default' => 12, 'md' => 4]),

                                    Forms\Components\Select::make('branch')
                                        ->label('Branch')
                                        ->options(\App\Models\LedgerBranch::pluck('name', 'name'))
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select Branch')
                                        ->columnSpan(['default' => 12, 'md' => 2]),

                                    Forms\Components\Select::make('debit_account_id')
                                        ->relationship('debitAccount', 'code')
                                        ->label(fn(Forms\Get $get) => $get('../../entry_type') === 'return' ? 'Account (Reversal)' : 'Account Code')
                                        ->getOptionLabelFromRecordUsing(fn($record) => $record->code . ' — ' . $record->name)
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
                                        ->columnSpan(['default' => 12, 'md' => 4]),

                                    Forms\Components\TextInput::make('amount')
                                        ->label(fn(Forms\Get $get) => $get('../../entry_type') === 'return' ? 'Return Amount' : 'Amount')
                                        ->numeric()
                                        ->required()
                                        ->prefix('AED')
                                        ->extraInputAttributes(['class' => 'font-bold text-primary-600'])
                                        ->live(onBlur: true)
                                        ->afterStateHydrated(function (Forms\Components\TextInput $component, $state, ?\App\Models\PurchaseEntryLine $record) {
                                            if ($record && ($state === null || (float) $state === 0.0)) {
                                                $amt = max((float) ($record->debit ?? 0), (float) ($record->credit ?? 0), (float) ($record->total ?? 0), (float) ($record->amount ?? 0));
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
                                        ->columnSpan(['default' => 12, 'md' => 2]),

                                    // Hidden accounting columns synced automatically
                                    Forms\Components\Hidden::make('debit')->default(0),
                                    Forms\Components\Hidden::make('credit')->default(0),
                                    Forms\Components\Hidden::make('total')->default(0),
                                    Forms\Components\Hidden::make('tax_percentage')->default(0),
                                    Forms\Components\Hidden::make('tax_amount')->default(0),
                                ])
                        ])
                        ->itemLabel(fn(array $state): ?string => $state['description'] ?? 'New Line Item')
                        ->collapsible()
                        ->cloneable()
                        ->defaultItems(1)
                ]),

                // ── Totals & Balance ──────────────────────────────────────
                Forms\Components\Section::make('Totals & Balance')
                    ->schema([
                        Forms\Components\Placeholder::make('grand_total_sum')
                            ->hiddenLabel()
                            ->content(function (Forms\Get $get) {
                                $lines = $get('lines') ?? [];
                                $sum = (float) collect($lines)->sum(function ($i) {
                                    return max((float) ($i['amount'] ?? 0), (float) ($i['debit'] ?? 0), (float) ($i['credit'] ?? 0), (float) ($i['total'] ?? 0));
                                });
                                $isReturn = $get('entry_type') === 'return';

                                return new \Illuminate\Support\HtmlString(
                                    '<div class="flex items-center justify-between p-4 rounded-xl bg-gray-50 border border-gray-200 dark:bg-gray-800 dark:border-gray-700">' .
                                    '<div>' .
                                    '<div class="text-xs uppercase tracking-wider text-gray-500 font-semibold">' . ($isReturn ? 'Total Refund Amount' : 'Invoice Grand Total') . '</div>' .
                                    '<div class="text-xs text-gray-400 mt-0.5">' . ($isReturn ? 'Total credit note amount to be reversed' : 'Total payable to supplier') . '</div>' .
                                    '</div>' .
                                    '<div class="flex items-baseline gap-2">' .
                                    ($isReturn ? '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300 uppercase">Return</span>' : '') .
                                    '<span class="text-3xl font-mono font-black text-primary-600 dark:text-primary-400">' . number_format($sum, 2) . '</span>' .
                                    '<span class="text-sm font-bold text-gray-500 font-mono">AED</span>' .
                                    '</div>' .
                                    '</div>'
                                );
                            }),
                    ])->compact(),

                // ── GL Impact Preview (Accounting Entry) ──────────────────
                Forms\Components\Section::make('GL Impact Preview (Accounting Entry)')
                    ->description('Review the double-entry accounting transactions that will be posted to the General Ledger.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('accounting_entry_preview')
                            ->hiddenLabel()
                            ->content(function (Forms\Get $get) {
                                return static::renderGlPreviewHtml([
                                    'lines' => $get('lines') ?? [],
                                    'entry_type' => $get('entry_type') ?? 'purchase',
                                    'supplier_account_id' => $get('supplier_account_id'),
                                    'tax_registration_id' => $get('tax_registration_id'),
                                ]);
                            }),
                    ]),


                // ── Payment Status ────────────────────────────────────────
                Forms\Components\Section::make('Payment Status')
                    ->description('Track how much of this bill has been paid.')
                    ->schema([
                        Forms\Components\Select::make('payment_status')
                            ->label('Status')
                            ->options([
                                'unpaid' => '🔴 Unpaid',
                                'partial' => '🟡 Partially Paid',
                                'paid' => '🟢 Fully Paid',
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
                                $paid = (float) $state;
                                $lines = $get('lines') ?? [];
                                $total = (float) collect($lines)->sum(fn($i) => max((float) ($i['debit'] ?? 0), (float) ($i['total'] ?? 0)));
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
                            ->visible(fn(Forms\Get $get) => $get('payment_status') !== 'unpaid'),

                        Forms\Components\Placeholder::make('balance_due_display')
                            ->label('Balance Due')
                            ->content(function (Forms\Get $get) {
                                $bal = (float) $get('balance_due');
                                $color = $bal > 0 ? 'text-red-600' : 'text-green-600';
                                return new \Illuminate\Support\HtmlString('<div class="text-lg font-mono font-bold ' . $color . '">' . number_format($bal, 2) . ' <span class="text-xs font-normal text-gray-500">AED</span></div>');
                            })
                            ->visible(fn(Forms\Get $get) => $get('payment_status') !== 'unpaid'),

                        Forms\Components\Hidden::make('balance_due'),
                    ])
                    ->columns(3)
                    ->collapsed(fn($context) => $context === 'create'),
            ]);
    }

    /**
     * Render the GL Impact Preview as a clean, professional accounting worksheet / table sheet.
     */
    public static function renderGlPreviewHtml(array $data): \Illuminate\Support\HtmlString
    {
        $lines = is_array($data['lines'] ?? null) ? $data['lines'] : [];
        $entryType = $data['entry_type'] ?? 'purchase';
        $isReturn = $entryType === 'return';

        // Calculate sum from all valid lines
        $sum = (float) collect($lines)->sum(function ($i) {
            if (!is_array($i)) {
                return 0;
            }
            return max(
                (float) ($i['amount'] ?? 0),
                (float) ($i['debit'] ?? 0),
                (float) ($i['credit'] ?? 0),
                (float) ($i['total'] ?? 0)
            );
        });
        $formattedSum = number_format($sum, 2);

        // Resolve Supplier AP Account
        $supplierAcctId = $data['supplier_account_id'] ?? null;
        $supplierAcct = null;
        if ($supplierAcctId) {
            $supplierAcct = \App\Models\AccountCode::find($supplierAcctId);
        } elseif ($taxId = ($data['tax_registration_id'] ?? null)) {
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

        $apCode = $supplierAcct ? $supplierAcct->code : '2000-01';
        $apName = $supplierAcct ? $supplierAcct->name : 'Accounts Payable (Supplier)';
        $apDesc = $isReturn ? 'Supplier AP Liability Reduction' : 'Supplier Accounts Payable';
        $apBranch = '—';

        // Fetch Account Codes for all lines
        $accountIds = collect($lines)
            ->map(fn($l) => is_array($l) ? ($l['debit_account_id'] ?? $l['credit_account_id'] ?? null) : null)
            ->filter()
            ->unique();
        $accounts = $accountIds->isNotEmpty()
            ? \App\Models\AccountCode::whereIn('id', $accountIds)->get()->keyBy('id')
            : collect();

        // Build item rows
        $itemRows = [];
        $validLinesCount = 0;

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $amt = max(
                (float) ($line['amount'] ?? 0),
                (float) ($line['debit'] ?? 0),
                (float) ($line['credit'] ?? 0),
                (float) ($line['total'] ?? 0)
            );
            $acctId = $line['debit_account_id'] ?? $line['credit_account_id'] ?? null;
            $acct = $acctId ? $accounts->get($acctId) : null;

            $code = $acct ? e($acct->code) : '—';
            $name = $acct ? e($acct->name) : '<span class="text-amber-600 dark:text-amber-400 italic">Account Code not selected</span>';
            $desc = !empty(trim($line['description'] ?? '')) ? e(trim($line['description'])) : '<span class="text-slate-400 dark:text-slate-500">—</span>';
            $branch = !empty(trim($line['branch'] ?? '')) ? e(trim($line['branch'])) : '<span class="text-slate-400 dark:text-slate-500">—</span>';
            $formattedAmt = number_format($amt, 2);

            $itemRows[] = [
                'type' => $isReturn ? 'CR' : 'DR',
                'code' => $code,
                'name' => $name,
                'desc' => $desc,
                'branch' => $branch,
                'debit' => $isReturn ? '—' : $formattedAmt,
                'credit' => $isReturn ? $formattedAmt : '—',
            ];
            $validLinesCount++;
        }

        // Supplier row
        $supplierRow = [
            'type' => $isReturn ? 'DR' : 'CR',
            'code' => e($apCode),
            'name' => e($apName),
            'desc' => e($apDesc),
            'branch' => e($apBranch),
            'debit' => $isReturn ? $formattedSum : '—',
            'credit' => $isReturn ? '—' : $formattedSum,
        ];

        // Combine rows in standard accounting order: DR first, then CR
        $allRows = [];
        if ($isReturn) {
            // Return: DR Supplier AP, then CR Items
            $allRows[] = $supplierRow;
            foreach ($itemRows as $row) {
                $allRows[] = $row;
            }
        } else {
            // Bill: DR Items, then CR Supplier AP
            foreach ($itemRows as $row) {
                $allRows[] = $row;
            }
            $allRows[] = $supplierRow;
        }

        // Generate tbody HTML
        $tbodyHtml = '';
        if ($validLinesCount === 0) {
            $placeholderMsg = $isReturn ? 'No return items entered yet' : 'No purchase items entered yet';
            $tbodyHtml = '<tr>' .
                '<td colspan="7" class="py-8 text-center text-slate-400 dark:text-slate-500 italic text-xs border-b border-slate-200 dark:border-slate-700">' .
                $placeholderMsg . ' — Add line items above to populate the ledger worksheet.' .
                '</td>' .
                '</tr>';
        } else {
            foreach ($allRows as $r) {
                $isDr = $r['type'] === 'DR';
                $badge = $isDr
                    ? '<span style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:20px; border-radius:4px; font-family:ui-monospace, monospace; font-size:10px; font-weight:800; background-color:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; box-shadow:0 1px 2px 0 rgba(0, 0, 0, 0.03);">DR</span>'
                    : '<span style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:20px; border-radius:4px; font-family:ui-monospace, monospace; font-size:10px; font-weight:800; background-color:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; box-shadow:0 1px 2px 0 rgba(0, 0, 0, 0.03);">CR</span>';

                $debitVal = $r['debit'] !== '—'
                    ? '<span class="font-mono font-bold text-emerald-700 dark:text-emerald-400">' . $r['debit'] . '</span>'
                    : '<span class="text-slate-300 dark:text-slate-600 font-mono">—</span>';

                $creditVal = $r['credit'] !== '—'
                    ? '<span class="font-mono font-bold text-blue-700 dark:text-blue-400">' . $r['credit'] . '</span>'
                    : '<span class="text-slate-300 dark:text-slate-600 font-mono">—</span>';

                $branchDisplay = ($r['branch'] !== '—')
                    ? '<span style="display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:500; background-color:#f1f5f9; color:#334155; border:1px solid #cbd5e1;"><span style="color:#64748b; font-size:10px;"></span>' . $r['branch'] . '</span>'
                    : '<span style="color:#94a3b8;">—</span>';

                $tbodyHtml .= '<tr class="odd:bg-white even:bg-slate-50/70 dark:odd:bg-slate-900 dark:even:bg-slate-800/40 hover:bg-sky-50/50 dark:hover:bg-slate-800/80 transition-colors">' .
                    '<td class="py-2.5 px-3.5 border-r border-b border-slate-200 dark:border-slate-700/80 text-center align-middle">' . $badge . '</td>' .
                    '<td class="py-2.5 px-3.5 border-r border-b border-slate-200 dark:border-slate-700/80 font-mono font-bold text-slate-900 dark:text-slate-100 text-xs align-middle whitespace-nowrap">' . $r['code'] . '</td>' .
                    '<td class="py-2.5 px-3.5 border-r border-b border-slate-200 dark:border-slate-700/80 text-slate-800 dark:text-slate-200 font-medium text-xs align-middle">' . $r['name'] . '</td>' .
                    '<td class="py-2.5 px-3.5 border-r border-b border-slate-200 dark:border-slate-700/80 text-slate-600 dark:text-slate-300 text-xs align-middle">' . $r['desc'] . '</td>' .
                    '<td class="py-2.5 px-3.5 border-r border-b border-slate-200 dark:border-slate-700/80 text-center align-middle whitespace-nowrap">' . $branchDisplay . '</td>' .
                    '<td class="py-2.5 px-3.5 border-r border-b border-slate-200 dark:border-slate-700/80 text-right tabular-nums text-xs align-middle font-mono font-semibold">' . $debitVal . '</td>' .
                    '<td class="py-2.5 px-3.5 border-b border-slate-200 dark:border-slate-700/80 text-right tabular-nums text-xs align-middle font-mono font-semibold">' . $creditVal . '</td>' .
                    '</tr>';
            }
        }

        $typeBadge = $isReturn
            ? '<span style="display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:9999px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; background-color:#fef3c7; color:#92400e; border:1px solid #fde68a;"><span style="width:6px; height:6px; border-radius:50%; background-color:#d97706;"></span>Purchase Return</span>'
            : '<span style="display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:9999px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; background-color:#eff6ff; color:#1e40af; border:1px solid #bfdbfe;"><span style="width:6px; height:6px; border-radius:50%; background-color:#2563eb;"></span>Purchase Bill</span>';

        $statusBadge = '<span style="display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:9999px; font-size:11px; font-weight:700; background-color:#ecfdf5; color:#065f46; border:1px solid #6ee7b7; box-shadow:0 1px 2px 0 rgba(0, 0, 0, 0.05);"><span style="display:inline-block; width:7px; height:7px; border-radius:50%; background-color:#10b981;"></span>BALANCED &bull; AED ' . $formattedSum . '</span>';

        $helperText = $isReturn
            ? 'Credit Note: Debits Supplier AP (' . e($apCode . ' — ' . $apName) . ') to reduce payable liability, and Credits items / costs.'
            : 'Vendor Bill: Debits item expense/asset accounts, and Credits Supplier AP (' . e($apCode . ' — ' . $apName) . ') until settled via payment voucher.';

        return new \Illuminate\Support\HtmlString(
            '<div style="width:100% !important; min-width:100% !important; box-sizing:border-box;" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm overflow-hidden font-sans text-xs">' .
            // Header Tag Section Toolbar
            '<div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; padding:12px 18px; background-color:#f8fafc; border-bottom:1px solid #cbd5e1; width:100%; box-sizing:border-box;">' .
            '<div style="display:flex; align-items:center; gap:10px;">' .
            '<div style="display:flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:6px; background-color:#ffffff; border:1px solid #cbd5e1; color:#334155; box-shadow:0 1px 2px 0 rgba(0, 0, 0, 0.04);">' .
            '<svg style="width:16px; height:16px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>' .
            '</div>' .
            '<div style="display:flex; align-items:center; gap:10px;">' .
            '<span style="font-weight:700; font-size:13px; color:#0f172a; letter-spacing:-0.01em;">GL Impact Worksheet</span>' .
            $typeBadge .
            '</div>' .
            '</div>' .
            '<div>' .
            $statusBadge .
            '</div>' .
            '</div>' .

            // Table Sheet Grid (Full-Width Responsive Table)
            '<div style="width:100% !important; min-width:100% !important; overflow-x:auto;" class="w-full">' .
            '<table style="width:100% !important; min-width:100% !important; border-collapse:collapse; table-layout:auto;" class="w-full text-left">' .
            '<thead>' .
            '<tr class="bg-slate-50 dark:bg-slate-800/80 text-slate-700 dark:text-slate-200 uppercase tracking-wider text-[11px] font-bold border-b border-slate-300 dark:border-slate-700">' .
            '<th style="width:55px; text-align:center; padding:10px 12px;" class="border-r border-slate-200 dark:border-slate-700 text-center">Type</th>' .
            '<th style="width:130px; padding:10px 14px; white-space:nowrap;" class="border-r border-slate-200 dark:border-slate-700">Account Code</th>' .
            '<th style="width:auto; min-width:220px; padding:10px 14px;" class="border-r border-slate-200 dark:border-slate-700">Account Name</th>' .
            '<th style="width:24%; min-width:170px; padding:10px 14px;" class="border-r border-slate-200 dark:border-slate-700">Description / Memo</th>' .
            '<th style="width:140px; padding:10px 14px; text-align:center; white-space:nowrap;" class="border-r border-slate-200 dark:border-slate-700 text-center">Branch</th>' .
            '<th style="width:135px; text-align:right; padding:10px 14px; white-space:nowrap;" class="border-r border-slate-200 dark:border-slate-700 text-right">Debit (AED)</th>' .
            '<th style="width:135px; text-align:right; padding:10px 14px; white-space:nowrap;" class="text-right">Credit (AED)</th>' .
            '</tr>' .
            '</thead>' .
            '<tbody>' .
            $tbodyHtml .
            '</tbody>' .
            '<tfoot>' .
            '<tr class="bg-slate-50 dark:bg-slate-800/90 text-slate-900 dark:text-slate-100 font-bold border-t-2 border-slate-400 dark:border-slate-600">' .
            '<td colspan="5" style="padding:10px 14px; text-align:right; text-transform:uppercase; font-size:11px; letter-spacing:0.04em; color:#475569;" class="border-r border-slate-300 dark:border-slate-700">' .
            'Total Ledger Posting (AED)' .
            '</td>' .
            '<td style="padding:10px 14px; text-align:right; font-family:ui-monospace, monospace; font-weight:800; font-size:14px; color:#047857; border-bottom:4px double #94a3b8;" class="border-r border-slate-300 dark:border-slate-700 tabular-nums">' .
            $formattedSum .
            '</td>' .
            '<td style="padding:10px 14px; text-align:right; font-family:ui-monospace, monospace; font-weight:800; font-size:14px; color:#1d4ed8; border-bottom:4px double #94a3b8;" class="tabular-nums">' .
            $formattedSum .
            '</td>' .
            '</tr>' .
            '</tfoot>' .
            '</table>' .
            '</div>' .

            // Sheet Footer Status Bar
            '<div style="padding:10px 18px; background-color:#f8fafc; border-top:1px solid #cbd5e1; font-size:11px; color:#475569; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; width:100%; box-sizing:border-box;">' .
            '<div style="display:inline-flex; align-items:center; gap:6px;">' .
            '<svg style="width:14px; height:14px; color:#3b82f6; flex-shrink:0;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>' .
            '<span>' . $helperText . '</span>' .
            '</div>' .
            '<div style="font-family:ui-monospace, monospace; font-size:11px; font-weight:700; color:#047857; white-space:nowrap;">' .
            'Net Out-of-Balance: AED 0.00 &bull; Balanced' .
            '</div>' .
            '</div>' .
            '</div>'
        );
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
                    ->formatStateUsing(fn($state) => $state === 'return' ? 'PURCHASE RETURN' : 'PURCHASE BILL')
                    ->color(fn($state) => $state === 'return' ? 'warning' : 'info')
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
                        if ($record->payment_status === 'paid')
                            return 'success';
                        if ($record->due_date && $record->due_date->isPast())
                            return 'danger';
                        return null;
                    }),

                Tables\Columns\TextColumn::make('taxRegistration.name')
                    ->label('Supplier')
                    ->description(fn($record) => $record->supplierAccount ? "{$record->supplierAccount->code} — {$record->supplierAccount->name}" : null)
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
                    ->formatStateUsing(fn($state) => match ($state) {
                        'paid' => 'PAID',
                        'partial' => 'PARTIAL',
                        default => 'UNPAID',
                    })
                    ->color(fn($state) => match ($state) {
                        'paid' => 'success',
                        'partial' => 'warning',
                        default => 'danger',
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
                    ->color(fn($record) => $record->aging_color)
                    ->sortable(false),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Grand Total')
                    ->getStateUsing(fn($record) => $record->isReturn() ? -(float) $record->grand_total : (float) $record->grand_total)
                    ->money('AED')
                    ->sortable()
                    ->color(fn($record) => $record->isReturn() ? 'warning' : null)
                    ->weight('bold')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('balance_due')
                    ->label('Balance Due')
                    ->getStateUsing(fn($record) => $record->isReturn() ? -(float) $record->balance_due : (float) $record->balance_due)
                    ->money('AED')
                    ->sortable()
                    ->color(fn($record) => $record->isReturn() ? 'warning' : ((float) $record->balance_due > 0 ? 'danger' : 'success'))
                    ->weight('bold')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('price_type')
                    ->label('Price Type')
                    ->badge()
                    ->color(fn(string $state): string => $state === 'inclusive' ? 'warning' : 'info')
                    ->formatStateUsing(fn($state) => $state === 'inclusive' ? 'VAT Inclusive' : 'VAT Exclusive')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // ── Entry Type ──────────────────────────────────────────
                Tables\Filters\SelectFilter::make('entry_type')
                    ->label('Entry Type')
                    ->multiple()
                    ->options([
                        'purchase' => 'Purchase Bills',
                        'return' => 'Purchase Returns',
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
                        'unpaid' => 'Unpaid',
                        'partial' => 'Partially Paid',
                        'paid' => 'Fully Paid',
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
                            ->when($data['date_from'], fn($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['date_until'], fn($q, $date) => $q->whereDate('date', '<=', $date));
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
                                'current' => '🟢 Current',
                                '1_30' => '🔵 1–30 Days',
                                '31_60' => '🟡 31–60 Days',
                                '61_90' => '🟠 61–90 Days',
                                '90_plus' => '🔴 90+ Days',
                            ])
                            ->placeholder('All Aging Buckets'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        $buckets = $data['aging_buckets'] ?? [];
                        if (empty($buckets))
                            return $query;

                        return $query->where(function ($q) use ($buckets) {
                            foreach ($buckets as $bucket) {
                                $q->orWhere(function ($sub) use ($bucket) {
                                    match ($bucket) {
                                        'current' => $sub->where(function ($s) {
                                                $s->where('payment_status', 'paid')
                                                ->orWhereNull('due_date')
                                                ->orWhere('due_date', '>=', now()->toDateString());
                                            }),
                                        '1_30' => $sub->whereIn('payment_status', ['unpaid', 'partial'])
                                            ->whereNotNull('due_date')
                                            ->where('due_date', '<', now()->toDateString())
                                            ->where('due_date', '>=', now()->subDays(30)->toDateString()),
                                        '31_60' => $sub->whereIn('payment_status', ['unpaid', 'partial'])
                                            ->whereNotNull('due_date')
                                            ->where('due_date', '<', now()->subDays(30)->toDateString())
                                            ->where('due_date', '>=', now()->subDays(60)->toDateString()),
                                        '61_90' => $sub->whereIn('payment_status', ['unpaid', 'partial'])
                                            ->whereNotNull('due_date')
                                            ->where('due_date', '<', now()->subDays(60)->toDateString())
                                            ->where('due_date', '>=', now()->subDays(90)->toDateString()),
                                        '90_plus' => $sub->whereIn('payment_status', ['unpaid', 'partial'])
                                            ->whereNotNull('due_date')
                                            ->where('due_date', '<', now()->subDays(90)->toDateString()),
                                        default => null,
                                    };
                                });
                            }
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        $labels = [
                            'current' => 'Current',
                            '1_30' => '1–30 Days',
                            '31_60' => '31–60 Days',
                            '61_90' => '61–90 Days',
                            '90_plus' => '90+ Days',
                        ];
                        return collect($data['aging_buckets'] ?? [])
                            ->map(fn($b) => 'Aging: ' . ($labels[$b] ?? $b))
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
                    ->url(fn() => \App\Filament\Vouchers\Pages\AgingReportPage::getUrl()),

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
                    ->label(fn($record) => $record->is_locked ? 'Unlock' : 'Lock')
                    ->icon(fn($record) => $record->is_locked ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                    ->color(fn($record) => $record->is_locked ? 'warning' : 'danger')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->visible(fn() => auth()->user()->hasAnyRole(['Accountant', 'Admin', 'Super Admin']))
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
                    ->modalDescription(fn($record) => "Are you sure you want to duplicate purchase entry {$record->entry_no}? A new unpaid entry will be created and all line items will be copied.")
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
                    ->successRedirectUrl(fn(\Illuminate\Database\Eloquent\Model $replica): string => \App\Filament\Vouchers\Resources\PurchaseEntryResource::getUrl('view', ['record' => $replica])),
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
                            $records->each(fn($r) => $r->update([
                                'payment_status' => 'paid',
                                'amount_paid' => $r->grand_total,
                                'balance_due' => 0,
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
            'all' => Tab::make('All Entries'),
            'purchase' => Tab::make('Purchase Bills')
                ->modifyQueryUsing(fn($query) => $query->purchases()),
            'return' => Tab::make('Purchase Returns')
                ->modifyQueryUsing(fn($query) => $query->returns()),
            'unpaid' => Tab::make('Unpaid / Overdue')
                ->modifyQueryUsing(fn($query) => $query->unpaid()),
        ];
    }

    // ── Pages ─────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseEntries::route('/'),
            'create' => Pages\CreatePurchaseEntry::route('/create'),
            'view' => Pages\ViewPurchaseEntry::route('/{record}'),
            'edit' => Pages\EditPurchaseEntry::route('/{record}/edit'),
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
                                    ->formatStateUsing(fn($state) => $state === 'return' ? 'PURCHASE RETURN' : 'PURCHASE BILL')
                                    ->color(fn($state) => $state === 'return' ? 'warning' : 'info')
                                    ->extraAttributes(['class' => 'font-bold tracking-tighter']),
                                \Filament\Infolists\Components\TextEntry::make('entity')
                                    ->label('Entity'),
                                \Filament\Infolists\Components\TextEntry::make('taxRegistration.name')
                                    ->label('Supplier')
                                    ->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('supplierAccount.name')
                                    ->label('Supplier Account (AP)')
                                    ->formatStateUsing(fn($state, $record) => $record->supplierAccount ? "{$record->supplierAccount->code} — {$record->supplierAccount->name}" : '—')
                                    ->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('invoice_no')
                                    ->label('Invoice / Doc No')
                                    ->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('date')
                                    ->label(fn($record) => $record->isReturn() ? 'Return Date' : 'Bill Date')
                                    ->date('M j, Y'),
                                \Filament\Infolists\Components\TextEntry::make('due_date')
                                    ->label('Due Date')
                                    ->date('M j, Y')
                                    ->placeholder('—')
                                    ->visible(fn($record) => !$record->isReturn()),
                                \Filament\Infolists\Components\TextEntry::make('po_number')
                                    ->label('PO Number')
                                    ->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('user.name')
                                    ->label('Created By')
                                    ->placeholder('System'),
                                \Filament\Infolists\Components\TextEntry::make('payment_status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn($state, $record) => $record->isReturn() ? 'SETTLED' : match ($state) {
                                        'paid' => 'PAID',
                                        'partial' => 'PARTIAL',
                                        default => 'UNPAID',
                                    })
                                    ->color(fn($state, $record) => $record->isReturn() ? 'success' : match ($state) {
                                        'paid' => 'success',
                                        'partial' => 'warning',
                                        default => 'danger',
                                    })
                                    ->extraAttributes(['class' => 'font-bold tracking-tighter']),
                                \Filament\Infolists\Components\TextEntry::make('aging_bucket')
                                    ->label('Aging')
                                    ->badge()
                                    ->color(fn($record) => $record->aging_color)
                                    ->visible(fn($record) => !$record->isReturn()),
                            ])->columns(5),
                    ]),

                \Filament\Infolists\Components\Section::make(fn($record) => $record->isReturn() ? 'Return Lines (Accounting Entry)' : 'Purchase Lines (Accounting Entry)')
                    ->icon('heroicon-o-scale')
                    ->description('Double-entry General Ledger posting sheet for this transaction.')
                    ->collapsible()
                    ->schema([
                        \Filament\Infolists\Components\ViewEntry::make('gl_accounting_entry')
                            ->hiddenLabel()
                            ->view('filament.infolists.purchase-entry-gl-table')
                            ->extraAttributes(['class' => 'w-full !max-w-none', 'style' => 'width: 100% !important;'])
                            ->columnSpanFull(),
                    ]),

                \Filament\Infolists\Components\Section::make(fn($record) => $record->isReturn() ? 'Return Summary' : 'Purchase Summary')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(4)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('grand_total')
                                    ->label(fn($record) => $record->isReturn() ? 'Total Refund' : 'Grand Total')
                                    ->money('AED')
                                    ->extraAttributes(['class' => 'text-2xl font-mono font-bold text-primary-600 pl-4 border-l-4 border-primary-500']),
                                \Filament\Infolists\Components\TextEntry::make('amount_paid')
                                    ->label('Amount Paid')
                                    ->money('AED')
                                    ->extraAttributes(['class' => 'text-xl font-mono font-bold text-green-600 pl-4 border-l-4 border-green-400'])
                                    ->visible(fn($record) => !$record->isReturn()),
                                \Filament\Infolists\Components\TextEntry::make('balance_due')
                                    ->label('Balance Due')
                                    ->money('AED')
                                    ->extraAttributes(['class' => 'text-xl font-mono font-bold text-red-600 pl-4 border-l-4 border-red-400'])
                                    ->visible(fn($record) => !$record->isReturn()),
                            ])
                    ])->compact(),

                \Filament\Infolists\Components\Section::make('Linked Payments')
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('vouchers')
                            ->label('Payment Vouchers')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('voucher_number')
                                    ->label('Voucher No')
                                    ->url(fn($record) => \App\Filament\Vouchers\Resources\VoucherResource::getUrl('view', ['record' => $record]))
                                    ->color('primary')
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
                                \Filament\Infolists\Components\TextEntry::make('pivot.amount_applied')
                                    ->label('Amount Applied')
                                    ->money('AED'),
                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn($state) => match ($state) { 'paid' => 'success', 'void' => 'danger', default => 'warning'}),
                            ])->columns(3)
                            ->hidden(fn($record) => $record->vouchers->isEmpty()),

                        \Filament\Infolists\Components\RepeatableEntry::make('journalEntries')
                            ->label('Journal Entries')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('entry_no')
                                    ->label('Entry No')
                                    ->url(fn($record) => \App\Filament\Vouchers\Resources\JournalEntryResource::getUrl('edit', ['record' => $record]))
                                    ->color('primary')
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
                                \Filament\Infolists\Components\TextEntry::make('pivot.amount_applied')
                                    ->label('Amount Applied')
                                    ->money('AED'),
                            ])->columns(2)
                            ->hidden(fn($record) => $record->journalEntries->isEmpty()),
                    ])->hidden(fn($record) => $record->vouchers->isEmpty() && $record->journalEntries->isEmpty()),
            ]);
    }
}
