<?php

namespace App\Filament\Vouchers\Resources\VoucherResource\Pages;

use App\Filament\Vouchers\Resources\VoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVoucher extends EditRecord
{
    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn ($record) => $record->status !== 'paid')
                ->tooltip('Paid vouchers cannot be voided from here. Process via Liquidation for an audit trail.'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $voucher = $this->record;

        // SCENARIO 1: Voucher was already APPROVED or mid-approver-chain.
        // Wipe all signatures and restart the approval chain.
        if (in_array($voucher->status, ['approved', 'pending_approver'])) {
            $data['status'] = 'pending_checker';
            $data['current_approval_step'] = 1;

            // Notify everyone who already signed it about the reset
            $previousApprovers = $voucher->approvals()->with('user')->get()->pluck('user')->filter()->unique('id');
            foreach ($previousApprovers as $approver) {
                if ($approver->id !== auth()->id()) {
                    \Filament\Notifications\Notification::make()
                        ->title('Voucher Edited & Reset')
                        ->body("Voucher #{$voucher->voucher_number} was edited by " . auth()->user()->name . ". Your previous approval was cleared and it has returned to step 1.")
                        ->warning()
                        ->sendToDatabase($approver);
                }
            }

            $voucher->approvals()->delete();

            activity()
                ->performedOn($voucher)
                ->causedBy(auth()->user())
                ->event('demoted')
                ->log('Voucher #' . $voucher->voucher_number . ' was edited after being approved/submitted by ' . auth()->user()->name . '. All approvals cleared. Status reset to Pending Checker.');

            \Filament\Notifications\Notification::make()
                ->title('⚠️ Approval Reset')
                ->body('This voucher was already approved. Because you edited it, all previous approvals have been cleared and it must go through the approval process again.')
                ->warning()
                ->persistent()
                ->send();
        }

        // SCENARIO 2: Voucher is at PENDING CHECKER stage (submitted but not yet to approver).
        // Minor correction by accountant — keep at pending_checker, just log the edit.
        elseif ($voucher->status === 'pending_checker') {
            activity()
                ->performedOn($voucher)
                ->causedBy(auth()->user())
                ->event('edited')
                ->log('Voucher #' . $voucher->voucher_number . ' was modified while in Pending Checker status by ' . auth()->user()->name . '.');
        }

        // SCENARIO 3: Voucher was REJECTED and creator is fixing it.
        // Reset to draft so they can resubmit cleanly.
        elseif ($voucher->status === 'rejected') {
            $data['status'] = 'draft';

            activity()
                ->performedOn($voucher)
                ->causedBy(auth()->user())
                ->event('revised')
                ->log('Voucher #' . $voucher->voucher_number . ' was revised after rejection by ' . auth()->user()->name . '. Status reset to Draft.');

            \Filament\Notifications\Notification::make()
                ->title('Voucher Revised')
                ->body('Your changes have been saved. The voucher is now in Draft — click Submit when you are ready to resubmit for approval.')
                ->info()
                ->send();
        }

        return $data;
    }
}
