<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Backfill from "created" logs
        $createdLogs = DB::table('activity_log')
            ->where('subject_type', 'App\Models\PurchaseEntry')
            ->where('description', 'created')
            ->whereNotNull('causer_id')
            ->orderBy('created_at', 'asc')
            ->get(['subject_id', 'causer_id']);

        foreach ($createdLogs as $log) {
            DB::table('purchase_entries')
                ->where('id', $log->subject_id)
                ->whereNull('user_id')
                ->update(['user_id' => $log->causer_id]);
        }

        // 2. For remaining records, fetch the earliest activity log causer
        $remainingEntryIds = DB::table('purchase_entries')
            ->whereNull('user_id')
            ->pluck('id');

        foreach ($remainingEntryIds as $id) {
            $earliestLog = DB::table('activity_log')
                ->where('subject_type', 'App\Models\PurchaseEntry')
                ->where('subject_id', $id)
                ->whereNotNull('causer_id')
                ->orderBy('created_at', 'asc')
                ->first(['causer_id']);

            if ($earliestLog) {
                DB::table('purchase_entries')
                    ->where('id', $id)
                    ->update(['user_id' => $earliestLog->causer_id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No action needed in down since it's just a data backfill.
    }
};
