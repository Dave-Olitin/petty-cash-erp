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
        Schema::create('item_masters', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('account_code_id')
                ->constrained('account_codes')
                ->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->foreignId('item_master_id')
                ->nullable()
                ->after('account_code_id')
                ->constrained('item_masters')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropForeign(['item_master_id']);
            $table->dropColumn('item_master_id');
        });

        Schema::dropIfExists('item_masters');
    }
};
