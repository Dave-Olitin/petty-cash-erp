<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create the suppliers table
        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('trn')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. Add supplier_id to transactions table
        if (!Schema::hasColumn('transactions', 'supplier_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignId('supplier_id')
                    ->nullable()
                    ->after('supplier')
                    ->constrained('suppliers')
                    ->nullOnDelete();
            });
        }

        // 3. Auto-migrate existing unique suppliers & TRNs from transactions
        $existingRecords = DB::table('transactions')
            ->whereNotNull('supplier')
            ->whereRaw("TRIM(supplier) != ''")
            ->select('supplier')
            ->distinct()
            ->get();

        foreach ($existingRecords as $record) {
            $cleanName = trim($record->supplier);
            if ($cleanName === '') {
                continue;
            }

            // Find if already inserted (case-insensitive or exact match)
            $existingSupplier = DB::table('suppliers')->where('name', $cleanName)->first();

            if ($existingSupplier) {
                $supplierId = $existingSupplier->id;
            } else {
                // Find any valid TRN associated with this supplier name
                $bestTrn = DB::table('transactions')
                    ->where('supplier', $record->supplier)
                    ->whereNotNull('trn')
                    ->whereRaw("TRIM(trn) != ''")
                    ->value('trn');

                $supplierId = DB::table('suppliers')->insertGetId([
                    'name'       => $cleanName,
                    'trn'        => $bestTrn ? trim($bestTrn) : null,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Link all matching transactions to this supplier_id
            DB::table('transactions')
                ->where('supplier', $record->supplier)
                ->update(['supplier_id' => $supplierId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });

        Schema::dropIfExists('suppliers');
    }
};
