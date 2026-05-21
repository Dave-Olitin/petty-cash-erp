<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop foreign key constraint on voucher_id so we can alter it to be nullable
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
        });

        // 2. Modify voucher_id to be nullable and add float_replenishment_id column
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedBigInteger('voucher_id')->nullable()->change();
            
            // Re-add the foreign key constraint
            $table->foreign('voucher_id')
                ->references('id')
                ->on('vouchers')
                ->cascadeOnDelete();

            $table->foreignId('float_replenishment_id')
                ->nullable()
                ->after('voucher_id')
                ->constrained('float_replenishments')
                ->cascadeOnDelete();
        });

        // 3. Backfill historical float_replenishments attachments
        $repls = DB::table('float_replenishments')
            ->whereNotNull('attachment_paths')
            ->get(['id', 'attachment_paths', 'created_by', 'created_at', 'updated_at']);

        foreach ($repls as $repl) {
            $paths = json_decode($repl->attachment_paths, true);
            if (is_array($paths)) {
                foreach ($paths as $path) {
                    if (empty($path)) continue;
                    
                    $fileName = basename($path);
                    $fileType = pathinfo($path, PATHINFO_EXTENSION);
                    
                    DB::table('documents')->insert([
                        'float_replenishment_id' => $repl->id,
                        'file_path' => $path,
                        'file_name' => $fileName,
                        'file_type' => $fileType,
                        'uploaded_by' => $repl->created_by,
                        'created_at' => $repl->created_at ?? now(),
                        'updated_at' => $repl->updated_at ?? now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['float_replenishment_id']);
            $table->dropColumn('float_replenishment_id');
            $table->dropForeign(['voucher_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedBigInteger('voucher_id')->nullable(false)->change();
            $table->foreign('voucher_id')
                ->references('id')
                ->on('vouchers')
                ->cascadeOnDelete();
        });
    }
};
