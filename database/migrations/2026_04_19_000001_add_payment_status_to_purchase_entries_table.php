<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds payment tracking columns to purchase_entries for AP aging.
     *
     * payment_status: unpaid | partial | paid
     * amount_paid:    cumulative payments applied to this bill
     * balance_due:    grand_total - amount_paid (maintained by model)
     */
    public function up(): void
    {
        Schema::table('purchase_entries', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid')
                  ->comment('unpaid | partial | paid');
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_entries', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'amount_paid', 'balance_due']);
        });
    }
};
