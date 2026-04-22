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
        Schema::create('denominations', function (Blueprint $table) {
            $table->id();
            $table->morphs('denominatable');
            $table->integer('bill_1000')->default(0);
            $table->integer('bill_500')->default(0);
            $table->integer('bill_200')->default(0);
            $table->integer('bill_100')->default(0);
            $table->integer('bill_50')->default(0);
            $table->integer('bill_20')->default(0);
            $table->integer('bill_10')->default(0);
            $table->integer('bill_5')->default(0);
            $table->integer('coin_1')->default(0);
            $table->integer('coin_0_50')->default(0);
            $table->integer('coin_0_25')->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('denominations');
    }
};
