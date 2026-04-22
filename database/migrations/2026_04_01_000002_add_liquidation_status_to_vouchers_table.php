<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->enum('liquidation_status', [
                'not_required',
                'pending',
                'liquidated',
                'overdue',
            ])->default('not_required')->after('status');

            // Threshold: only PCVs above this amount require liquidation
            // Default: 0 — meaning ALL paid PCVs require liquidation
            // (Configurable via config/liquidation.php)
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('liquidation_status');
        });
    }
};
