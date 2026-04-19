<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds entry_type to purchase_entries so Purchase Returns (Debit Notes to suppliers)
     * can live alongside normal Purchase Bills in the same table.
     *
     * purchase = normal supplier invoice
     * return   = purchase return / debit note (reverses the obligation)
     */
    public function up(): void
    {
        Schema::table('purchase_entries', function (Blueprint $table) {
            $table->string('entry_type')->default('purchase')->after('entry_no')
                  ->comment('purchase | return');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_entries', function (Blueprint $table) {
            $table->dropColumn('entry_type');
        });
    }
};
