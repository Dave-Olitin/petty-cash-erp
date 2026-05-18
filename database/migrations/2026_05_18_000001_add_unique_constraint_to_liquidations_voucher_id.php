<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Before adding the unique constraint, we clean up any existing duplicate
     * liquidation records (keeping the most recently updated one per voucher)
     * so the migration does not fail on production data that already has dupes.
     */
    public function up(): void
    {
        // ── Step 1: Remove duplicates — keep the latest record per voucher_id ──
        // We identify every voucher_id that has more than one liquidation row,
        // then delete all but the row with the highest `updated_at` (most recent).
        $duplicateVoucherIds = DB::table('liquidations')
            ->select('voucher_id')
            ->whereNull('deleted_at')           // only active (non-soft-deleted) rows
            ->groupBy('voucher_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('voucher_id');

        foreach ($duplicateVoucherIds as $voucherId) {
            // Find the ID of the "winner" — the most recently updated record
            $keepId = DB::table('liquidations')
                ->where('voucher_id', $voucherId)
                ->whereNull('deleted_at')
                ->orderByDesc('updated_at')
                ->value('id');

            // Soft-delete (or hard-delete if you prefer) all older duplicates
            DB::table('liquidations')
                ->where('voucher_id', $voucherId)
                ->where('id', '!=', $keepId)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
        }

        // ── Step 2: Add the unique index (partial — active rows only) ─────────
        // MySQL does not natively support partial unique indexes, so we use a
        // standard unique on voucher_id. Soft-deleted rows already have a
        // deleted_at value and are excluded from application queries; the
        // constraint applies to all rows (including soft-deleted) at DB level,
        // which is the safest default.
        //
        // If you need to allow re-creating a liquidation for a previously
        // soft-deleted voucher, swap this for a DB::statement raw UNIQUE
        // that filters on deleted_at IS NULL (MySQL 8+ supports functional indexes).
        Schema::table('liquidations', function (Blueprint $table) {
            $table->unique('voucher_id', 'liquidations_voucher_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('liquidations', function (Blueprint $table) {
            $table->dropUnique('liquidations_voucher_id_unique');
        });
    }
};
