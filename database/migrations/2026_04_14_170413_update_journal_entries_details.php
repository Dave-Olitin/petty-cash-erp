<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['entity', 'description']);
            $table->string('po_number')->nullable()->after('date');
            $table->string('supplier_name')->nullable()->after('po_number');
            $table->string('trn')->nullable()->after('supplier_name');
            $table->string('invoice_no')->nullable()->after('trn');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('entity')->nullable();
            $table->text('description')->nullable();
            $table->dropColumn(['po_number', 'supplier_name', 'trn', 'invoice_no']);
        });
    }
};
