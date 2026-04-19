<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds explicit debit/credit monetary amounts to each purchase entry line
     * so that the module can support proper double-entry bookkeeping.
     * Also adds running totals on the parent purchase_entries header.
     */
    public function up(): void
    {
        Schema::table('purchase_entry_lines', function (Blueprint $table) {
            // Per-line monetary debit/credit amounts (distinct from the account FK columns)
            $table->decimal('debit', 12, 2)->default(0)->after('credit_account_id');
            $table->decimal('credit', 12, 2)->default(0)->after('debit');
        });

        Schema::table('purchase_entries', function (Blueprint $table) {
            $table->decimal('total_debit', 12, 2)->default(0)->after('grand_total');
            $table->decimal('total_credit', 12, 2)->default(0)->after('total_debit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_entry_lines', function (Blueprint $table) {
            $table->dropColumn(['debit', 'credit']);
        });

        Schema::table('purchase_entries', function (Blueprint $table) {
            $table->dropColumn(['total_debit', 'total_credit']);
        });
    }
};
