<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_entry_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_entry_lines', 'debit')) {
                $table->decimal('debit', 12, 2)->default(0)->after('credit_account_id');
            }
            if (!Schema::hasColumn('purchase_entry_lines', 'credit')) {
                $table->decimal('credit', 12, 2)->default(0)->after('debit');
            }
        });

        Schema::table('purchase_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_entries', 'total_debit')) {
                $table->decimal('total_debit', 12, 2)->default(0)->after('grand_total');
            }
            if (!Schema::hasColumn('purchase_entries', 'total_credit')) {
                $table->decimal('total_credit', 12, 2)->default(0)->after('total_debit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safety migration, down method intentionally left empty to avoid data loss
    }
};
