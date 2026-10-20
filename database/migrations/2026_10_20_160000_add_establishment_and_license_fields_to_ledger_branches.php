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
        Schema::table('ledger_branches', function (Blueprint $table) {
            if (!Schema::hasColumn('ledger_branches', 'license_number')) {
                $table->string('license_number')->nullable()->after('name');
            }
            if (!Schema::hasColumn('ledger_branches', 'issuing_authority')) {
                $table->string('issuing_authority')->nullable()->after('license_number');
            }
            if (!Schema::hasColumn('ledger_branches', 'mohre_est_card_number')) {
                $table->string('mohre_est_card_number')->nullable()->after('license_expiry');
            }
            if (!Schema::hasColumn('ledger_branches', 'mohre_est_card_expiry')) {
                $table->date('mohre_est_card_expiry')->nullable()->after('mohre_est_card_number');
            }
            if (!Schema::hasColumn('ledger_branches', 'immigration_est_card_number')) {
                $table->string('immigration_est_card_number')->nullable()->after('mohre_est_card_expiry');
            }
            if (!Schema::hasColumn('ledger_branches', 'immigration_est_card_expiry')) {
                $table->date('immigration_est_card_expiry')->nullable()->after('immigration_est_card_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledger_branches', function (Blueprint $table) {
            $columns = [
                'license_number',
                'issuing_authority',
                'mohre_est_card_number',
                'mohre_est_card_expiry',
                'immigration_est_card_number',
                'immigration_est_card_expiry',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('ledger_branches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
