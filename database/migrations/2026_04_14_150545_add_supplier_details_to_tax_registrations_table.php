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
        Schema::table('tax_registrations', function (Blueprint $table) {
            $table->string('trn', 50)->nullable()->change(); // Allow empty TRNs
            $table->string('supplier_code')->nullable()->unique()->after('trn');
            $table->string('payment_terms')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('entity')->nullable();
            $table->date('started_date')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_registrations', function (Blueprint $table) {
            $table->string('trn', 50)->nullable(false)->change();
            $table->dropColumn([
                'supplier_code',
                'payment_terms',
                'contact_name',
                'phone',
                'email',
                'entity',
                'started_date',
                'is_active',
            ]);
        });
    }
};
