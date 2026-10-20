<?php

namespace App\Notifications;

use Filament\Notifications\Actions\Action;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class SystemPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $actionUrl = '/',
        public string $tagId = 'system'
    ) {}

    // Channels: In-App Bell + WebSockets (Echo) + OS Push (Service Worker)
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', WebPushChannel::class];
    }

    // 1. Filament Toast / Database representation
    public function toFilamentNotification(): \Filament\Notifications\Notification
    {
        $notification = \Filament\Notifications\Notification::make()
            ->title($this->title)
            ->body($this->body)
            ->icon('heroicon-o-bell')
            ->iconColor('primary');

        if (!empty($this->actionUrl)) {
            $notification->actions([
                Action::make('view')
                    ->button()
                    ->url($this->actionUrl)
                    ->openUrlInNewTab()
                    ->markAsRead(),
            ]);
        }

        return $notification;
    }

    // Stored in `notifications` table for the bell
    public function toDatabase(object $notifiable): array
    {
        return $this->toFilamentNotification()->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return $this->toFilamentNotification()->getDatabaseMessage();
    }

    // Broadcast live over WebSockets to Echo
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        // Dispatches event to update the bell unread badge counter in real-time
        DatabaseNotificationsSent::dispatch($notifiable);

        return $this->toFilamentNotification()->getBroadcastMessage();
    }

    // Delivered via VAPID to Service Worker for Desktop OS Banners
    public function toWebPush($notifiable, $notification)
    {
        $push = (new WebPushMessage)
            ->title($this->title)
            ->icon('/images/voucher-app-icon.png')
            ->body($this->body)
            ->tag("notif-{$this->tagId}-" . time()) // Ensures alerts stack individually
            ->vibrate([100, 50, 100]);

        if (!empty($this->actionUrl)) {
            $push->action('View', $this->actionUrl);
            $push->data(['action' => $this->actionUrl]);
        }

        return $push;
    }
}
