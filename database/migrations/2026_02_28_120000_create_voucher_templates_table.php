<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_templates', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('tel_no')->nullable();
            $table->text('address')->nullable();
            $table->string('trn')->nullable();
            $table->string('prefix')->unique();        // e.g. "ETC", "TG", "IC", "SB"
            $table->string('branch_code')->nullable();  // auto from initials, editable
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_templates');
    }
};
