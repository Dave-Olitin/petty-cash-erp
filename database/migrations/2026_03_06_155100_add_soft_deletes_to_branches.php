<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix #6: Add softDeletes (deleted_at) column to the branches table.
     * Branch model uses the SoftDeletes trait but the migration never created
     * the column — Branch::destroy() would throw a SQL error without this.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('branches', 'deleted_at')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
