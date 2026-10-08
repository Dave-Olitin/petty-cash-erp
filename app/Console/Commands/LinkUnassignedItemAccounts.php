<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ItemMasterLinkerService;

class LinkUnassignedItemAccounts extends Command
{
    protected $signature = 'item-master:link-accounts 
                            {--smart : Include smart pattern matching for common variants (routes, fuel receipts, barcodes)}
                            {--dry-run : Preview matches without modifying the database}
                            {--force : Execute without confirmation prompt}';

    protected $description = 'Auto-link account codes and master item IDs on existing transaction items based on the Item Masterfile.';

    public function handle(ItemMasterLinkerService $linker): int
    {
        $isDry = $this->option('dry-run');
        $smart = (bool) $this->option('smart');
        $force = (bool) $this->option('force');

        $this->info($isDry 
            ? '🔍 [DRY RUN] Previewing account code auto-linking from Item Masterfile...' 
            : '⚡ Auto-linking account codes from Item Masterfile to existing transaction items...'
        );
        $this->line("Mode: " . ($smart ? '<fg=cyan>Exact + Smart Pattern Matching</>' : '<fg=yellow>Exact Match Only</>'));
        $this->newLine();

        $result = $linker->preview(includeSmart: $smart);

        if ($result['total_unassigned'] === 0) {
            $this->info('✅ No unassigned transaction items found. Everything is already linked!');
            return self::SUCCESS;
        }

        $this->line("Found <fg=yellow>{$result['total_unassigned']}</> unassigned transaction items in EXPENSE vouchers.");
        $this->newLine();

        // 1. Exact Matches Table
        if ($result['exact_count'] > 0) {
            $this->line("<fg=green;options=bold>Exact Matches ({$result['exact_count']} items):</>");
            $exactSample = array_map(fn ($r) => [
                $r['item_id'],
                $r['transaction_id'],
                $r['item_name'],
                $r['account_code'],
                $r['account_name'],
            ], array_slice($result['exact_matches'], 0, 20));

            $this->table(['Item #', 'TX #', 'Item Name', 'Account Code', 'Account Name'], $exactSample);

            if ($result['exact_count'] > 20) {
                $this->line("  ... and " . ($result['exact_count'] - 20) . " more exact matches.");
            }
            $this->newLine();
        }

        // 2. Smart Matches Table
        if ($result['smart_count'] > 0) {
            $this->line("<fg=cyan;options=bold>Smart Pattern Matches ({$result['smart_count']} items):</>");
            $smartSample = array_map(fn ($r) => [
                $r['item_id'],
                $r['transaction_id'],
                $r['item_name'],
                $r['master_name'],
                $r['account_code'],
                $r['match_type'],
            ], array_slice($result['smart_matches'], 0, 20));

            $this->table(['Item #', 'TX #', 'Item Text', 'Matched Master', 'Account Code', 'Pattern'], $smartSample);

            if ($result['smart_count'] > 20) {
                $this->line("  ... and " . ($result['smart_count'] - 20) . " more smart pattern matches.");
            }
            $this->newLine();
        }

        // 3. Unmatched Items
        if ($result['unmatched_count'] > 0) {
            $this->line("<fg=red;options=bold>Unmatched Items ({$result['unmatched_count']} items):</>");
            $unmatchedSample = array_map(fn ($r) => [
                $r['item_id'],
                $r['transaction_id'],
                $r['item_name'],
            ], array_slice($result['unmatched'], 0, 15));

            $this->table(['Item #', 'TX #', 'Item Name'], $unmatchedSample);

            if ($result['unmatched_count'] > 15) {
                $this->line("  ... and " . ($result['unmatched_count'] - 15) . " more unmatched items.");
            }
            $this->newLine();
        }

        // Summary
        $this->line('<options=bold>Summary:</>');
        $this->line("  → Total Unassigned Items : {$result['total_unassigned']}");
        $this->line("  → Exact Matches          : <fg=green>{$result['exact_count']}</>");
        if ($smart) {
            $this->line("  → Smart Pattern Matches  : <fg=cyan>{$result['smart_count']}</>");
        }
        $this->line("  → Total To Link          : <fg=green;options=bold>{$result['total_linked']}</> items across <fg=green;options=bold>{$result['affected_transactions']}</> transactions");
        $this->line("  → Remaining Unmatched    : <fg=yellow>{$result['unmatched_count']}</>");
        $this->newLine();

        if ($result['total_linked'] === 0) {
            $this->warn('No matches found to link.');
            return self::SUCCESS;
        }

        if ($isDry) {
            $this->warn('🔒 [DRY RUN] No records were modified. Run without --dry-run to apply changes.');
            return self::SUCCESS;
        }

        if (!$force && !$this->confirm("Proceed to link {$result['total_linked']} items to their Item Master / Account Codes?", true)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        // Execute linking
        $executionResult = $linker->link(includeSmart: $smart);

        $this->newLine();
        $this->info("🎉 Done! Successfully linked {$executionResult['total_linked']} items across {$executionResult['affected_transactions']} transactions to the Chart of Accounts.");

        return self::SUCCESS;
    }
}
