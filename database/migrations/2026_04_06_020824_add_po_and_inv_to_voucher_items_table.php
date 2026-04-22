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
        Schema::table('voucher_items', function (Blueprint $table) {
            $table->string('po_number')->nullable();
            $table->string('invoice_number')->nullable();
        });

        // Create the new permission
        \Spatie\Permission\Models\Permission::findOrCreate('voucher.edit_own_undisbursed', 'web');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voucher_items', function (Blueprint $table) {
            $table->dropColumn(['po_number', 'invoice_number']);
        });
    }
};
