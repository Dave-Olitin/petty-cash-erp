<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_no')->unique();
            $table->date('date');
            $table->string('supplier_name')->nullable();
            $table->string('supplier_trn')->nullable();
            $table->string('bill_no')->nullable();
            $table->string('invoice_no')->nullable();
            $table->string('currency')->default('AED');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('total_vat', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_entries');
    }
};
