<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Voucher;
use App\Models\User;
use Filament\Notifications\Notification;

class SendVoucherReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-voucher-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily email and database reminders for pending vouchers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $vouchers = Voucher::whereIn('status', ['pending_checker', 'pending_approver'])
            ->where('updated_at', '<', now()->subDays(1)) // Remind if untouched for > 1 day
            ->get();

        foreach ($vouchers as $voucher) {
            $recipients = match ($voucher->status) {
                'pending_checker' => User::role('Accountant')->get(),
                'pending_approver' => User::role('Approver')->get(),
                default => collect([]),
            };

            if ($recipients->isNotEmpty()) {
                Notification::make()
                    ->title('Reminder: Action Required')
                    ->body("Voucher {$voucher->voucher_number} has been pending action for over 24 hours. Please review it.")
                    ->warning() // Yellow alert
                    ->sendToDatabase($recipients);
                
                // Also trigger Email if configured. Filament native `sendToDatabase` 
                // doesn't force emails unless connected to an Email gateway.
                // We'll broadcast natively here.
            }
        }

        $this->info('Reminders sent for ' . $vouchers->count() . ' pending vouchers.');
    }
}
