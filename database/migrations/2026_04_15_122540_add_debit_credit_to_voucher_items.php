<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_items', function (Blueprint $table) {
            $table->decimal('debit', 10, 2)->default(0)->after('amount');
            $table->decimal('credit', 10, 2)->default(0)->after('debit');
        });

        // Back-fill existing rows from entry_type + amount
        DB::statement("
            UPDATE voucher_items
            SET debit  = CASE WHEN entry_type = 'debit'  THEN amount ELSE 0 END,
                credit = CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END
        ");
    }

    public function down(): void
    {
        Schema::table('voucher_items', function (Blueprint $table) {
            $table->dropColumn(['debit', 'credit']);
        });
    }
};
