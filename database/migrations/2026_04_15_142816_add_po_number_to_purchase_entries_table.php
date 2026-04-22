<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_entries', function (Blueprint $table) {
            $table->string('po_number')->nullable()->after('supplier_trn');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_entries', function (Blueprint $table) {
            $table->dropColumn('po_number');
        });
    }
};
