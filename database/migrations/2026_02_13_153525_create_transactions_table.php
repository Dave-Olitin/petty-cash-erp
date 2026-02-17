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
    Schema::create('transactions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('branch_id')->constrained()->cascadeOnDelete(); // Performance link
        $table->foreignId('user_id')->constrained(); // Audit trail
        $table->enum('type', ['EXPENSE', 'REPLENISHMENT']); 
        $table->decimal('amount', 15, 2);
        $table->string('payee')->nullable(); // Who got the money?
        $table->text('description');
        $table->string('receipt_path')->nullable(); // File storage
        $table->boolean('is_approved')->default(true); // For future approval flows
        $table->timestamps();
        $table->softDeletes(); // CRITICAL: Never delete money records, only "soft delete"
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
