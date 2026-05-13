<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add account type and normal balance classification to the chart of accounts.
     *
     * 'type'           — One of: asset | liability | equity | revenue | expense
     * 'normal_balance' — The side that increases this account: debit | credit
     *                    Derived from type but stored explicitly for query performance
     *                    and clarity in reports.
     */
    public function up(): void
    {
        Schema::table('account_codes', function (Blueprint $table) {
            $table->enum('type', [
                'asset',
                'liability',
                'equity',
                'revenue',
                'expense',
            ])->default('expense')->after('name');

            $table->enum('normal_balance', [
                'debit',
                'credit',
            ])->default('debit')->after('type');

            $table->text('description')->nullable()->after('normal_balance');
        });
    }

    public function down(): void
    {
        Schema::table('account_codes', function (Blueprint $table) {
            $table->dropColumn(['type', 'normal_balance', 'description']);
        });
    }
};
