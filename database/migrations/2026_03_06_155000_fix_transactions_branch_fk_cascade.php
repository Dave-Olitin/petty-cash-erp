<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix #2: Change branch_id FK on transactions from cascadeOnDelete to restrictOnDelete.
     * A cascade would permanently destroy ALL transaction records if a branch is deleted,
     * bypassing soft-deletes entirely. RestrictOnDelete prevents the delete and forces
     * the admin to handle transactions manually first.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Drop the old cascading FK
            $table->dropForeign(['branch_id']);

            // Re-add with restrictOnDelete — cannot delete a branch that has transactions
            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);

            // Restore the original (dangerous) cascade
            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->cascadeOnDelete();
        });
    }
};
