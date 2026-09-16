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
        Schema::table('float_replenishments', function (Blueprint $table) {
            $table->string('bank_reference', 100)->nullable()->after('account_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('float_replenishments', function (Blueprint $table) {
            $table->dropColumn('bank_reference');
        });
    }
};
