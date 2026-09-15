<?php

namespace App\Filament\Vouchers\Widgets;

use App\Models\AccountCode;
use App\Models\BankReconciliation;
use App\Models\Voucher;
use Filament\Widgets\Widget;
use Illuminate\Support\HtmlString;

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

    public function getCreateUrl(string $accountCode): string
    {
        return route('filament.vouchers.resources.bank-reconciliations.create', [
            'account_code' => $accountCode,
        ]);
    }
}
