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
        Schema::table('denominations', function (Blueprint $table) {
            $table->boolean('is_change_received')->default(true)->after('change_given');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('denominations', function (Blueprint $table) {
            $table->dropColumn('is_change_received');
        });
    }
};
