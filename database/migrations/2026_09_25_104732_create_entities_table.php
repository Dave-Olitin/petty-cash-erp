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
        // 1. Create the entities lookup table
        if (!Schema::hasTable('entities')) {
            Schema::create('entities', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();     // e.g., "ABC Trading LLC"
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Drop the old free-text entity column from branches if present
        //    and replace it with a proper FK to entities.id
        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'entity')) {
                $table->dropColumn('entity');
            }
            if (!Schema::hasColumn('branches', 'entity_id')) {
                $table->foreignId('entity_id')
                      ->nullable()
                      ->after('name')
                      ->constrained('entities')
                      ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['entity_id']);
            $table->dropColumn('entity_id');
            $table->string('entity')->nullable()->after('name');
        });

        Schema::dropIfExists('entities');
    }
};
