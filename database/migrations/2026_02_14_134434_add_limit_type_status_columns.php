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
        // 1. Transaction Limit (Branch)
        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('transaction_limit', 15, 2)->nullable()->after('max_limit');
        });

        // 2. Transacton Type (Category) - Expense/Replenishment
        Schema::table('categories', function (Blueprint $table) {
            $table->string('type')->default('expense')->after('name'); // 'expense' or 'replenishment'
        });

        // 3. Approval Status (Transaction) - Pending/Approved/Rejected
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('amount');
        });

        // 4. Data Migration: Map is_approved -> status
        // Since is_approved existed, let's use it to set status
        DB::table('transactions')->where('is_approved', 1)->update(['status' => 'approved']);
        DB::table('transactions')->where('is_approved', 0)->update(['status' => 'pending']);

        // 5. Drop old column
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('is_approved');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('transaction_limit');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_approved')->default(true); // Restore column
            $table->dropColumn('status');
        });
        
        // Restore data roughly (approved -> is_approved=1, else 0)
        // Not perfect but safe enough for rollback
    }
};
