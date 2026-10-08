<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\ItemMaster;

class AutoCreateItemMasterfile extends Command
{
    protected $signature   = 'item-master:auto-create {--dry-run : Preview only, do not write to database} {--force : Bypass confirmation}';
    protected $description = 'Auto-generate Item Masterfile from existing transaction_items that already have an account_code_id linked.';

    public function handle(): int
    {
        $isDry = $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info($isDry ? '[DRY RUN] Previewing auto-created Item Masterfile...' : 'Auto-creating Item Masterfile from existing transaction data...');
        $this->newLine();

        /*
         * Strategy:
         *  1. From transaction_items, pick rows where account_code_id IS NOT NULL
         *  2. For each unique item name, pick the account_code_id that appears MOST often
         *     (majority-wins: if "Fuel" has been assigned account 5100-01 60× and 6200-00 2×, use 5100-01)
         *  3. Skip names that already exist in item_masters
         *  4. Skip blank/numeric-only names that are meaningless
         */
        $rows = DB::select("
            SELECT
                ti.name,
                ti.account_code_id,
                ac.code   AS account_code,
                ac.name   AS account_name,
                COUNT(*)  AS usage_count
            FROM transaction_items ti
            JOIN account_codes ac ON ac.id = ti.account_code_id
            WHERE ti.name IS NOT NULL
              AND TRIM(ti.name) <> ''
              AND ti.account_code_id IS NOT NULL
            GROUP BY ti.name, ti.account_code_id, ac.code, ac.name
            ORDER BY ti.name ASC, usage_count DESC
        ");

        // Group by item name, keep only the top account_code_id per name
        $bestPerName = [];
        foreach ($rows as $row) {
            $key = strtolower(trim($row->name));
            if (!isset($bestPerName[$key])) {
                $bestPerName[$key] = $row; // First row = highest usage_count (ORDER BY usage_count DESC)
            }
        }

        // Fetch already-existing master names so we can skip them
        $existing = ItemMaster::pluck('name')
            ->map(fn ($n) => strtolower(trim($n)))
            ->flip();

        $toCreate  = [];
        $skipped   = [];

        foreach ($bestPerName as $key => $row) {
            $name = trim($row->name);

            // Skip if already in masterfile
            if (isset($existing[$key])) {
                $skipped[] = $name;
                continue;
            }

            // Skip obviously meaningless names (numbers only, single chars, etc.)
            if (strlen($name) < 2 || is_numeric($name)) {
                $skipped[] = $name . ' (too short/numeric)';
                continue;
            }

            $toCreate[] = [
                'name'            => $name,
                'account_code_id' => $row->account_code_id,
                'account_code'    => $row->account_code,
                'account_name'    => $row->account_name,
                'usage_count'     => $row->usage_count,
            ];
        }

        if (empty($toCreate)) {
            $this->warn('No new items found to create. All unique item names are either already in the masterfile or have no account code assigned.');
            return self::SUCCESS;
        }

        // Display table of what will be created
        $this->table(
            ['Item Name', 'Account Code', 'Account Name', 'Usage Count'],
            array_map(fn ($r) => [$r['name'], $r['account_code'], $r['account_name'], $r['usage_count']], $toCreate)
        );

        $this->newLine();
        $this->line('Summary:');
        $this->line('  → Will create : <fg=green>' . count($toCreate) . '</> master items');
        $this->line('  → Will skip   : <fg=yellow>' . count($skipped) . '</> (already exist or meaningless)');

        if ($isDry) {
            $this->newLine();
            $this->warn('[DRY RUN] No records written. Run without --dry-run to apply.');
            return self::SUCCESS;
        }

        $this->newLine();

        if (!$force && !$this->confirm('Proceed to create ' . count($toCreate) . ' master items?', true)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $created = 0;
        $bar = $this->output->createProgressBar(count($toCreate));
        $bar->start();

        foreach ($toCreate as $entry) {
            ItemMaster::firstOrCreate(
                ['name' => $entry['name']],
                [
                    'account_code_id' => $entry['account_code_id'],
                    'is_active'       => true,
                ]
            );
            $created++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Done! Created {$created} master items successfully.");

        return self::SUCCESS;
    }
}
