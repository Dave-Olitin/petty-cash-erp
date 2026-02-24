<?php

namespace App\Console\Commands;

use App\Models\Voucher;
use App\Notifications\VoucherStatusNotification;
use App\Models\User;
use Illuminate\Console\Command;

class SendVoucherEscalationReminders extends Command
{
    protected $signature = 'vouchers:send-escalation-reminders
                            {--hours=24 : Hours a voucher can sit pending before reminder fires}';

    protected $description = 'Send escalation reminders for vouchers that have been pending for too long';

    public function handle(): void
    {
        $hours = (int) $this->option('hours');

        // Vouchers pending checker for too long → remind Accountants
        $pendingChecker = Voucher::where('status', 'pending_checker')
            ->where('updated_at', '<=', now()->subHours($hours))
            ->with('user')
            ->get();

        if ($pendingChecker->isNotEmpty()) {
            $accountants = User::role('Accountant')->get();
            $pendingChecker->each(function (Voucher $voucher) use ($accountants) {
                $accountants->each(fn ($user) => $user->notify(
                    new VoucherStatusNotification($voucher, 'reminder_checker')
                ));
            });
            $this->info("Sent {$pendingChecker->count()} escalation reminder(s) to Accountants.");
        }

        // Vouchers pending approver for too long → remind Approvers
        $pendingApprover = Voucher::where('status', 'pending_approver')
            ->where('updated_at', '<=', now()->subHours($hours))
            ->with('user')
            ->get();

        if ($pendingApprover->isNotEmpty()) {
            $approvers = User::role('Approver')->get();
            $pendingApprover->each(function (Voucher $voucher) use ($approvers) {
                $approvers->each(fn ($user) => $user->notify(
                    new VoucherStatusNotification($voucher, 'reminder_approver')
                ));
            });
            $this->info("Sent {$pendingApprover->count()} escalation reminder(s) to Approvers.");
        }

        if ($pendingChecker->isEmpty() && $pendingApprover->isEmpty()) {
            $this->info("No stale vouchers found. All clear!");
        }
    }
}
