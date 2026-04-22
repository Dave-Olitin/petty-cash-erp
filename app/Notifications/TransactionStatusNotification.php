<?php

namespace App\Notifications;

use App\Models\Transaction;
use Filament\Notifications\Actions\Action;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class TransactionStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Transaction $transaction;
    public string $type; // 'created', 'approved', 'rejected'

    /**
     * Create a new notification instance.
     */
    public function __construct(Transaction $transaction, string $type)
    {
        $this->transaction = $transaction;
        $this->type = $type;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', WebPushChannel::class];

        if (config('mail.mailers.smtp.host') && config('mail.mailers.smtp.host') !== '127.0.0.1') {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // In Filament v3, the default query string parameter for tabs is usually `activeTab`.
        // However, if the user is already on the page, Livewire wire:navigate might swallow the query string update.
        $tabName = $this->transaction->status ?? 'all';
        $actionUrl = url("/admin/transactions?activeTab={$tabName}");
        
        $message = (new MailMessage)
            ->subject("Petty Cash: Transaction {$this->type}")
            ->greeting("Hello {$notifiable->name},");

        if ($this->type === 'created') {
            $message->line("A new petty cash transaction has been submitted by {$this->transaction->user->name} and requires your review.")
                    ->line("Amount: AED {$this->transaction->amount}")
                    ->line("Payee: {$this->transaction->payee}");
        } elseif ($this->transaction->type === 'REPLENISHMENT' && $this->type === 'approved' && $notifiable->branch_id === $this->transaction->branch_id) {
            $message->line("Your branch has received a fund replenishment of AED {$this->transaction->amount}.");
        } else {
            $message->line("Your petty cash transaction of AED {$this->transaction->amount} for {$this->transaction->payee} has been {$this->type}.");
        }

        return $message
            ->action('View Transactions', $actionUrl)
            ->line('Thank you for using our Petty Cash system!');
    }

    /**
     * Get the array representation for Filament's database notification bell.
     */
    public function toArray(object $notifiable): array
    {
        $isReplenishment = $this->transaction->type === 'REPLENISHMENT' && $this->type === 'approved' && $notifiable->branch_id === $this->transaction->branch_id;
        
        $title = $this->type === 'created' ? 'New Transaction Submitted' : ($isReplenishment ? 'Funds Received' : 'Transaction ' . ucfirst($this->type));
        
        if ($this->type === 'created') {
            $body = "{$this->transaction->user->name} submitted an expense for AED {$this->transaction->amount}.";
        } elseif ($isReplenishment) {
            $body = "Your branch just received a replenishment of AED {$this->transaction->amount}.";
        } else {
            $body = "Your transaction for AED {$this->transaction->amount} has been {$this->type}.";
        }

        return \Filament\Notifications\Notification::make()
            ->title($title)
            ->body($body)
            ->icon($this->type === 'approved' ? 'heroicon-o-check-circle' : ($this->type === 'rejected' ? 'heroicon-o-x-circle' : 'heroicon-o-document-text'))
            ->iconColor($this->type === 'approved' ? 'success' : ($this->type === 'rejected' ? 'danger' : 'warning'))
            ->actions([
                Action::make('view')
                    ->button()
                    ->url(function() {
                        $tabName = $this->transaction->status ?? 'all';
                        return url("/admin/transactions?activeTab={$tabName}");
                    })
                    ->openUrlInNewTab() // Force actual browser navigation instead of SPA link check
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    /**
     * Get the Web Push representation.
     */
    public function toWebPush($notifiable, $notification)
    {
        $isReplenishment = $this->transaction->type === 'REPLENISHMENT' && $this->type === 'approved' && $notifiable->branch_id === $this->transaction->branch_id;
        
        $title = $this->type === 'created' ? 'New Transaction' : ($isReplenishment ? 'Funds Received' : 'Transaction ' . ucfirst($this->type));
        
        if ($this->type === 'created') {
            $body = "{$this->transaction->user->name} submitted an expense for AED {$this->transaction->amount}.";
        } elseif ($isReplenishment) {
            $body = "Your branch received a replenishment of AED {$this->transaction->amount}.";
        } else {
            $body = "Your transaction for AED {$this->transaction->amount} was {$this->type}.";
        }

        $tabName = $this->transaction->status ?? 'all';
        return (new WebPushMessage)
            ->title($title)
            ->icon('/images/icon-192.png')
            ->body($body)
            ->action('View', url("/admin/transactions?activeTab={$tabName}"))
            ->vibrate([100, 50, 100]);
    }
}
