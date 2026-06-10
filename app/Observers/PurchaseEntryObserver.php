<?php

namespace App\Observers;

use App\Models\PurchaseEntry;

class PurchaseEntryObserver
{
    /**
     * Handle the PurchaseEntry "created" event.
     */
    public function created(PurchaseEntry $purchaseEntry): void
    {
        $accountants = \App\Models\User::role('Accountant')->get();

        if ($accountants->count() > 0) {
            $creator = $purchaseEntry->user_id ? \App\Models\User::find($purchaseEntry->user_id) : auth()->user();
            $creatorName = $creator ? $creator->name : 'System';

            \Filament\Notifications\Notification::make()
                ->title('New Purchase Entry Created')
                ->body("{$purchaseEntry->entry_no} has been created by {$creatorName}.")
                ->info()
                ->sendToDatabase($accountants);
        }
    }

    /**
     * Handle the PurchaseEntry "updated" event.
     */
    public function updated(PurchaseEntry $purchaseEntry): void
    {
        //
    }

    /**
     * Handle the PurchaseEntry "deleted" event.
     */
    public function deleted(PurchaseEntry $purchaseEntry): void
    {
        //
    }

    /**
     * Handle the PurchaseEntry "restored" event.
     */
    public function restored(PurchaseEntry $purchaseEntry): void
    {
        //
    }

    /**
     * Handle the PurchaseEntry "force deleted" event.
     */
    public function forceDeleted(PurchaseEntry $purchaseEntry): void
    {
        //
    }
}
