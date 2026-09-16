<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\BankReconciliationResource\Pages;
use App\Filament\Vouchers\Resources\VoucherResource;
use App\Models\AccountCode;
use App\Models\Voucher;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class BankReconciliationResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static ?string $slug = 'bank-reconciliations';

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Banks Reconciliations';

    protected static ?string $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 60;

    protected static ?string $breadcrumb = 'Banks Reconciliations';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['floatReplenishment'])
            ->whereIn('type', ['payment', 'bank_encashment'])
            ->where(function ($q) {
                $q->whereNotNull('bank')
                    ->where('bank', '!=', '')
                    ->orWhereNotNull('multiple_payments');
            });
    }

    // ─────────────────────────────────────────────────────────────────────
    // TABLE
    // ─────────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        $validAccountCodes = AccountCode::where('is_active', true)
            ->where('code', 'like', '1%')
            ->pluck('name', 'code')
            ->toArray();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bank')
                    ->label('Bank Account / Code')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state, Voucher $record) use ($validAccountCodes) {
                        $payments = $record->multiple_payments;

                        // If multiple payments with multiple distinct banks
                        if (!empty($payments) && is_array($payments) && count($payments) > 1) {
                            $badges = [];
                            foreach ($payments as $p) {
                                $bk = trim($p['bank'] ?? '');
                                $amt = isset($p['amount']) ? ' (AED ' . number_format((float) $p['amount'], 2) . ')' : '';
                                if (isset($validAccountCodes[$bk])) {
                                    $name = Str::limit($validAccountCodes[$bk], 22);
                                    $badges[] = "<span style='display:inline-block;margin:1px 0;padding:2px 8px;border-radius:6px;background:#dcfce7;color:#15803d;font-size:11px;font-weight:600;' title='{$validAccountCodes[$bk]}'>✓ {$bk} — {$name}{$amt}</span>";
                                } else {
                                    $badges[] = "<span style='display:inline-block;margin:1px 0;padding:2px 8px;border-radius:6px;background:#fef3c7;color:#92400e;font-size:11px;font-weight:600;'>⚠️ {$bk}{$amt}</span>";
                                }
                            }
                            return new HtmlString(implode('<br>', $badges));
                        }

                        $bk = trim($state ?? ($payments[0]['bank'] ?? ($record->floatReplenishment?->account_code ?? '')));
                        if (empty($bk)) {
                            return new HtmlString("<span style='color:#9ca3af;font-style:italic;'>Not Set</span>");
                        }

                        if (isset($validAccountCodes[$bk])) {
                            $name = $validAccountCodes[$bk];
                            return new HtmlString("
                                <div style='display:flex;flex-direction:column;'>
                                    <span style='font-family:monospace;font-weight:700;color:#15803d;font-size:12px;'>
                                        ✓ {$bk}
                                    </span>
                                    <span style='font-size:11px;color:#4b5563;line-height:1.2;' title='{$name}'>
                                        " . Str::limit($name, 35) . "
                                    </span>
                                </div>
                            ");
                        }

                        // Legacy / Free-text bank name
                        return new HtmlString("
                            <div style='display:inline-flex;align-items:center;gap:4px;background:#fef3c7;border:1px solid #fde68a;padding:3px 8px;border-radius:6px;'>
                                <span style='font-size:11px;'>⚠️</span>
                                <span style='font-size:11px;font-weight:700;color:#92400e;'>" . e($bk) . "</span>
                                <span style='font-size:10px;color:#b45309;'>(Unlinked)</span>
                            </div>
                        ");
                    }),

                Tables\Columns\TextColumn::make('cheque_no')
                    ->label('Bank Ref / Cheque #')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('cheque_no', 'like', "%{$search}%")
                              ->orWhere('multiple_payments', 'like', "%{$search}%")
                              ->orWhereHas('floatReplenishment', function ($fq) use ($search) {
                                  $fq->where('bank_reference', 'like', "%{$search}%");
                              });
                        });
                    })
                    ->formatStateUsing(function ($state, Voucher $record) {
                        if (!empty($record->multiple_payments) && is_array($record->multiple_payments) && count($record->multiple_payments) > 1) {
                            $cheques = array_filter(array_column($record->multiple_payments, 'cheque_no'));
                            return !empty($cheques) ? implode(', ', $cheques) : '—';
                        }
                        return $state ?: ($record->floatReplenishment?->bank_reference ?: '—');
                    }),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('AED')
                    ->sortable()
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('voucher_number')
                    ->label('Voucher #')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('voucher_number', 'like', "%{$search}%")
                              ->orWhereHas('floatReplenishment', function ($fq) use ($search) {
                                  $fq->where('reference', 'like', "%{$search}%")
                                     ->orWhere('bank_reference', 'like', "%{$search}%");
                              });
                        });
                    })
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn(Voucher $record) => VoucherResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab()
                    ->formatStateUsing(function ($state, Voucher $record) {
                        $badge = '';
                        if ($repl = $record->floatReplenishment) {
                            $badge = "<br><span style='display:inline-block;margin-top:2px;padding:1px 6px;border-radius:4px;background:#e0f2fe;color:#0369a1;font-size:10px;font-weight:600;' title='Fund Voucher (Float Replenishment)'>🏦 Fund: {$repl->reference}</span>";
                        }
                        return new HtmlString("<span style='font-weight:700;color:#2563eb;'>#{$state}</span>{$badge}");
                    }),

                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payee')
                    ->label('Payee')
                    ->searchable()
                    ->wrap()
                    ->limit(32),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'paid' => 'success',
                        'approved' => 'info',
                        'rejected' => 'danger',
                        'draft' => 'gray',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state)),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('bank')
                    ->label('Bank Account')
                    ->searchable()
                    ->options(function () use ($validAccountCodes) {
                        $options = [];
                        foreach ($validAccountCodes as $code => $name) {
                            $options[$code] = "{$code} — {$name}";
                        }

                        // Legacy free-text banks in database
                        $legacy = Voucher::whereIn('type', ['payment', 'bank_encashment'])
                            ->whereNotNull('bank')
                            ->where('bank', '!=', '')
                            ->whereNotIn('bank', array_keys($validAccountCodes))
                            ->distinct()
                            ->pluck('bank')
                            ->toArray();

                        foreach ($legacy as $bk) {
                            if (!empty(trim($bk))) {
                                $options[$bk] = "⚠️ {$bk} (Legacy Unlinked)";
                            }
                        }

                        return $options;
                    })
                    ->query(function (Builder $query, array $data) {
                        $val = $data['value'] ?? null;
                        if (empty($val))
                            return $query;
                        return $query->where(function ($q) use ($val) {
                            $q->where('bank', $val)
                                ->orWhereRaw("JSON_SEARCH(multiple_payments, 'one', ?) IS NOT NULL", [$val]);
                        });
                    }),

                Tables\Filters\SelectFilter::make('link_status')
                    ->label('Bank Link Status')
                    ->options([
                        'linked' => '✅ Linked to Bank Account Code',
                        'unlinked' => '⚠️ Needs Bank Link (Legacy Free Text)',
                    ])
                    ->query(function (Builder $query, array $data) use ($validAccountCodes) {
                        $val = $data['value'] ?? null;
                        if (empty($val))
                            return $query;
                        $validKeys = array_keys($validAccountCodes);
                        if ($val === 'linked') {
                            return $query->whereIn('bank', $validKeys);
                        }
                        if ($val === 'unlinked') {
                            return $query->whereNotIn('bank', $validKeys);
                        }
                        return $query;
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Voucher Status')
                    ->options([
                        'paid' => 'Paid',
                        'approved' => 'Approved',
                        'pending' => 'Pending',
                    ]),
            ])
            ->actions([
                // ── Edit Bank Action (Manual Editing) ─────────────────────
                Tables\Actions\Action::make('edit_bank')
                    ->label('Edit Bank')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->modalHeading(fn(Voucher $record) => "Edit Payment References — Voucher #{$record->voucher_number}")
                    ->modalDescription('Update the bank accounts, cheque references, dates, and amounts for this voucher. Multiple banks and split payments are supported.')
                    ->modalWidth('4xl')
                    ->fillForm(function (Voucher $record) {
                        $payments = $record->multiple_payments;
                        if (empty($payments) || !is_array($payments)) {
                            $payments = [
                                [
                                    'cheque_no'   => $record->cheque_no ?: ($record->floatReplenishment?->bank_reference ?? ''),
                                    'cheque_date' => $record->cheque_date ? $record->cheque_date->format('Y-m-d') : ($record->date ? $record->date->format('Y-m-d') : now()->format('Y-m-d')),
                                    'bank'        => $record->bank ?: ($record->floatReplenishment?->account_code ?? ''),
                                    'amount'      => $record->amount,
                                ]
                            ];
                        }
                        return [
                            'voucher_amount'    => $record->amount,
                            'multiple_payments' => $payments,
                        ];
                    })
                    ->form(function (Voucher $record) {
                        $bankOptions = AccountCode::where('is_active', true)
                            ->where('code', 'like', '1%')
                            ->get()
                            ->mapWithKeys(fn($ac) => [
                                $ac->code => "<span class='font-mono text-xs text-gray-500'>{$ac->code}</span> &nbsp; <strong>{$ac->name}</strong>"
                            ])
                            ->toArray();

                        return [
                            Forms\Components\Hidden::make('voucher_amount')
                                ->default($record->amount),

                            Forms\Components\Repeater::make('multiple_payments')
                                ->label('Payment References')
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
                                            ->options($bankOptions)
                                            ->required(),
                                        Forms\Components\TextInput::make('amount')
                                            ->label('Amount')
                                            ->numeric()
                                            ->prefix('AED')
                                            ->required()
                                            ->live(debounce: 500),
                                    ]),
                                ])
                                ->reorderable(false)
                                ->addActionLabel('+ Add to payment References')
                                ->itemLabel(fn(array $state): ?string => 
                                    ($state['cheque_no'] ?? 'Payment') . 
                                    ($state['amount'] ? ' — AED ' . number_format((float) $state['amount'], 2) : '')
                                )
                                ->hint(function (Forms\Get $get) use ($record) {
                                    $payments = $get('multiple_payments') ?? [];
                                    $total = collect($payments)->sum(fn($p) => (float)($p['amount'] ?? 0));
                                    $target = (float)($get('voucher_amount') ?: $record->amount);

                                    if (abs($total - $target) < 0.01) {
                                        return new HtmlString('<span style="color:#16a34a;font-weight:700;">✅ Total Matches (AED ' . number_format($total, 2) . ')</span>');
                                    }
                                    return new HtmlString('<span style="color:#dc2626;font-weight:700;">⚠️ Total (AED ' . number_format($total, 2) . ') must equal AED ' . number_format($target, 2) . '</span>');
                                }),
                        ];
                    })
                    ->action(function (Voucher $record, array $data) {
                        $payments = $data['multiple_payments'] ?? [];
                        if (empty($payments)) {
                            Notification::make()
                                ->title('At least one payment reference is required.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $total = collect($payments)->sum(fn($p) => (float)($p['amount'] ?? 0));
                        if (abs($total - (float)$record->amount) >= 0.01) {
                            Notification::make()
                                ->title('Amount Mismatch')
                                ->body('The total of payment references (AED ' . number_format($total, 2) . ') must equal the voucher amount (AED ' . number_format((float)$record->amount, 2) . ').')
                                ->danger()
                                ->send();
                            return;
                        }

                        $firstPayment = $payments[0] ?? [];
                        $primaryBank = $firstPayment['bank'] ?? $record->bank;
                        $primaryCheque = $firstPayment['cheque_no'] ?? $record->cheque_no;
                        $primaryDate = $firstPayment['cheque_date'] ?? $record->cheque_date;

                        $record->update([
                            'bank'              => $primaryBank,
                            'cheque_no'         => $primaryCheque,
                            'cheque_date'       => $primaryDate,
                            'multiple_payments' => $payments,
                        ]);

                        // Also sync to linked float replenishment if present
                        if ($record->floatReplenishment) {
                            $record->floatReplenishment->update([
                                'account_code'   => $primaryBank,
                                'bank_reference' => $primaryCheque,
                            ]);
                        }

                        activity()
                            ->performedOn($record)
                            ->causedBy(auth()->user())
                            ->log("Updated payment references / bank accounts for voucher #{$record->voucher_number}");

                        Notification::make()
                            ->title("Payment references updated for #{$record->voucher_number}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('view_voucher')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->url(fn(Voucher $record) => VoucherResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                // ── Bulk Assign Bank Action ──────────────────────────────────
                Tables\Actions\BulkAction::make('bulk_assign_bank')
                    ->label('Assign Bank Account')
                    ->icon('heroicon-m-building-library')
                    ->color('warning')
                    ->modalHeading('Assign Bank Account to Selected Vouchers')
                    ->modalDescription('This will set the chosen Bank Account Code on all selected vouchers.')
                    ->modalWidth('md')
                    ->form([
                        Forms\Components\Select::make('bank')
                            ->label('Bank Account Code')
                            ->required()
                            ->searchable()
                            ->allowHtml()
                            ->options(
                                AccountCode::where('is_active', true)
                                    ->where('code', 'like', '1%')
                                    ->get()
                                    ->mapWithKeys(fn($ac) => [
                                        $ac->code => "<span class='font-mono text-xs text-gray-500'>{$ac->code}</span> &nbsp; <strong>{$ac->name}</strong>"
                                    ])
                                    ->toArray()
                            ),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $newBank = $data['bank'];
                        $count = 0;

                        foreach ($records as $record) {
                            $payments = $record->multiple_payments;
                            if (!empty($payments) && is_array($payments)) {
                                foreach ($payments as &$p) {
                                    $p['bank'] = $newBank;
                                }
                            } else {
                                $payments = [
                                    [
                                        'bank' => $newBank,
                                        'amount' => $record->amount,
                                        'cheque_no' => $record->cheque_no,
                                        'cheque_date' => $record->cheque_date,
                                    ]
                                ];
                            }

                            $record->update([
                                'bank' => $newBank,
                                'multiple_payments' => $payments,
                            ]);

                            activity()
                                ->performedOn($record)
                                ->causedBy(auth()->user())
                                ->log("Bulk assigned bank link to {$newBank}");

                            $count++;
                        }

                        Notification::make()
                            ->title("Successfully updated {$count} voucher(s) to bank {$newBank}")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankReconciliations::route('/'),
        ];
    }
}
