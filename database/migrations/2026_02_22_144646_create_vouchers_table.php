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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['petty_cash', 'payment'])->default('payment');
            $table->string('voucher_number')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('payee');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'pending_checker', 'pending_approver', 'approved', 'rejected', 'paid'])->default('draft');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
