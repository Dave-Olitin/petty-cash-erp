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
        Schema::table('pro_records', function (Blueprint $table) {
            if (!Schema::hasColumn('pro_records', 'fines_settled_at')) {
                $table->timestamp('fines_settled_at')->nullable()->after('manual_fine_override');
            }
            if (!Schema::hasColumn('pro_records', 'fine_settlement_type')) {
                $table->string('fine_settlement_type')->nullable()->after('fines_settled_at');
            }
            if (!Schema::hasColumn('pro_records', 'fine_amount_paid')) {
                $table->decimal('fine_amount_paid', 10, 2)->default(0.00)->after('fine_settlement_type');
            }
            if (!Schema::hasColumn('pro_records', 'fine_receipt_number')) {
                $table->string('fine_receipt_number')->nullable()->after('fine_amount_paid');
            }
            if (!Schema::hasColumn('pro_records', 'fine_receipt_attachment')) {
                $table->string('fine_receipt_attachment')->nullable()->after('fine_receipt_number');
            }
            if (!Schema::hasColumn('pro_records', 'fine_settlement_notes')) {
                $table->text('fine_settlement_notes')->nullable()->after('fine_receipt_attachment');
            }
            if (!Schema::hasColumn('pro_records', 'fine_settled_by')) {
                $table->foreignId('fine_settled_by')->nullable()->after('fine_settlement_notes')->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pro_records', function (Blueprint $table) {
            if (Schema::hasColumn('pro_records', 'fine_settled_by')) {
                $table->dropForeign(['fine_settled_by']);
                $table->dropColumn('fine_settled_by');
            }
            $columnsToDrop = [
                'fines_settled_at',
                'fine_settlement_type',
                'fine_amount_paid',
                'fine_receipt_number',
                'fine_receipt_attachment',
                'fine_settlement_notes',
            ];
            foreach ($columnsToDrop as $col) {
                if (Schema::hasColumn('pro_records', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
