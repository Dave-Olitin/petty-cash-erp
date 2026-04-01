<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('liquidated_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount_spent', 12, 2)->default(0);
            $table->decimal('amount_returned', 12, 2)->default(0);
            $table->decimal('amount_short', 12, 2)->default(0)->comment('How much is still unaccounted for');
            $table->enum('status', ['pending', 'complete', 'short', 'excess'])->default('pending');
            $table->text('remarks')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('liquidated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['voucher_id', 'status']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidations');
    }
};
