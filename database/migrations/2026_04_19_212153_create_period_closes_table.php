<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_closes', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // e.g. "April 2026"
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'closed'])->default('draft');

            // Aggregated snapshot figures
            $table->decimal('total_vouchers_paid', 15, 2)->default(0);
            $table->decimal('total_petty_cash_disbursed', 15, 2)->default(0);
            $table->decimal('total_ap_billed', 15, 2)->default(0);
            $table->decimal('total_ap_paid', 15, 2)->default(0);
            $table->decimal('total_ap_balance', 15, 2)->default(0);
            $table->decimal('total_journal_dr', 15, 2)->default(0);
            $table->decimal('total_journal_cr', 15, 2)->default(0);
            $table->integer('voucher_count')->default(0);
            $table->integer('purchase_entry_count')->default(0);
            $table->integer('journal_entry_count')->default(0);

            $table->text('closing_notes')->nullable();

            // Who and when it was closed
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_closes');
    }
};
