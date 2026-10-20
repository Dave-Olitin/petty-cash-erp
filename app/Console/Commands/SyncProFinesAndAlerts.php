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

        // 3. Notify Admins and Authorized PRO Users only
        $recipients = User::all()->filter(fn ($u) => $u->hasAnyRole(['PRO Officer', 'Admin', 'Super Admin']) || $u->can('pro_record.view'));

        if ($recipients->isNotEmpty()) {
            // A. Alert for Overstay Fines
            if ($overdueRecords->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send($recipients, new \App\Notifications\SystemPushNotification(
                    title: 'Overstay Penalties Alert',
                    body: "{$overdueRecords->count()} employee(s) past grace period · Total: AED " . number_format($totalFinesAccrued, 2),
                    actionUrl: '/vouchers/pro-records',
                    tagId: 'pro-overstay-fines'
                ));
            }

            // B. Alert for Visas Expiring in <= 30 Days
            if ($visasExpiring30d->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send($recipients, new \App\Notifications\SystemPushNotification(
                    title: 'Visa Expiry Notice',
                    body: "{$visasExpiring30d->count()} employee visa(s) expiring within 30 days.",
                    actionUrl: '/vouchers/pro-records',
                    tagId: 'pro-visas-expiring'
                ));
            }

            // C. Alert for Passports Expiring in <= 6 Months
            if ($passportsExpiring6mo->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send($recipients, new \App\Notifications\SystemPushNotification(
                    title: 'Passport Expiry Notice',
                    body: "{$passportsExpiring6mo->count()} employee passport(s) expiring within 6 months.",
                    actionUrl: '/vouchers/pro-records',
                    tagId: 'pro-passports-expiring'
                ));
            }
        }

        $this->info("PRO sync complete. Alerts sent to {$recipients->count()} user(s).");
        return Command::SUCCESS;
    }
}
