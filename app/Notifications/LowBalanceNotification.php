<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class LowBalanceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public float $currentBalance
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', WebPushChannel::class];

        $host = config('mail.mailers.smtp.host');
        if ($host && $host !== '127.0.0.1' && $host !== 'your-smtp-host') {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('⚠️ Action Required: Low Head Office Float Balance')
            ->greeting("Hello, {$notifiable->name}!")
            ->line('The Head Office petty cash float has dropped below the minimum threshold of **AED 2,000.00**.')
            ->line('**Current Balance:** AED ' . number_format($this->currentBalance, 2))
            ->line('Please initiate a float replenishment to ensure continuous operations.')
            ->action('View Head Office Float', url('/vouchers'))
            ->line('This is an automated system alert.');
    }

    public function toDatabase(object $notifiable): array
    {
        return \Filament\Notifications\Notification::make()
            ->title('Low Float Balance')
            ->body('Head Office float dropped below AED 2,000. Current: AED ' . number_format($this->currentBalance, 2))
            ->danger()
            ->icon('heroicon-o-exclamation-triangle')
            ->getDatabaseMessage();
    }

    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title('⚠️ Low Float Balance')
            ->icon('/images/icon-192.png')
            ->body('Head Office float dropped below AED 2,000. Current: AED ' . number_format($this->currentBalance, 2))
            ->action('View', url('/admin'))
            ->options([
                'TTL' => 86400, // 1 day
                'urgency' => 'high',
            ])
            ->data(['id' => $notification->id, 'url' => url('/admin')]);
    }
}
