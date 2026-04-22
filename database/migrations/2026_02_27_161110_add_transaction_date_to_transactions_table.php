<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a dedicated `transaction_date` column to separate business date
     * from the system-managed `created_at` timestamp.
     *
     * Previously the form wrote directly to `created_at`, which is a system
     * audit field in Laravel. Using a separate column ensures the audit trail
     * is always accurate while still letting users input a custom date.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Nullable so existing rows without a value are handled gracefully.
            // Placed after 'type' to keep the column logically near the top.
            $table->timestamp('transaction_date')->nullable()->after('type');
        });

        // Back-fill: copy created_at into transaction_date for all existing rows
        // so no existing data is lost or displayed as null.
        DB::statement('UPDATE transactions SET transaction_date = created_at WHERE transaction_date IS NULL');
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('transaction_date');
        });
    }
};
