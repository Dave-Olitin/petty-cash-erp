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

    protected static ?string $navigationLabel = 'Bank Accounts & Vouchers';

    protected static ?string $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 60;

    protected static ?string $breadcrumb = 'Bank Accounts & Vouchers';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
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
                Tables\Columns\TextColumn::make('voucher_number')
                    ->label('Voucher #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn (Voucher $record) => VoucherResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payee')
                    ->label('Payee')
                    ->searchable()
                    ->wrap()
                    ->limit(32),

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
                                $amt = isset($p['amount']) ? ' (AED ' . number_format((float)$p['amount'], 2) . ')' : '';
                                if (isset($validAccountCodes[$bk])) {
                                    $name = Str::limit($validAccountCodes[$bk], 22);
                                    $badges[] = "<span style='display:inline-block;margin:1px 0;padding:2px 8px;border-radius:6px;background:#dcfce7;color:#15803d;font-size:11px;font-weight:600;' title='{$validAccountCodes[$bk]}'>✓ {$bk} — {$name}{$amt}</span>";
                                } else {
                                    $badges[] = "<span style='display:inline-block;margin:1px 0;padding:2px 8px;border-radius:6px;background:#fef3c7;color:#92400e;font-size:11px;font-weight:600;'>⚠️ {$bk}{$amt}</span>";
                                }
                            }
                            return new HtmlString(implode('<br>', $badges));
                        }

                        $bk = trim($state ?? ($payments[0]['bank'] ?? ''));
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
                    ->label('Cheque #')
                    ->searchable()
                    ->formatStateUsing(function ($state, Voucher $record) {
                        if (!empty($record->multiple_payments) && is_array($record->multiple_payments) && count($record->multiple_payments) > 1) {
                            $cheques = array_filter(array_column($record->multiple_payments, 'cheque_no'));
                            return !empty($cheques) ? implode(', ', $cheques) : '—';
                        }
                        return $state ?: '—';
                    }),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('AED')
                    ->sortable()
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'paid' => 'success',
                        'approved' => 'info',
                        'rejected' => 'danger',
                        'draft' => 'gray',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
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
                        if (empty($val)) return $query;
                        return $query->where(function ($q) use ($val) {
                            $q->where('bank', $val)
                              ->orWhereRaw("JSON_SEARCH(multiple_payments, 'one', ?) IS NOT NULL", [$val]);
                        });
                    }),

                Tables\Filters\SelectFilter::make('link_status')
                    ->label('Bank Link Status')
                    ->options([
                        'linked'   => '✅ Linked to Bank Account Code',
                        'unlinked' => '⚠️ Needs Bank Link (Legacy Free Text)',
                    ])
                    ->query(function (Builder $query, array $data) use ($validAccountCodes) {
                        $val = $data['value'] ?? null;
                        if (empty($val)) return $query;
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
                        'paid'     => 'Paid',
                        'approved' => 'Approved',
                        'pending'  => 'Pending',
                    ]),
            ])
            ->actions([
                // ── Edit Bank Action (Manual Editing) ─────────────────────
                Tables\Actions\Action::make('edit_bank')
                    ->label('Edit Bank')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->modalHeading(fn (Voucher $record) => "Edit Bank Link — Voucher #{$record->voucher_number}")
                    ->modalDescription('Manually update the bank account code or cheque details for this voucher.')
                    ->modalWidth('lg')
                    ->form(function (Voucher $record) use ($validAccountCodes) {
                        $currentBank = $record->bank;
                        $isCurrentValid = isset($validAccountCodes[$currentBank]);

                        $infoHtml = "
                            <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:8px;display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;'>
                                <div><span style='color:#64748b;'>Voucher #:</span> <strong>#{$record->voucher_number}</strong></div>
                                <div><span style='color:#64748b;'>Amount:</span> <strong>AED " . number_format($record->amount, 2) . "</strong></div>
                                <div><span style='color:#64748b;'>Payee:</span> " . e($record->payee) . "</div>
                                <div><span style='color:#64748b;'>Current Bank:</span> " . ($isCurrentValid ? "<span style='color:#15803d;font-weight:700;'>✓ {$currentBank}</span>" : "<span style='color:#b45309;font-weight:700;'>⚠️ " . e($currentBank ?: 'None') . " (Unlinked)</span>") . "</div>
                            </div>
                        ";

                        return [
                            Forms\Components\Placeholder::make('voucher_info')
                                ->label('')
                                ->content(new HtmlString($infoHtml)),

                            Forms\Components\Select::make('bank')
                                ->label('Bank Account Code')
                                ->required()
                                ->searchable()
                                ->allowHtml()
                                ->options(
                                    AccountCode::where('is_active', true)
                                        ->where('code', 'like', '1%')
                                        ->get()
                                        ->mapWithKeys(fn ($ac) => [
                                            $ac->code => "<span class='font-mono text-xs text-gray-500'>{$ac->code}</span> &nbsp; <strong>{$ac->name}</strong>"
                                        ])
                                        ->toArray()
                                )
                                ->default($isCurrentValid ? $currentBank : null)
                                ->helperText(!$isCurrentValid && !empty($currentBank) ? "Currently saved as: '{$currentBank}'. Select the matching account code above." : 'Select the cash in bank account code.'),

                            Forms\Components\TextInput::make('cheque_no')
                                ->label('Cheque # / Reference')
                                ->default($record->cheque_no),

                            Forms\Components\DatePicker::make('cheque_date')
                                ->label('Cheque Date')
                                ->native(false)
                                ->displayFormat('d M Y')
                                ->default($record->cheque_date),
                        ];
                    })
                    ->action(function (Voucher $record, array $data) {
                        $newBank = $data['bank'];
                        $chequeNo = $data['cheque_no'] ?? $record->cheque_no;
                        $chequeDate = $data['cheque_date'] ?? $record->cheque_date;

                        $payments = $record->multiple_payments;
                        if (!empty($payments) && is_array($payments)) {
                            foreach ($payments as &$p) {
                                $p['bank'] = $newBank;
                                if (!empty($chequeNo)) $p['cheque_no'] = $chequeNo;
                                if (!empty($chequeDate)) $p['cheque_date'] = $chequeDate;
                            }
                        } else {
                            $payments = [[
                                'bank'        => $newBank,
                                'amount'      => $record->amount,
                                'cheque_no'   => $chequeNo,
                                'cheque_date' => $chequeDate,
                            ]];
                        }

                        $record->update([
                            'bank'              => $newBank,
                            'cheque_no'         => $chequeNo,
                            'cheque_date'       => $chequeDate,
                            'multiple_payments' => $payments,
                        ]);

                        activity()
                            ->performedOn($record)
                            ->causedBy(auth()->user())
                            ->log("Manually updated bank link to {$newBank}");

                        Notification::make()
                            ->title("Bank link updated for Voucher #{$record->voucher_number}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('view_voucher')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->url(fn (Voucher $record) => VoucherResource::getUrl('view', ['record' => $record]))
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
                                    ->mapWithKeys(fn ($ac) => [
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
                                $payments = [[
                                    'bank'        => $newBank,
                                    'amount'      => $record->amount,
                                    'cheque_no'   => $record->cheque_no,
                                    'cheque_date' => $record->cheque_date,
                                ]];
                            }

                            $record->update([
                                'bank'              => $newBank,
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
