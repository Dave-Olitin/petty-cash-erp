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
        Schema::create('pro_records', function (Blueprint $table) {
            $table->id();

            // Sponsoring Company / Branch
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('license_expiry')->nullable();

            // Employee Core Details
            $table->string('employee_name');
            $table->string('nationality')->nullable();
            $table->string('job_description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Process & Category
            $table->string('process_type')->default('new_inside'); // new_inside, new_outside, renewal, cancellation
            $table->string('company_category')->default('category_2'); // category_1, category_2, category_3
            $table->string('status')->default('in_progress'); // draft, in_progress, completed, cancelled, on_hold

            // Government Identifiers & Expiries
            $table->string('person_code')->nullable();       // MOHRE Person Code
            $table->string('unified_id')->nullable();       // Unified ID # (UID)
            $table->string('passport_number')->nullable();
            $table->date('passport_expiry')->nullable();
            $table->string('emirates_id_number')->nullable();
            $table->date('emirates_id_expiry')->nullable();
            $table->string('labour_card_number')->nullable();
            $table->date('labour_card_expiry')->nullable();
            $table->date('labour_contract_expiry')->nullable();
            $table->string('residence_visa_number')->nullable();
            $table->date('residence_visa_expiry')->nullable();

            // Grace Period & Overstay Fines
            $table->integer('grace_period_days')->default(30);
            $table->date('fines_start_date')->nullable();
            $table->decimal('fine_eid_20', 10, 2)->default(0.00);
            $table->decimal('fine_pr_50', 10, 2)->default(0.00);
            $table->boolean('manual_fine_override')->default(false);

            // 9-Stage Pipeline (Statuses, Dates, and Costs)
            // 1. Offer Letter
            $table->string('offer_letter_status')->default('Pending');
            $table->date('offer_letter_date')->nullable();
            $table->decimal('offer_letter_cost', 10, 2)->default(0.00);

            // 2. DUBINS (Dubai Insurance / ILOE / Workers Protection)
            $table->string('dubins_status')->default('Pending');
            $table->date('dubins_date')->nullable();
            $table->decimal('dubins_cost', 10, 2)->default(0.00);

            // 3. WP Submission (Work Permit / Labour Application)
            $table->string('wp_submission_status')->default('Pending');
            $table->date('wp_submission_date')->nullable();
            $table->decimal('wp_submission_cost', 10, 2)->default(0.00);

            // 4. EVISA (Entry Visa / Electronic Visa)
            $table->string('evisa_status')->default('Pending');
            $table->date('evisa_date')->nullable();
            $table->decimal('evisa_cost', 10, 2)->default(0.00);

            // 5. Change Status (Inside UAE Status Amendment)
            $table->string('change_status_status')->default('Pending');
            $table->date('change_status_date')->nullable();
            $table->decimal('change_status_cost', 10, 2)->default(0.00);

            // 6. ILOE (Involuntary Loss of Employment)
            $table->string('iloe_status')->default('Pending');
            $table->date('iloe_date')->nullable();
            $table->decimal('iloe_cost', 10, 2)->default(0.00);

            // 7. Contract Submission (MOHRE Signed Contract)
            $table->string('contract_submission_status')->default('Pending');
            $table->date('contract_submission_date')->nullable();
            $table->decimal('contract_submission_cost', 10, 2)->default(0.00);

            // 8. Emirates ID & Medical Fitness
            $table->string('emirates_id_stage_status')->default('Pending');
            $table->date('emirates_id_stage_date')->nullable();
            $table->decimal('emirates_id_stage_cost', 10, 2)->default(0.00);

            // 9. Residence Visa (Stamping / Digital Issuance)
            $table->string('residence_visa_stage_status')->default('Pending');
            $table->date('residence_visa_stage_date')->nullable();
            $table->decimal('residence_visa_stage_cost', 10, 2)->default(0.00);

            // Total Cost Summation
            $table->decimal('total_cost', 10, 2)->default(0.00);

            // Attachments & Notes
            $table->json('attachments')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for fast querying & filtering
            $table->index(['employee_name', 'status']);
            $table->index(['passport_number', 'passport_expiry']);
            $table->index(['emirates_id_number', 'emirates_id_expiry']);
            $table->index(['residence_visa_expiry', 'fines_start_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pro_records');
    }
};
