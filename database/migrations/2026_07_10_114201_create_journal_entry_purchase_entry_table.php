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
        Schema::create('journal_entry_purchase_entry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_entry_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_applied', 15, 2)->default(0.00);
            $table->timestamps();
            
            $table->unique(
                ['journal_entry_id', 'purchase_entry_id'],
                'uq_je_pe_journal_entry_purchase_entry'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entry_purchase_entry');
    }
};
