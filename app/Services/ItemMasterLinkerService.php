<?php

namespace App\Services;

use App\Models\ItemMaster;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;

class ItemMasterLinkerService
{
    /**
     * Preview matching without writing to the database.
     */
    public function preview(bool $includeSmart = true): array
    {
        return $this->process(dryRun: true, includeSmart: $includeSmart);
    }

    /**
     * Perform the auto-link and update the database.
     */
    public function link(bool $includeSmart = true): array
    {
        return $this->process(dryRun: false, includeSmart: $includeSmart);
    }

    /**
     * Core processing logic.
     */
    protected function process(bool $dryRun, bool $includeSmart): array
    {
        $masters = ItemMaster::with('accountCode')
            ->where('is_active', true)
            ->get();

        $masterMap = [];
        foreach ($masters as $m) {
            $masterMap[strtolower(trim($m->name))] = $m;
        }

        // Common master fallbacks for smart pattern matching
        $fallbackMasters = [
            'taxi'          => $masterMap['taxi fare'] ?? null,
            'petrol'        => $masterMap['petrol'] ?? null,
            'accom_gas'     => $masterMap['accomodation gas'] ?? $masterMap['accommodation gas'] ?? null,
            'broom_dustpan' => $masterMap['broom and dust pan'] ?? null,
            'broom'         => $masterMap['broom'] ?? null,
            'dustpan'       => $masterMap['dustpan'] ?? null,
            'cash_advance'  => $masterMap['cash advance'] ?? null,
            'stationery'    => $masterMap['stationery'] ?? $masterMap['stationery items'] ?? null,
            'sales_quota'   => $masterMap['sales quota'] ?? null,
            'jergens'       => $masterMap['jergens body lotion 400ml mix'] ?? $masterMap['jergens body lotion'] ?? null,
            'correction'    => $masterMap['correction pen'] ?? null,
            'notebook'      => $masterMap['exercise notebook/decking book'] ?? null,
        ];

        // Fetch unassigned items (belonging to EXPENSE transactions)
        $items = TransactionItem::with(['transaction', 'accountCode'])
            ->whereNull('account_code_id')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->whereHas('transaction', fn ($q) => $q->where('type', 'EXPENSE'))
            ->get();

        $exactMatches = [];
        $smartMatches = [];
        $unmatched    = [];

        $toUpdate = [];

        foreach ($items as $item) {
            $rawName = trim($item->name);
            $lower   = strtolower($rawName);

            // 1. Exact match (case-insensitive & trimmed)
            if (isset($masterMap[$lower])) {
                $master = $masterMap[$lower];
                $matchData = [
                    'item_id'         => $item->id,
                    'transaction_id'  => $item->transaction_id,
                    'item_name'       => $rawName,
                    'master_id'       => $master->id,
                    'master_name'     => $master->name,
                    'account_code_id' => $master->account_code_id,
                    'account_code'    => $master->accountCode?->code,
                    'account_name'    => $master->accountCode?->name,
                    'match_type'      => 'Exact',
                ];
                $exactMatches[] = $matchData;
                $toUpdate[] = $matchData;
                continue;
            }

            // 2. Smart match
            if ($includeSmart) {
                $matchedMaster = null;
                $reason = '';

                if ((str_contains($lower, 'accomodation') || str_contains($lower, 'accommodation')) && str_contains($lower, 'gas')) {
                    $matchedMaster = $fallbackMasters['accom_gas'];
                    $reason = 'Accommodation Gas';
                } elseif (str_contains($lower, 'taxi') || str_contains($lower, 'cab')) {
                    $matchedMaster = $fallbackMasters['taxi'];
                    $reason = 'Taxi Fare';
                } elseif (preg_match('/\b(petrol|gasoline|ulg\s*-\s*95|ulg-95|driver gas|car gas|for gas|gas adnoc|gas top up|gas driver)\b/i', $lower)) {
                    $matchedMaster = $fallbackMasters['petrol'];
                    $reason = 'Fuel / Petrol';
                } elseif (str_contains($lower, 'broom') && str_contains($lower, 'dust pan')) {
                    $matchedMaster = $fallbackMasters['broom_dustpan'] ?: $fallbackMasters['broom'];
                    $reason = 'Broom & Dust Pan';
                } elseif (str_contains($lower, 'broom')) {
                    $matchedMaster = $fallbackMasters['broom'];
                    $reason = 'Broom';
                } elseif (str_contains($lower, 'dustpan') || str_contains($lower, 'dust pan')) {
                    $matchedMaster = $fallbackMasters['dustpan'];
                    $reason = 'Dust Pan';
                } elseif (str_contains($lower, 'jergen') && str_contains($lower, 'lotion')) {
                    $matchedMaster = $fallbackMasters['jergens'];
                    $reason = 'Jergens Lotion';
                } elseif (str_contains($lower, 'cash advance')) {
                    $matchedMaster = $fallbackMasters['cash_advance'];
                    $reason = 'Cash Advance';
                } elseif (str_contains($lower, 'sales quota')) {
                    $matchedMaster = $fallbackMasters['sales_quota'];
                    $reason = 'Sales Quota';
                } elseif (str_contains($lower, 'correction')) {
                    $matchedMaster = $fallbackMasters['correction'];
                    $reason = 'Correction Pen';
                } elseif (str_contains($lower, 'stationery')) {
                    $matchedMaster = $fallbackMasters['stationery'];
                    $reason = 'Stationery';
                } elseif (str_contains($lower, 'exercise note')) {
                    $matchedMaster = $fallbackMasters['notebook'];
                    $reason = 'Exercise Notebook';
                }

                if ($matchedMaster && $matchedMaster->account_code_id) {
                    $matchData = [
                        'item_id'         => $item->id,
                        'transaction_id'  => $item->transaction_id,
                        'item_name'       => $rawName,
                        'master_id'       => $matchedMaster->id,
                        'master_name'     => $matchedMaster->name,
                        'account_code_id' => $matchedMaster->account_code_id,
                        'account_code'    => $matchedMaster->accountCode?->code,
                        'account_name'    => $matchedMaster->accountCode?->name,
                        'match_type'      => "Smart ({$reason})",
                    ];
                    $smartMatches[] = $matchData;
                    $toUpdate[] = $matchData;
                    continue;
                }
            }

            // Unmatched
            $unmatched[] = [
                'item_id'        => $item->id,
                'transaction_id' => $item->transaction_id,
                'item_name'      => $rawName,
            ];
        }

        $affectedTransactions = collect($toUpdate)->pluck('transaction_id')->unique()->count();

        // Perform updates if not dry run
        if (!$dryRun && !empty($toUpdate)) {
            DB::transaction(function () use ($toUpdate) {
                foreach ($toUpdate as $up) {
                    TransactionItem::where('id', $up['item_id'])->update([
                        'item_master_id'  => $up['master_id'],
                        'account_code_id' => $up['account_code_id'],
                    ]);
                }
            });
        }

        return [
            'dry_run'               => $dryRun,
            'include_smart'         => $includeSmart,
            'total_unassigned'      => $items->count(),
            'total_linked'          => count($toUpdate),
            'exact_matches'         => $exactMatches,
            'exact_count'           => count($exactMatches),
            'smart_matches'         => $smartMatches,
            'smart_count'           => count($smartMatches),
            'unmatched'             => $unmatched,
            'unmatched_count'       => count($unmatched),
            'affected_transactions' => $affectedTransactions,
        ];
    }

    /**
     * Auto-create ItemMaster entries from transaction items that already have account codes.
     */
    public function autoCreateMastersFromTransactions(): array
    {
        $rows = DB::select("
            SELECT
                ti.name,
                ti.account_code_id,
                COUNT(*)  AS usage_count
            FROM transaction_items ti
            WHERE ti.name IS NOT NULL
              AND TRIM(ti.name) <> ''
              AND ti.account_code_id IS NOT NULL
            GROUP BY ti.name, ti.account_code_id
            ORDER BY ti.name ASC, usage_count DESC
        ");

        $bestPerName = [];
        foreach ($rows as $row) {
            $key = strtolower(trim($row->name));
            if (!isset($bestPerName[$key])) {
                $bestPerName[$key] = $row;
            }
        }

        $existing = ItemMaster::pluck('name')
            ->map(fn ($n) => strtolower(trim($n)))
            ->flip();

        $created = 0;
        foreach ($bestPerName as $key => $row) {
            $name = trim($row->name);
            if (isset($existing[$key]) || strlen($name) < 2 || is_numeric($name)) {
                continue;
            }

            ItemMaster::firstOrCreate(
                ['name' => $name],
                [
                    'account_code_id' => $row->account_code_id,
                    'is_active'       => true,
                ]
            );
            $existing[$key] = true;
            $created++;
        }

        return [
            'created'          => $created,
            'total_candidates' => count($bestPerName),
        ];
    }
}
