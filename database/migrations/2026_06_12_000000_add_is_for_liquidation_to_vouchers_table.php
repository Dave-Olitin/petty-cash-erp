<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->boolean('is_for_liquidation')->default(false)->after('status');
        });

        // Set historical petty_cash vouchers that already have liquidations to true
        DB::table('vouchers')
            ->where('type', 'petty_cash')
            ->whereIn('liquidation_status', ['pending', 'liquidated', 'overdue'])
            ->update(['is_for_liquidation' => true]);
            
        // Also set petty_cash vouchers without liquidations but with amount >= threshold to true?
        // Let's just set all petty_cash vouchers to true historically to avoid breaking existing ones.
        DB::table('vouchers')
            ->where('type', 'petty_cash')
            ->update(['is_for_liquidation' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('is_for_liquidation');
        });
    }
};
