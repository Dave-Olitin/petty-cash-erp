<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_voucher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // Migrate existing links
        $entries = DB::table('journal_entries')->whereNotNull('voucher_id')->get();
        foreach ($entries as $entry) {
            DB::table('journal_entry_voucher')->insert([
                'journal_entry_id' => $entry->id,
                'voucher_id' => $entry->voucher_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
            $table->dropColumn('voucher_id');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
        });

        // Attempt to restore primary link
        $pivots = DB::table('journal_entry_voucher')->orderBy('id')->get();
        foreach ($pivots as $pivot) {
            DB::table('journal_entries')->where('id', $pivot->journal_entry_id)->update(['voucher_id' => $pivot->voucher_id]);
        }

        Schema::dropIfExists('journal_entry_voucher');
    }
};
