<?php

namespace App\Filament\Vouchers\Resources\BankReconciliationResource\Pages;

use App\Filament\Vouchers\Resources\BankReconciliationResource;
use App\Filament\Vouchers\Widgets\BankReconciliationOverviewWidget;
use App\Models\AccountCode;
use App\Models\Voucher;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ListBankReconciliations extends ListRecords
{
    protected static string $resource = BankReconciliationResource::class;

    protected static ?string $title = 'Banks Reconciliations';

    public function getSubheading(): ?string
    {
        return '';
    }

    // ──────────────────────────────────────────────────────────────────────
    // Known mapping of legacy free-text bank names → correct account codes.
    // Based on the audit of 225 paid payment vouchers.
    // ──────────────────────────────────────────────────────────────────────
    protected static array $legacyBankMapping = [
        // TRIMMERS / TG → ADCB account 1010-02
        'trimmers adcb' => '1010-02',
        'tg adcb' => '1010-02',

        // iCook → ENBD account 100-02
        'icook enbd' => '100-02',
        'ic enbd' => '100-02',

        // Simply Beauty → FAB account 1010-05
        'simply beauty fab' => '1010-05',
        'sb fab' => '1010-05',
        'fab' => '1010-05',

        // Split entry (had two banks in one field) → ambiguous, skip
        'trimmers adcb / simply beauty fab' => null,

        // Test/junk data → can't auto-map
        'aa' => null,
        'aaa' => null,
    ];

    public function getTabs(): array
    {
        $validCodes = AccountCode::where('is_active', true)
            ->where('code', 'like', '1%')
            ->pluck('code')
            ->toArray();

        $unlinkedCount = Voucher::whereIn('type', ['payment', 'bank_encashment'])
            ->where(function ($q) {
                $q->whereNotNull('bank')->where('bank', '!=', '')
                    ->orWhereNotNull('multiple_payments');
            })
            ->whereNotIn('bank', $validCodes)
            ->count();

        $linkedCount = Voucher::whereIn('type', ['payment', 'bank_encashment'])
            ->whereIn('bank', $validCodes)
            ->count();

        return [
            'all' => Tab::make('All Bank Vouchers'),

            'linked' => Tab::make('✅ Linked to Bank Account')
                ->badge($linkedCount)
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereIn('bank', $validCodes)),

            'unlinked' => Tab::make('⚠️ Needs Link (Legacy Free Text)')
                ->badge($unlinkedCount)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNotIn('bank', $validCodes)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // ── Batch Fix Legacy Bank Names (Admin only) ───────────────────
            Actions\Action::make('fix_legacy_banks')
                ->label('Bank Reconcile')
                ->color('warning')
                ->modalHeading('Batch Fix Legacy Bank Names in Payment Vouchers')
                ->modalWidth('4xl')
                ->modalSubmitActionLabel('Apply All Corrections')
                ->mountUsing(function (Forms\Form $form) {
                    $validCodes = AccountCode::pluck('code')->toArray();

                    $paid = Voucher::whereIn('type', ['payment', 'bank_encashment'])
                        ->where('status', 'paid')
                        ->get(['id', 'voucher_number', 'bank', 'multiple_payments']);

                    $freeTextGroups = [];
                    foreach ($paid as $v) {
                        $payments = $v->multiple_payments ?? [['bank' => $v->bank]];
                        foreach ($payments as $p) {
                            $bk = trim($p['bank'] ?? '');
                            if (empty($bk) || in_array($bk, $validCodes)) continue;
                            $normalized = strtolower($bk);
                            if (!isset($freeTextGroups[$bk])) {
                                $suggestedCode = static::$legacyBankMapping[$normalized] ?? null;
                                $freeTextGroups[$bk] = [
                                    'count'   => 0,
                                    'suggest' => $suggestedCode,
                                ];
                            }
                            $freeTextGroups[$bk]['count']++;
                        }
                    }

                    // Pre-fill the repeater rows
                    $rows = [];
                    foreach ($freeTextGroups as $legacyName => $info) {
                        $rows[] = [
                            'legacy_name'    => $legacyName,
                            'voucher_count'  => $info['count'],
                            'assign_to'      => $info['suggest'] ?? '',
                            'is_auto_mapped' => !empty($info['suggest']) ? '1' : '0',
                        ];
                    }

                    $form->fill(['mappings' => $rows]);
                })
                ->form(function () {
                    $bankOptions = AccountCode::where('is_active', true)
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn($ac) => [$ac->code => "{$ac->code} — {$ac->name}"])
                        ->toArray();

                    return [
                        Forms\Components\Repeater::make('mappings')
                            ->label('Legacy Bank Names → Assign To')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->schema([
                                // Hidden data carriers
                                Forms\Components\Hidden::make('legacy_name'),
                                Forms\Components\Hidden::make('voucher_count'),
                                Forms\Components\Hidden::make('is_auto_mapped'),

                                Forms\Components\Grid::make(12)->schema([
                                    // Color-coded legacy name badge
                                    Forms\Components\Placeholder::make('_legacy_label')
                                        ->label('Legacy Bank Name')
                                        ->columnSpan(5)
                                        ->content(function (Forms\Get $get): HtmlString {
                                            $name        = $get('legacy_name') ?? '—';
                                            $isAuto      = $get('is_auto_mapped') === '1';
                                            $bg          = $isAuto ? '#f0fdf4' : '#fef9c3';
                                            $border      = $isAuto ? '#86efac' : '#fde68a';
                                            $textColor   = $isAuto ? '#15803d' : '#92400e';
                                            return new HtmlString(
                                                "<span style='display:inline-flex;align-items:center;gap:5px;background:{$bg};border:1px solid {$border};padding:3px 10px;border-radius:6px;max-width:100%;overflow:hidden;'>" .
                                                "<span style='font-family:monospace;font-size:12px;font-weight:700;color:{$textColor};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'>" . e($name) . "</span>" .
                                                "</span>"
                                            );
                                        }),

                                    // Voucher count
                                    Forms\Components\Placeholder::make('_count_label')
                                        ->label('Count')
                                        ->columnSpan(1)
                                        ->content(fn(Forms\Get $get): HtmlString => new HtmlString(
                                            "<span style='display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:#e0e7ff;color:#3730a3;font-size:12px;font-weight:700;'>" .
                                            ($get('voucher_count') ?? 0) .
                                            "</span>"
                                        )),

                                    // Status badge: ✅ Auto-assigned or ⚠️ Cannot map
                                    Forms\Components\Placeholder::make('_status_label')
                                        ->label('Status')
                                        ->columnSpan(2)
                                        ->content(function (Forms\Get $get): HtmlString {
                                            if ($get('is_auto_mapped') === '1') {
                                                return new HtmlString(
                                                    "<span style='display:inline-block;padding:2px 8px;border-radius:6px;background:#dcfce7;color:#15803d;font-size:11px;font-weight:700;border:1px solid #86efac;'>✅ Auto</span>"
                                                );
                                            }
                                            return new HtmlString(
                                                "<span style='display:inline-block;padding:2px 8px;border-radius:6px;background:#fef3c7;color:#92400e;font-size:11px;font-weight:700;border:1px solid #fde68a;'>⚠️ Manual</span>"
                                            );
                                        }),

                                    // Compact select dropdown
                                    Forms\Components\Select::make('assign_to')
                                        ->label('Assign To')
                                        ->columnSpan(4)
                                        ->options($bankOptions)
                                        ->searchable()
                                        ->placeholder('— Skip —')
                                        ->nullable()
                                        ->extraAttributes(['style' => 'font-size:12px;']),
                                ]),
                            ])
                            ->itemLabel(function (array $state): string {
                                $icon = ($state['is_auto_mapped'] ?? '0') === '1' ? '✅' : '⚠️';
                                return $icon . ' ' . ($state['legacy_name'] ?? '?') .
                                    '  (' . ($state['voucher_count'] ?? 0) . ' voucher(s))';
                            })
                            ->default([]),
                    ];
                })
                ->action(function (array $data) {
                    $validCodes = AccountCode::pluck('code')->toArray();

                    // Build a lookup: legacy_name → chosen account code
                    $corrections = [];
                    foreach ($data['mappings'] ?? [] as $row) {
                        $legacyName = trim($row['legacy_name'] ?? '');
                        $assignTo   = trim($row['assign_to'] ?? '');
                        if (!empty($legacyName) && !empty($assignTo)) {
                            $corrections[$legacyName] = $assignTo;
                        }
                    }

                    $paid = Voucher::whereIn('type', ['payment', 'bank_encashment'])
                        ->where('status', 'paid')
                        ->get(['id', 'bank', 'multiple_payments']);

                    $fixed   = 0;
                    $skipped = 0;

                    foreach ($paid as $v) {
                        $changed  = false;
                        $payments = $v->multiple_payments ?? null;

                        if ($payments !== null) {
                            $newPayments = [];
                            foreach ($payments as $p) {
                                $bk = trim($p['bank'] ?? '');
                                if (!empty($bk) && !in_array($bk, $validCodes)) {
                                    $corrected = $corrections[$bk] ?? null;
                                    if ($corrected) {
                                        $p['bank'] = $corrected;
                                        $changed = true;
                                        $fixed++;
                                    } else {
                                        $skipped++;
                                    }
                                }
                                $newPayments[] = $p;
                            }

                            if ($changed) {
                                $firstBankFixed = $newPayments[0]['bank'] ?? $v->bank;
                                \Illuminate\Support\Facades\DB::table('vouchers')
                                    ->where('id', $v->id)
                                    ->update([
                                        'multiple_payments' => json_encode($newPayments),
                                        'bank'              => $firstBankFixed,
                                    ]);
                            }
                        } else {
                            $bk = trim($v->bank ?? '');
                            if (!empty($bk) && !in_array($bk, $validCodes)) {
                                $corrected = $corrections[$bk] ?? null;
                                if ($corrected) {
                                    \Illuminate\Support\Facades\DB::table('vouchers')
                                        ->where('id', $v->id)
                                        ->update(['bank' => $corrected]);
                                    $fixed++;
                                } else {
                                    $skipped++;
                                }
                            }
                        }
                    }

                    Notification::make()
                        ->title("✅ {$fixed} bank name(s) corrected! {$skipped} skipped.")
                        ->success()
                        ->send();
                })
                ->visible(fn() => auth()->user()->hasAnyRole(['Super Admin', 'Admin'])),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            BankReconciliationOverviewWidget::class,
        ];
    }
}
