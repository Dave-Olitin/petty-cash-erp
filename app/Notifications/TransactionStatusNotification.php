<?php

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;
use App\Filament\Resources\TransactionResource;

class TransactionStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Transaction $transaction;
    public string $action;

    /**
     * Create a new notification instance.
     * action can be: 'created', 'approved', 'rejected', 'deleted'
     */
    public function __construct(Transaction $transaction, string $action)
    {
        $this->transaction = $transaction;
        $this->action = $action;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', WebPushChannel::class];
    }

    protected function getMessageText(): string
    {
        $branchName = $this->transaction->branch ? $this->transaction->branch->name : 'Head Office';
        $ref = $this->transaction->reference_number;
        
        return match($this->action) {
            'created'  => "New transaction {$ref} submitted from {$branchName}.",
            'approved' => "Your transaction {$ref} has been approved.",
            'rejected' => "Your transaction {$ref} has been rejected.",
            'deleted'  => "Transaction {$ref} was deleted.",
            default    => "Transaction {$ref} was updated.",
        };
    }

    protected function getUrl(): string
    {
        // For HQ users, they view branches via the TransactionResource
        // For Branch users, they view their own transactions via the same resource
        return TransactionResource::getUrl('index');
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $color = match($this->action) {
            'approved' => 'success',
            'rejected' => 'error',
            default    => 'primary',
        };

        $mail = (new MailMessage)
            ->subject('Transaction ' . ucfirst($this->action) . ' [' . $this->transaction->reference_number . ']')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($this->getMessageText())
            ->line('Amount: AED ' . number_format($this->transaction->amount, 2))
            ->action('View Transaction', $this->getUrl());

        if ($this->action === 'rejected' && $this->transaction->rejection_reason) {
            $mail->line('Reason for Rejection: ' . $this->transaction->rejection_reason);
        }

        return $mail->line('Thank you for using Petty Cash ERP!');
    }

    /**
     * Get the array representation of the notification for the Database Bell.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'transaction_id' => $this->transaction->id,
            'reference'      => $this->transaction->reference_number,
            'amount'         => $this->transaction->amount,
            'message'        => $this->getMessageText(),
        ];
    }

    /**
     * Get the web push representation of the notification.
     *
     * @param  mixed  $notifiable
     * @param  mixed  $notification
     * @return \NotificationChannels\WebPush\WebPushMessage
     */
    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title('Transaction ' . ucfirst($this->action))
            ->icon('/images/icon-192.png')
            ->badge('/images/badge-72.png')
            ->body($this->getMessageText())
            ->action('View Transaction', 'open_transaction')
            ->data(['url' => $this->getUrl()])
            ->tag('transaction-'.$this->transaction->id);
    }
}
