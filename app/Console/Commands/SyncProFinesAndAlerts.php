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
        $records = ProRecord::whereNotNull('residence_visa_expiry')->get();
        $totalFinesAccrued = 0;
        $overdueCount = 0;

        foreach ($records as $record) {
            if ($record->fines_settled_at || $record->manual_fine_override) {
                if ($record->fine_pr_50 > 0 || $record->fine_eid_20 > 0) {
                    $totalFinesAccrued += ((float) $record->fine_pr_50 + (float) $record->fine_eid_20);
                    $overdueCount++;
                }
                continue;
            }

            $graceDays = (int) ($record->grace_period_days ?: 30);
            $correctFinesStart = $record->residence_visa_expiry->copy()->addDays($graceDays + 1);

            // A: Visa is still active & valid -> ZERO FINES
            if ($today->lte($record->residence_visa_expiry)) {
                $record->updateQuietly([
                    'fines_start_date' => $correctFinesStart,
                    'fine_pr_50'       => 0,
                    'fine_eid_20'      => 0,
                ]);
                continue;
            }

            // B: Visa is expired
            $daysExpired = (int) $record->residence_visa_expiry->diffInDays($today);

            if ($daysExpired <= $graceDays) {
                // Within Grace Period -> ZERO FINES
                $record->updateQuietly([
                    'fines_start_date' => $correctFinesStart,
                    'fine_pr_50'       => 0,
                    'fine_eid_20'      => 0,
                ]);
            } else {
                // Past Grace Period -> OVERSTAY PENALTIES ACTIVE
                $daysOverdue = $daysExpired - $graceDays;
                $pr50 = (float) ($daysOverdue * 50);
                $eid20 = (float) min(1000, $daysOverdue * 20);

                $record->updateQuietly([
                    'fines_start_date' => $correctFinesStart,
                    'fine_pr_50'       => $pr50,
                    'fine_eid_20'      => $eid20,
                ]);

                $totalFinesAccrued += ($pr50 + $eid20);
                $overdueCount++;
            }
        }

        $this->info("Updated fines for {$overdueCount} overdue records. Total Fines: AED {$totalFinesAccrued}");

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
            if ($overdueCount > 0) {
                \Illuminate\Support\Facades\Notification::send($recipients, new \App\Notifications\SystemPushNotification(
                    title: 'Overstay Penalties Alert',
                    body: "{$overdueCount} employee(s) past grace period · Total: AED " . number_format($totalFinesAccrued, 2),
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
