<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send escalation reminders to Accountants and Approvers for vouchers
// that have been pending for more than 24 hours — runs twice a day.
Schedule::command('vouchers:send-escalation-reminders')
    ->dailyAt('09:00')
    ->description('Morning escalation reminders for stale pending vouchers');

Schedule::command('vouchers:send-escalation-reminders')
    ->dailyAt('16:00')
    ->description('Afternoon escalation reminders for stale pending vouchers');

// Daily PRO overstay fines sync and proactive expiration alerts (08:00 AM)
Schedule::command('pro:sync-fines-and-alerts')
    ->dailyAt('08:00')
    ->description('Daily PRO overstay fines sync and expiry alerts');

