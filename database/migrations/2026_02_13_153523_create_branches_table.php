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
    Schema::create('branches', function (Blueprint $table) {
        $table->id();
        $table->string('name'); // e.g., "Downtown Branch"
        $table->string('location')->nullable();
        $table->decimal('current_balance', 15, 2)->default(0); // Cached balance for speed
        $table->decimal('max_limit', 15, 2)->default(500.00); // The "Float" cap
        $table->boolean('is_active')->default(true); // "Kill Switch" for HQ
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
