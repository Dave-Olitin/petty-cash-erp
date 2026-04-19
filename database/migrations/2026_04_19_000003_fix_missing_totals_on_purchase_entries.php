<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The debit/credit migration (2026_04_15_162838) had already been marked
     * as "Ran" with empty content, so the total_debit / total_credit columns
     * on purchase_entries were never created. This migration adds them.
     */
    public function up(): void
    {
        Schema::table('purchase_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_entries', 'total_debit')) {
                $table->decimal('total_debit', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('purchase_entries', 'total_credit')) {
                $table->decimal('total_credit', 12, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_entries', function (Blueprint $table) {
            $table->dropColumn(['total_debit', 'total_credit']);
        });
    }
};
