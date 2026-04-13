<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Journal Entries: add entity (company) and description
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('entity')->nullable()->after('entry_no');        // Company from VoucherTemplate
            $table->text('description')->nullable()->after('voucher_id');   // Full-width description
        });

        // Purchase Entries: add entity, branch, supplier FK, due_date, price_type
        Schema::table('purchase_entries', function (Blueprint $table) {
            $table->string('entity')->nullable()->after('entry_no');        // Company from VoucherTemplate
            $table->string('branch')->nullable()->after('entity');          // LedgerBranch name
            $table->foreignId('tax_registration_id')
                  ->nullable()
                  ->after('branch')
                  ->constrained('tax_registrations')
                  ->nullOnDelete();                                          // Supplier from TaxRegistration
            $table->date('due_date')->nullable()->after('date');            // Payment due date
            $table->string('price_type')->nullable()->after('due_date');    // inclusive / exclusive
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['entity', 'description']);
        });

        Schema::table('purchase_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_registration_id');
            $table->dropColumn(['entity', 'branch', 'due_date', 'price_type']);
        });
    }
};
