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
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE vouchers MODIFY COLUMN status ENUM('draft','pending_checker','pending_approver','approved','rejected','paid','voided') NOT NULL DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE vouchers MODIFY COLUMN status ENUM('draft','pending_checker','pending_approver','approved','rejected','paid') NOT NULL DEFAULT 'draft'");
    }
};
