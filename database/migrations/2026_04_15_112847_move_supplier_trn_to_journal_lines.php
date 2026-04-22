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
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['supplier_name', 'trn']);
        });

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->string('supplier_name')->nullable()->after('cost_center');
            $table->string('trn')->nullable()->after('supplier_name');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropColumn(['supplier_name', 'trn']);
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('supplier_name')->nullable()->after('po_number');
            $table->string('trn')->nullable()->after('supplier_name');
        });
    }
};
