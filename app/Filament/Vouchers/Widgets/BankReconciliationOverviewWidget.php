<?php

namespace App\Filament\Vouchers\Widgets;

use App\Models\AccountCode;
use App\Models\BankReconciliation;
use App\Models\Voucher;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class BankReconciliationOverviewWidget extends Widget
{
    protected static string $view = 'filament.widgets.bank-reconciliation-overview';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    /**
     * Compute a summary of all distinct bank accounts used in paid payment
     * vouchers, grouped by account code (or free-text name for legacy ones).
     */
    public function getBankSummaryData(): array
    {
        $paid = Voucher::whereIn('type', ['payment', 'bank_encashment'])
            ->where('status', 'paid')
            ->get(['id', 'bank', 'multiple_payments', 'amount', 'cheque_no', 'updated_at', 'date']);

        $accountNames = AccountCode::pluck('name', 'code')->toArray();

        // Existing completed reconciliations (account_code → [period coverage])
        $reconByAccount = BankReconciliation::where('status', 'completed')
            ->get(['account_code', 'period_from', 'period_to', 'id'])
            ->groupBy('account_code');

        $summary = [];

        foreach ($paid as $v) {
            $payments = $v->multiple_payments ?? [['bank' => $v->bank, 'amount' => $v->amount]];
            foreach ($payments as $p) {
                $bk = trim($p['bank'] ?? '');
                if (empty($bk)) continue;

                if (!isset($summary[$bk])) {
                    $summary[$bk] = [
                        'bank'           => $bk,
                        'account_name'   => $accountNames[$bk] ?? null,
                        'is_valid_code'  => isset($accountNames[$bk]),
                        'count'          => 0,
                        'total'          => 0.0,
                        'recon_sessions' => $reconByAccount[$bk] ?? collect(),
                        'latest_date'    => null,
                    ];
                }

                $summary[$bk]['count']++;
                $summary[$bk]['total'] += (float)($p['amount'] ?? $v->amount);

                $vDate = $v->date ? $v->date->format('Y-m-d') : null;
                if ($vDate && ($summary[$bk]['latest_date'] === null || $vDate > $summary[$bk]['latest_date'])) {
                    $summary[$bk]['latest_date'] = $vDate;
                }
            }
        }

        // Sort: valid codes first, then by total descending
        uasort($summary, function ($a, $b) {
            if ($a['is_valid_code'] !== $b['is_valid_code']) {
                return $b['is_valid_code'] - $a['is_valid_code'];
            }
            return $b['total'] <=> $a['total'];
        });

        return array_values($summary);
    }

    /**
     * Get all active account codes that are bank/cash accounts (code starts with 1)
     * as an associative array [code => "code — name"] for the dropdown.
     */
    public function getValidAccountCodes(): array
    {
        return AccountCode::where('is_active', true)
            ->where('code', 'like', '1%')
            ->orderBy('code')
            ->get(['code', 'name'])
            ->mapWithKeys(fn ($ac) => [$ac->code => $ac->code . ' — ' . $ac->name])
            ->toArray();
    }

    /**
     * Livewire action: assign all vouchers with the given legacy bank name
     * to the selected account code.
     */
    public function assignLegacyBank(string $legacyBank, string $newCode): void
    {
        if (empty($newCode) || empty($legacyBank)) {
            Notification::make()
                ->title('Please select an account code.')
                ->warning()
                ->send();
            return;
        }

        // Validate the code exists
        $exists = AccountCode::where('code', $newCode)->exists();
        if (!$exists) {
            Notification::make()
                ->title('Invalid account code selected.')
                ->danger()
                ->send();
            return;
        }

        $paid = Voucher::whereIn('type', ['payment', 'bank_encashment'])
            ->where('status', 'paid')
            ->get(['id', 'bank', 'multiple_payments']);

        $fixed = 0;

        foreach ($paid as $v) {
            $payments = $v->multiple_payments ?? null;

            if ($payments !== null) {
                $changed = false;
                $newPayments = [];
                foreach ($payments as $p) {
                    $bk = trim($p['bank'] ?? '');
                    if ($bk === $legacyBank) {
                        $p['bank'] = $newCode;
                        $changed = true;
                        $fixed++;
                    }
                    $newPayments[] = $p;
                }
                if ($changed) {
                    DB::table('vouchers')
                        ->where('id', $v->id)
                        ->update([
                            'multiple_payments' => json_encode($newPayments),
                            'bank'              => $newPayments[0]['bank'] ?? $newCode,
                        ]);
                }
            } else {
                if (trim($v->bank ?? '') === $legacyBank) {
                    DB::table('vouchers')
                        ->where('id', $v->id)
                        ->update(['bank' => $newCode]);
                    $fixed++;
                }
            }
        }

        Notification::make()
            ->title("✅ {$fixed} voucher(s) reassigned to {$newCode}")
            ->success()
            ->send();
    }

    public function getCreateUrl(string $accountCode): string
    {
        return route('filament.vouchers.resources.bank-reconciliations.create', [
            'account_code' => $accountCode,
        ]);
    }
}
