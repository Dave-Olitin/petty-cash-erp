<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. vouchers.status — used in every widget stat query, approval checks, table filters
        Schema::table('vouchers', function (Blueprint $table) {
            if (!$this->hasIndex('vouchers', 'vouchers_status_index')) {
                $table->index('status', 'vouchers_status_index');
            }
            if (!$this->hasIndex('vouchers', 'vouchers_type_index')) {
                $table->index('type', 'vouchers_type_index');
            }
            // Composite for the most common widget query: WHERE type IN (...) AND status = 'paid'
            if (!$this->hasIndex('vouchers', 'vouchers_type_status_index')) {
                $table->index(['type', 'status'], 'vouchers_type_status_index');
            }
        });

        // 2. voucher_items.account_code — used in getOptionLabelUsing lookups in forms
        Schema::table('voucher_items', function (Blueprint $table) {
            if (!$this->hasIndex('voucher_items', 'voucher_items_account_code_index')) {
                $table->index('account_code', 'voucher_items_account_code_index');
            }
        });

        // 3. account_codes.name — used in LIKE search in Select dropdowns
        Schema::table('account_codes', function (Blueprint $table) {
            if (!$this->hasIndex('account_codes', 'account_codes_name_index')) {
                $table->index('name', 'account_codes_name_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndexIfExists('vouchers_status_index');
            $table->dropIndexIfExists('vouchers_type_index');
            $table->dropIndexIfExists('vouchers_type_status_index');
        });

        Schema::table('voucher_items', function (Blueprint $table) {
            $table->dropIndexIfExists('voucher_items_account_code_index');
        });

        Schema::table('account_codes', function (Blueprint $table) {
            $table->dropIndexIfExists('account_codes_name_index');
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->contains($indexName);
    }
};
