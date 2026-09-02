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
        \Illuminate\Support\Facades\DB::table('purchase_entries')
            ->where('payment_status', 'paid')
            ->where('balance_due', '>', 0)
            ->update(['payment_status' => 'unpaid']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
