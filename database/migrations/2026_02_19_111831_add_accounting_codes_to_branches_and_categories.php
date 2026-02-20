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
        Schema::table('branches', function (Blueprint $table) {
            if (!Schema::hasColumn('branches', 'code')) {
                $table->string('code')->nullable()->after('name');
            }
            if (!Schema::hasColumn('branches', 'gl_code')) {
                $table->string('gl_code')->nullable()->after('code');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'gl_code')) {
                $table->string('gl_code')->nullable()->after('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['code', 'gl_code']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('gl_code');
        });
    }
};
