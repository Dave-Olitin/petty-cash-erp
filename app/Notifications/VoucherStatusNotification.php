<?php

namespace App\Notifications;

use App\Models\Voucher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

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
        $channels = ['database', WebPushChannel::class];

        // Only attempt to send an email if a real SMTP host has been configured.
        // This prevents the application from crashing if the .env file is incomplete, 
        // ensuring the in-app Notification Bell still successfully receives the DB alert.
        $host = config('mail.mailers.smtp.host', '');
        if (!empty($host) && !in_array($host, ['127.0.0.1', 'localhost', 'your-smtp-host'])) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isReceipt = $this->voucher->type === 'receipt';

        $subject = match ($this->event) {
            'submitted'       => "New " . ($isReceipt ? "Receipt Voucher" : "Voucher") . " Submitted: {$this->voucher->voucher_number}",
            'checked'         => ($isReceipt ? "Receipt" : "Voucher") . " Checked & Forwarded: {$this->voucher->voucher_number}",
            'approved'        => "✅ " . ($isReceipt ? "Receipt" : "Voucher") . " Approved: {$this->voucher->voucher_number}",
            'rejected'        => "❌ " . ($isReceipt ? "Receipt" : "Voucher") . " Rejected: {$this->voucher->voucher_number}",
            'paid'            => "💰 " . ($isReceipt ? "Receipt Collected" : "Voucher Paid") . ": {$this->voucher->voucher_number}",
            'reminder_checker' => "⏰ Action Required: " . ($isReceipt ? "Receipt" : "Voucher") . " {$this->voucher->voucher_number} Awaiting Check",
            'reminder_approver' => "⏰ Action Required: " . ($isReceipt ? "Receipt" : "Voucher") . " {$this->voucher->voucher_number} Awaiting Approval",
            default           => ($isReceipt ? "Receipt" : "Voucher") . " Update: {$this->voucher->voucher_number}",
        };

        $intro = match ($this->event) {
            'submitted'       => "A new " . ($isReceipt ? "receipt voucher" : "voucher") . " has been submitted for checking.",
            'checked'         => "A " . ($isReceipt ? "receipt voucher" : "voucher") . " has been checked by the accountant and is awaiting final approval.",
            'approved'        => "Your " . ($isReceipt ? "receipt voucher" : "voucher") . " has been approved and will be processed.",
            'rejected'        => "Your " . ($isReceipt ? "receipt voucher" : "voucher") . " has been returned with comments. Please review and resubmit.",
            'paid'            => "Your " . ($isReceipt ? "receipt voucher has been officially collected." : "voucher has been marked as paid."),
            'reminder_checker' => "⏰ Reminder: This " . ($isReceipt ? "receipt voucher" : "voucher") . " has been pending your review for over 24 hours. Please take action.",
            'reminder_approver' => "⏰ Reminder: This " . ($isReceipt ? "receipt voucher" : "voucher") . " has been pending your approval for over 24 hours. Please take action.",
            default           => "There is an update on your " . ($isReceipt ? "receipt voucher" : "voucher") . ".",
        };

        return (new MailMessage)
            ->subject($subject)
            ->replyTo(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.voucher-status', [
                'user'     => $notifiable,
                'voucher'  => $this->voucher,
                'event'    => $this->event,
                'comments' => $this->comments,
                'intro'    => $intro,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        $title = match ($this->event) {
            'submitted'       => "New Voucher Submitted",
            'checked'         => "Voucher Checked & Forwarded",
            'approved'        => "Voucher Approved",
            'rejected'        => "Voucher Rejected",
            'paid'            => "Voucher Paid",
            'reminder_checker' => "Action Required: Pending Check",
            'reminder_approver' => "Action Required: Pending Approval",
            default           => "Voucher Update",
        };

        $icon = match ($this->event) {
            'approved', 'paid' => 'heroicon-o-check-circle',
            'rejected'         => 'heroicon-o-x-circle',
            'submitted', 'checked' => 'heroicon-o-clock',
            default            => 'heroicon-o-information-circle',
        };

        $color = match ($this->event) {
            'approved', 'paid' => 'success',
            'rejected'         => 'danger',
            'submitted', 'checked' => 'warning',
            default            => 'gray',
        };

        $body = "**{$this->voucher->voucher_number}** - AED " . number_format($this->voucher->amount, 2);
        if ($this->comments) {
            $body .= "\n\nComments: {$this->comments}";
        }

        return \Filament\Notifications\Notification::make()
            ->title($title)
            ->body($body)
            ->icon($icon)
            ->color($color)
            ->actions([
                \Filament\Notifications\Actions\Action::make('open_voucher')
                    ->label('View Voucher')
                    ->button()
                    ->url(url("/vouchers/vouchers/{$this->voucher->id}"))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toWebPush($notifiable, $notification)
    {
        $title = match ($this->event) {
            'submitted'       => "New Voucher Submitted",
            'checked'         => "Voucher Checked & Forwarded",
            'approved'        => "Voucher Approved",
            'rejected'        => "Voucher Rejected",
            'paid'            => "Voucher Paid",
            'reminder_checker' => "Action Required: Pending Check",
            'reminder_approver' => "Action Required: Pending Approval",
            default           => "Voucher Update",
        };

        $body = "{$this->voucher->voucher_number} - AED " . number_format($this->voucher->amount, 2);
        if ($this->comments) {
            $body .= "\n\nComments: {$this->comments}";
        }

        return (new WebPushMessage)
            ->title($title)
            ->icon('/images/icon-192.png')
            ->body($body)
            ->options(['TTL' => '86400'])
            ->data(['id' => $notification->id, 'url' => url("/vouchers/vouchers/{$this->voucher->id}")]);
    }
}
