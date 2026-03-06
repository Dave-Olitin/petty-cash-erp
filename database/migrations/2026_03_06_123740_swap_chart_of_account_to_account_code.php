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
        // 0. Ensure 'account_codes' table exists (production did not have it)
        if (!Schema::hasTable('account_codes')) {
            Schema::create('account_codes', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->timestamps();
            });
        }

        // 1. Swap chart_of_account_id → account_code_id on categories
        if (Schema::hasColumn('categories', 'chart_of_account_id')) {
            Schema::table('categories', function (Blueprint $table) {
                // Wrap in try-catch in case the foreign key name differs but column exists
                try {
                    $table->dropForeign(['chart_of_account_id']);
                } catch (\Exception $e) {}
                $table->dropColumn('chart_of_account_id');
            });
        }

        if (!Schema::hasColumn('categories', 'account_code_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->foreignId('account_code_id')
                    ->nullable()
                    ->constrained('account_codes')
                    ->nullOnDelete()
                    ->after('type');
            });
        }

        // 2. Swap chart_of_account_id → account_code_id on transaction_items
        if (Schema::hasColumn('transaction_items', 'chart_of_account_id')) {
            Schema::table('transaction_items', function (Blueprint $table) {
                try {
                    $table->dropForeign(['chart_of_account_id']);
                } catch (\Exception $e) {}
                $table->dropColumn('chart_of_account_id');
            });
        }

        if (!Schema::hasColumn('transaction_items', 'account_code_id')) {
            Schema::table('transaction_items', function (Blueprint $table) {
                $table->foreignId('account_code_id')
                    ->nullable()
                    ->constrained('account_codes')
                    ->nullOnDelete()
                    ->after('category_id');
            });
        }

        // 3. Drop the redundant chart_of_accounts table we created today
        Schema::dropIfExists('chart_of_accounts');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore chart_of_accounts table
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code')->unique();
            $table->string('account_name');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['account_code_id']);
            $table->dropColumn('account_code_id');
            $table->foreignId('chart_of_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()->after('type');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropForeign(['account_code_id']);
            $table->dropColumn('account_code_id');
            $table->foreignId('chart_of_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()->after('category_id');
        });
    }
};
