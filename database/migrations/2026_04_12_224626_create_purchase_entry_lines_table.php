<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_entry_id')->constrained()->cascadeOnDelete();
            $table->string('description')->nullable();
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->foreignId('debit_account_id')->nullable()->constrained('account_codes')->nullOnDelete();
            $table->foreignId('credit_account_id')->nullable()->constrained('account_codes')->nullOnDelete();
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_entry_lines');
    }
};
