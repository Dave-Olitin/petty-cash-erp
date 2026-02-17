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
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'supplier')) {
                $table->string('supplier')->nullable()->after('payee');
            }
            if (!Schema::hasColumn('transactions', 'trn')) {
                $table->string('trn')->nullable()->after('supplier');
            }
            if (!Schema::hasColumn('transactions', 'reference_number')) {
                $table->string('reference_number')->nullable()->after('trn');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['supplier', 'trn', 'reference_number']);
        });
    }
};
