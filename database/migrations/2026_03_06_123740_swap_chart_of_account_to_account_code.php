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
        // 1. Swap chart_of_account_id → account_code_id on categories
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['chart_of_account_id']);
            $table->dropColumn('chart_of_account_id');
            $table->foreignId('account_code_id')
                ->nullable()
                ->constrained('account_codes')
                ->nullOnDelete()
                ->after('type');
        });

        // 2. Swap chart_of_account_id → account_code_id on transaction_items
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropForeign(['chart_of_account_id']);
            $table->dropColumn('chart_of_account_id');
            $table->foreignId('account_code_id')
                ->nullable()
                ->constrained('account_codes')
                ->nullOnDelete()
                ->after('category_id');
        });

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
