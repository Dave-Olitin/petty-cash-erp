<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('account_code');                          // AccountCode.code e.g. '1010-02'
            $table->string('account_name');                          // Cached for display
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('opening_balance', 15, 2)->default(0);  // Balance at start of period
            $table->decimal('closing_balance_per_bank', 15, 2)->default(0); // Per bank statement
            $table->enum('status', ['draft', 'completed'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('account_code');
            $table->index('status');
            $table->index(['period_from', 'period_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
    }
};
