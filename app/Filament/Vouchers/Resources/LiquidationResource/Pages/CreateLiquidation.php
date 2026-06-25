<?php

namespace App\Filament\Vouchers\Resources\LiquidationResource\Pages;

use App\Filament\Vouchers\Resources\LiquidationResource;
use App\Models\Liquidation;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateLiquidation extends CreateRecord
{
    protected static string $resource = LiquidationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('create_liquidation')
                ->label('Create')
                ->requiresConfirmation()
                ->modalHeading('Confirm Liquidation Settlement')
                ->modalDescription(function () {
                    $data = $this->form->getRawState();
                    $voucher = \App\Models\Voucher::find($data['voucher_id'] ?? null);
                    if (!$voucher) {
                        return 'Are you sure you want to submit this liquidation?';
                    }

                    $original  = (float) $voucher->amount;
                    $deduction = (float) ($data['prior_deduction'] ?? 0);
                    $netTarget = max(0, $original - $deduction);
                    $spent     = (float) ($data['amount_spent'] ?? 0);
                    $returned  = (float) ($data['amount_returned'] ?? 0);
                    $diff      = round(($spent + $returned) - $netTarget, 2);

                    if ($diff < -0.01) {
                        return "You have entered a shortage of AED " . number_format(abs($diff), 2) . ". Saving this will automatically draft a Petty Cash Voucher (PCV) to reimburse the employee. Are you sure you want to proceed?";
                    } elseif ($diff > 0.01) {
                        return "You have entered an excess of AED " . number_format($diff, 2) . ". Saving this will automatically draft a Receipt Voucher (RV) to record the returned cash. Are you sure you want to proceed?";
                    }

                    return "The liquidation amounts balance perfectly. No extra settlement vouchers will be generated. Are you sure you want to proceed?";
                })
                ->modalSubmitActionLabel('Yes, Submit')
                ->action(function () {
                    $this->create();
                }),
            ...(static::canCreateAnother() ? [$this->getCreateAnotherFormAction()] : []),
            $this->getCancelFormAction(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove placeholder keys (prefixed with _) before saving
        unset($data['_voucher_amount'], $data['_voucher_payee'], $data['_voucher_number']);

        $voucher = \App\Models\Voucher::find($data['voucher_id']);
        if (!$voucher) return $data;

        $original  = (float) $voucher->amount;
        $deduction = (float) ($data['prior_deduction'] ?? 0);
        $netTarget = max(0, $original - $deduction);
        $spent     = (float) ($data['amount_spent'] ?? 0);
        $returned  = (float) ($data['amount_returned'] ?? 0);
        $accounted = round($spent + $returned, 2);
        $diff      = round($accounted - $netTarget, 2);

        $data['amount_short']    = max(0, -$diff);
        $data['prior_deduction'] = $deduction;
        $data['liquidated_at']   = now();
        $data['liquidated_by']   = $data['liquidated_by'] ?? auth()->id();

        // Determine status
        if (abs($diff) <= 0.01) {
            $data['status'] = 'complete';
        } elseif ($diff < 0) {
            $data['status'] = 'short';
        } else {
            $data['status'] = 'excess';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        $voucher = $record->voucher;

        if (!$voucher) return;

        // Sync the parent voucher's liquidation_status
        $newLiqStatus = match ($record->status) {
            'complete', 'excess' => 'liquidated',
            'short'              => 'pending', // still pending until fully settled
            default              => 'pending',
        };

        $voucher->updateQuietly(['liquidation_status' => $newLiqStatus]);

        // Auto-log change received on the physical denominations tracker
        // If a liquidation is filed, it means the cashier/accountant has verified exactly what was received.
        $denom = $voucher->denominations()->first();
        if ($denom && !$denom->is_change_received) {
            $denom->updateQuietly(['is_change_received' => true]);
        }

        // Also log an activity on the voucher
        activity()
            ->performedOn($voucher)
            ->causedBy(auth()->user())
            ->event('updated')
            ->log("Liquidation filed by {$record->custodian?->name}. Status: {$record->status}. Spent: AED " . number_format($record->amount_spent, 2) . " | Returned: AED " . number_format($record->amount_returned, 2));

        Notification::make()
            ->title('Liquidation successfully filed!')
            ->body("Voucher {$voucher->voucher_number} has been marked as {$record->status}.")
            ->success()
            ->send();
    }
}
