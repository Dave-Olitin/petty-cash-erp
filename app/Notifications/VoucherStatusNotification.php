<?php

namespace App\Notifications;

use App\Models\Voucher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VoucherStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Voucher $voucher,
        public readonly string $event, // 'submitted' | 'checked' | 'approved' | 'rejected' | 'paid'
        public readonly ?string $comments = null,
    ) {}

    /**
     * Delivery channels — both in-app and email.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = match ($this->event) {
            'submitted'       => "New Voucher Submitted: {$this->voucher->voucher_number}",
            'checked'         => "Voucher Checked & Forwarded: {$this->voucher->voucher_number}",
            'approved'        => "✅ Voucher Approved: {$this->voucher->voucher_number}",
            'rejected'        => "❌ Voucher Rejected: {$this->voucher->voucher_number}",
            'paid'            => "💰 Voucher Paid: {$this->voucher->voucher_number}",
            'reminder_checker' => "⏰ Action Required: Voucher {$this->voucher->voucher_number} Awaiting Check",
            'reminder_approver' => "⏰ Action Required: Voucher {$this->voucher->voucher_number} Awaiting Approval",
            default           => "Voucher Update: {$this->voucher->voucher_number}",
        };

        $intro = match ($this->event) {
            'submitted'       => "A new voucher has been submitted for checking.",
            'checked'         => "A voucher has been checked by the accountant and is awaiting final approval.",
            'approved'        => "Your voucher has been approved and will be processed for payment.",
            'rejected'        => "Your voucher has been returned with comments. Please review and resubmit.",
            'paid'            => "Your voucher has been marked as paid.",
            'reminder_checker' => "⏰ Reminder: This voucher has been pending your review for over 24 hours. Please take action.",
            'reminder_approver' => "⏰ Reminder: This voucher has been pending your approval for over 24 hours. Please take action.",
            default           => "There is an update on your voucher.",
        };

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello, {$notifiable->name}!")
            ->line($intro)
            ->line("**Voucher:** {$this->voucher->voucher_number}")
            ->line("**Payee:** {$this->voucher->payee}")
            ->line("**Amount:** AED " . number_format($this->voucher->amount, 2))
            ->line("**Requester:** {$this->voucher->user->name}");

        if ($this->comments) {
            $mail->line("**Comments:** {$this->comments}");
        }

        $mail->action('View Voucher', url('/vouchers/vouchers'))
             ->line('This is an automated notification from the Petty Cash ERP system.');

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'voucher_id'     => $this->voucher->id,
            'voucher_number' => $this->voucher->voucher_number,
            'event'          => $this->event,
            'amount'         => $this->voucher->amount,
            'payee'          => $this->voucher->payee,
            'requester'      => $this->voucher->user->name,
            'comments'       => $this->comments,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
