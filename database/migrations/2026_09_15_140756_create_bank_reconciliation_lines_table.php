<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')
                  ->constrained('bank_reconciliations')
                  ->cascadeOnDelete();

            // Bank statement fields (manually entered)
            $table->date('line_date');
            $table->string('description');
            $table->string('reference')->nullable();                   // Cheque # / bank ref / TT #
            $table->decimal('debit', 15, 2)->default(0);               // Money OUT of bank account
            $table->decimal('credit', 15, 2)->default(0);              // Money IN to bank account

            // Matching fields
            $table->enum('match_status', ['unmatched', 'matched', 'manual'])->default('unmatched');
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->unsignedInteger('matched_payment_index')->nullable(); // Index in multiple_payments JSON

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('bank_reconciliation_id');
            $table->index('match_status');
            $table->index('line_date');
            $table->index('voucher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_lines');
    }
};
