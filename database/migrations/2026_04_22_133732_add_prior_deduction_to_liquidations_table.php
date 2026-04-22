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
        Schema::table('liquidations', function (Blueprint $table) {
            // Stores the cash-advance deduction that was applied at disbursement.
            // This pre-fills the settlement so the custodian knows receipts only
            // need to cover (voucher_amount - prior_deduction).
            $table->decimal('prior_deduction', 12, 2)->default(0)->after('amount_short');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('liquidations', function (Blueprint $table) {
            $table->dropColumn('prior_deduction');
        });
    }
};
