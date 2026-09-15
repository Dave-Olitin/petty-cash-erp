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

    protected static ?string $title = 'Bank Accounts & Voucher Logs';

    public function getSubheading(): ?string
    {
        return 'Review payment vouchers linked to bank accounts, check account codes, and manually edit bank assignments.';
    }

    // ──────────────────────────────────────────────────────────────────────
    // Known mapping of legacy free-text bank names → correct account codes.
    // Based on the audit of 225 paid payment vouchers.
    // ──────────────────────────────────────────────────────────────────────
    protected static array $legacyBankMapping = [
        // TRIMMERS / TG → ADCB account 1010-02
        'trimmers adcb'                    => '1010-02',
        'tg adcb'                          => '1010-02',

        // iCook → ENBD account 100-02
        'icook enbd'                       => '100-02',
        'ic enbd'                          => '100-02',

        // Simply Beauty → FAB account 1010-05
        'simply beauty fab'                => '1010-05',
        'sb fab'                           => '1010-05',
        'fab'                              => '1010-05',

        // Split entry (had two banks in one field) → ambiguous, skip
        'trimmers adcb / simply beauty fab' => null,

        // Test/junk data → can't auto-map
        'aa'                               => null,
        'aaa'                              => null,
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
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('bank', $validCodes)),

            'unlinked' => Tab::make('⚠️ Needs Link (Legacy Free Text)')
                ->badge($unlinkedCount)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('bank', $validCodes)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // ── Batch Fix Legacy Bank Names (Admin only) ───────────────────
            Actions\Action::make('fix_legacy_banks')
                ->label('Batch Fix Legacy Bank Names')
                ->icon('heroicon-m-wrench-screwdriver')
                ->color('warning')
                ->modalHeading('Batch Fix Legacy Bank Names in Payment Vouchers')
                ->modalWidth('4xl')
                ->modalSubmitActionLabel('Apply All Corrections')
                ->form(function () {
                    $validCodes = AccountCode::pluck('code')->toArray();
                    $accountNames = AccountCode::pluck('name', 'code')->toArray();

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
                                    'count'          => 0,
                                    'suggested'      => $suggestedCode,
                                    'suggested_name' => $suggestedCode ? ($accountNames[$suggestedCode] ?? '') : null,
                                ];
                            }
                            $freeTextGroups[$bk]['count']++;
                        }
                    }

                    $tableRows = '';
                    foreach ($freeTextGroups as $name => $info) {
                        $suggested = $info['suggested'];
                        $rowBg = $suggested ? '#f0fdf4' : '#fef9c3';
                        $status = $suggested
                            ? "✅ → <strong>{$suggested}</strong> — " . e($info['suggested_name'])
                            : '⚠️ Cannot auto-map — will be skipped (can edit manually)';

                        $tableRows .= "
                            <tr style='background:{$rowBg};border-bottom:1px solid #e2e8f0;'>
                                <td style='padding:8px 12px;font-family:monospace;font-size:13px;font-weight:600;color:#be185d;'>" . e($name) . "</td>
                                <td style='padding:8px 12px;font-size:13px;text-align:center;'><strong>{$info['count']}</strong></td>
                                <td style='padding:8px 12px;font-size:13px;'>{$status}</td>
                            </tr>
                        ";
                    }

                    $mappableCount = count(array_filter($freeTextGroups, fn($g) => $g['suggested'] !== null));
                    $skipCount     = count(array_filter($freeTextGroups, fn($g) => $g['suggested'] === null));

                    return [
                        Forms\Components\Placeholder::make('mapping_table')
                            ->label('')
                            ->content(new HtmlString("
                                <div style='margin-bottom:12px;'>
                                    <span style='font-size:13px;color:#374151;'>
                                        Found <strong>" . array_sum(array_column($freeTextGroups, 'count')) . " payment entries</strong> with legacy free-text bank names.
                                        <strong>{$mappableCount}</strong> group(s) will be automatically mapped to their account code.
                                        <strong>{$skipCount}</strong> group(s) can be updated manually row-by-row.
                                    </span>
                                </div>
                                <div style='overflow-x:auto;border:1px solid #e2e8f0;border-radius:10px;'>
                                    <table style='width:100%;border-collapse:collapse;'>
                                        <thead style='background:#f1f5f9;'>
                                            <tr>
                                                <th style='padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;'>Legacy Bank Name</th>
                                                <th style='padding:10px 12px;text-align:center;font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;'>Vouchers</th>
                                                <th style='padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;'>Will Be Assigned To</th>
                                            </tr>
                                        </thead>
                                        <tbody>{$tableRows}</tbody>
                                    </table>
                                </div>
                            ")),
                    ];
                })
                ->action(function () {
                    $validCodes = AccountCode::pluck('code')->toArray();

                    $paid = Voucher::whereIn('type', ['payment', 'bank_encashment'])
                        ->where('status', 'paid')
                        ->get(['id', 'bank', 'multiple_payments']);

                    $fixed = 0;
                    $skipped = 0;

                    foreach ($paid as $v) {
                        $changed = false;
                        $payments = $v->multiple_payments ?? null;

                        if ($payments !== null) {
                            $newPayments = [];
                            foreach ($payments as $p) {
                                $bk = trim($p['bank'] ?? '');
                                if (!empty($bk) && !in_array($bk, $validCodes)) {
                                    $normalized = strtolower($bk);
                                    $corrected  = static::$legacyBankMapping[$normalized] ?? null;
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
                                $normalized = strtolower($bk);
                                $corrected  = static::$legacyBankMapping[$normalized] ?? null;
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
                        ->title("✅ {$fixed} bank name(s) corrected! {$skipped} skipped (edit manually)")
                        ->success()
                        ->send();
                })
                ->visible(fn () => auth()->user()->hasAnyRole(['Super Admin', 'Admin'])),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            BankReconciliationOverviewWidget::class,
        ];
    }
}
