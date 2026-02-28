<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('cheque_no', 50)->nullable()->after('attachment_paths');
            $table->date('cheque_date')->nullable()->after('cheque_no');
            $table->string('bank', 100)->nullable()->after('cheque_date');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['cheque_no', 'cheque_date', 'bank']);
        });
    }
};
