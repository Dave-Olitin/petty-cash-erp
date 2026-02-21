<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes to transaction_items for category aggregation queries.
     * category_id is used heavily by ExpensesByCategoryChart GROUP BY queries.
     */
    public function up(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            // Used by ExpensesByCategoryChart and category-level reporting
            $table->index('category_id', 'ti_category_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropIndex('ti_category_id_index');
        });
    }
};
