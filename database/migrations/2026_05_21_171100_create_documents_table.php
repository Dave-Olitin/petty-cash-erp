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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')
                ->constrained('vouchers')
                ->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });

        // Backfill existing voucher attachments
        $vouchers = DB::table('vouchers')
            ->whereNotNull('attachment_paths')
            ->get(['id', 'attachment_paths', 'user_id', 'created_at', 'updated_at']);

        foreach ($vouchers as $voucher) {
            $paths = json_decode($voucher->attachment_paths, true);
            if (is_array($paths)) {
                foreach ($paths as $path) {
                    if (empty($path)) continue;
                    
                    $fileName = basename($path);
                    $fileType = pathinfo($path, PATHINFO_EXTENSION);
                    
                    DB::table('documents')->insert([
                        'voucher_id' => $voucher->id,
                        'file_path' => $path,
                        'file_name' => $fileName,
                        'file_type' => $fileType,
                        'uploaded_by' => $voucher->user_id,
                        'created_at' => $voucher->created_at ?? now(),
                        'updated_at' => $voucher->updated_at ?? now(),
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
        Schema::dropIfExists('documents');
    }
};
