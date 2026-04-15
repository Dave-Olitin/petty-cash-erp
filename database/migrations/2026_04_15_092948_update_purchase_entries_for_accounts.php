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
        Schema::table('purchase_entry_lines', function (Blueprint $table) {
            $table->dropColumn(['qty', 'unit_price', 'discount_percentage']);
            $table->decimal('amount', 12, 2)->default(0)->after('description');
            $table->string('cost_center')->nullable()->after('debit_account_id');
        });

        Schema::table('purchase_entries', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
            $table->dropColumn('voucher_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_entry_lines', function (Blueprint $table) {
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->dropColumn(['amount', 'cost_center']);
        });

        Schema::table('purchase_entries', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
        });
    }
};
