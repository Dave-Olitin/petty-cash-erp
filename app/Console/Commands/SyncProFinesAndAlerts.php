<?php

namespace App\Console\Commands;

use App\Models\ProRecord;
use App\Models\User;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class SyncProFinesAndAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pro:sync-fines-and-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily sync of UAE overstay fines and notification of expiring documents (visas, passports, licenses)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();
        $this->info("Running PRO Fines and Expiration Check for {$today->toDateString()}...");

        // 1. Sync Overdue Fines in Database
        $overdueRecords = ProRecord::whereNotNull('fines_start_date')
            ->where('fines_start_date', '<', $today)
            ->where('manual_fine_override', false)
            ->get();

        $totalFinesAccrued = 0;

        foreach ($overdueRecords as $record) {
            $days = (int) $record->fines_start_date->diffInDays($today);
            $pr50 = (float) ($days * 50);
            $eid20 = (float) min(1000, $days * 20);

            $record->updateQuietly([
                'fine_pr_50' => $pr50,
                'fine_eid_20' => $eid20,
            ]);

            $totalFinesAccrued += ($pr50 + $eid20);
        }

        $this->info("Updated fines for {$overdueRecords->count()} overdue records. Total Fines: AED {$totalFinesAccrued}");

        // 2. Identify Expiring Records
        $visasExpiring30d = ProRecord::whereNotNull('residence_visa_expiry')
            ->whereBetween('residence_visa_expiry', [$today, $today->copy()->addDays(30)])
            ->get();

        $passportsExpiring6mo = ProRecord::whereNotNull('passport_expiry')
            ->whereBetween('passport_expiry', [$today, $today->copy()->addMonths(6)])
            ->get();

        // 3. Notify Admins and Authorized Panel Users
        $recipients = User::all()->filter(fn ($u) => $u->hasAnyRole(['Admin', 'Super Admin']) || $u->can('access_vouchers_panel'));

        if ($recipients->isNotEmpty()) {
            // A. Alert for Overstay Fines
            if ($overdueRecords->isNotEmpty()) {
                Notification::make()
                    ->title('🚨 PRO Alert: Active Overstay Fines Accruing')
                    ->body("{$overdueRecords->count()} employee(s) have exceeded their grace period. Total accrued fines: AED " . number_format($totalFinesAccrued, 2))
                    ->danger()
                    ->sendToDatabase($recipients);
            }

            // B. Alert for Visas Expiring in <= 30 Days
            if ($visasExpiring30d->isNotEmpty()) {
                Notification::make()
                    ->title('⚠️ PRO Alert: Visas Expiring in 30 Days')
                    ->body("{$visasExpiring30d->count()} employee visa(s) are due for renewal within 30 days. Please initiate the renewal process.")
                    ->warning()
                    ->sendToDatabase($recipients);
            }

            // C. Alert for Passports Expiring in <= 6 Months
            if ($passportsExpiring6mo->isNotEmpty()) {
                Notification::make()
                    ->title('🛂 PRO Alert: Passports Expiring (< 6 Months)')
                    ->body("{$passportsExpiring6mo->count()} employee passport(s) have under 6 months validity. Renewals cannot be processed without passport renewal.")
                    ->warning()
                    ->sendToDatabase($recipients);
            }
        }

        $this->info("PRO sync complete. Alerts sent to {$recipients->count()} user(s).");
        return Command::SUCCESS;
    }
}
