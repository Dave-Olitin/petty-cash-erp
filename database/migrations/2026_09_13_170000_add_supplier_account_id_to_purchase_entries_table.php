<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_entries', function (Blueprint $table) {
            $table->foreignId('supplier_account_id')
                  ->nullable()
                  ->after('tax_registration_id')
                  ->constrained('account_codes')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_account_id');
        });
    }
};
