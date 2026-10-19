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
        // 1. Create dynamic PRO Categories table
        Schema::create('pro_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            // New Joiner Default Government / Typing Fees (AED)
            $table->decimal('offer_letter_cost', 10, 2)->default(50.00);
            $table->decimal('dubins_cost', 10, 2)->default(120.00);
            $table->decimal('wp_submission_cost', 10, 2)->default(1200.00);
            $table->decimal('evisa_cost', 10, 2)->default(450.00);
            $table->decimal('change_status_cost', 10, 2)->default(650.00);
            $table->decimal('iloe_cost', 10, 2)->default(63.00);
            $table->decimal('contract_submission_cost', 10, 2)->default(100.00);
            $table->decimal('emirates_id_cost', 10, 2)->default(370.00);
            $table->decimal('residence_visa_cost', 10, 2)->default(450.00);

            // Renewal Default Government / Typing Fees (AED)
            $table->decimal('renewal_wp_cost', 10, 2)->default(1200.00);
            $table->decimal('renewal_dubins_cost', 10, 2)->default(120.00);
            $table->decimal('renewal_iloe_cost', 10, 2)->default(63.00);
            $table->decimal('renewal_contract_cost', 10, 2)->default(100.00);
            $table->decimal('renewal_emirates_id_cost', 10, 2)->default(370.00);
            $table->decimal('renewal_residence_visa_cost', 10, 2)->default(450.00);

            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Add license_expiry to ledger_branches
        if (!Schema::hasColumn('ledger_branches', 'license_expiry')) {
            Schema::table('ledger_branches', function (Blueprint $table) {
                $table->date('license_expiry')->nullable()->after('name');
            });
        }

        // 3. Link pro_records to ledger_branches and pro_categories
        Schema::table('pro_records', function (Blueprint $table) {
            $table->foreignId('ledger_branch_id')->nullable()->after('id')->constrained('ledger_branches')->nullOnDelete();
            $table->foreignId('pro_category_id')->nullable()->after('process_type')->constrained('pro_categories')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pro_records', function (Blueprint $table) {
            $table->dropForeign(['ledger_branch_id']);
            $table->dropColumn('ledger_branch_id');
            $table->dropForeign(['pro_category_id']);
            $table->dropColumn('pro_category_id');
        });

        if (Schema::hasColumn('ledger_branches', 'license_expiry')) {
            Schema::table('ledger_branches', function (Blueprint $table) {
                $table->dropColumn('license_expiry');
            });
        }

        Schema::dropIfExists('pro_categories');
    }
};
