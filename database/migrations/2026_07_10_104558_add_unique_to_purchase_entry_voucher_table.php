<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds amount_applied and a composite unique index to the pivot table.
     */
    public function up(): void
    {
        // 1. Remove any duplicate rows that already exist, keeping the lowest id.
        DB::statement('
            DELETE pev1 FROM purchase_entry_voucher pev1
            INNER JOIN purchase_entry_voucher pev2
                ON pev1.voucher_id = pev2.voucher_id
               AND pev1.purchase_entry_id = pev2.purchase_entry_id
               AND pev1.id > pev2.id
        ');

        // 2. Add the amount_applied column and unique index.
        Schema::table('purchase_entry_voucher', function (Blueprint $table) {
            $table->decimal('amount_applied', 15, 2)->default(0.00)->after('purchase_entry_id');
            
            $table->unique(
                ['voucher_id', 'purchase_entry_id'],
                'uq_pev_voucher_purchase_entry'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_entry_voucher', function (Blueprint $table) {
            $table->dropUnique('uq_pev_voucher_purchase_entry');
            $table->dropColumn('amount_applied');
        });
    }
};
