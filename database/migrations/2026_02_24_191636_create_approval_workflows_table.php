<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('step_order');          // 1, 2, 3 ...
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();            // e.g. "Finance Manager", "CEO"
            $table->timestamps();

            $table->unique(['step_order', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflows');
    }
};
