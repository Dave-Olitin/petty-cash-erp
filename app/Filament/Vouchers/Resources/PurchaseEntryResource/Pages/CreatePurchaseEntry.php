<?php

namespace App\Filament\Vouchers\Resources\PurchaseEntryResource\Pages;

use App\Filament\Vouchers\Resources\PurchaseEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;

class CreatePurchaseEntry extends CreateRecord
{
    protected static string $resource = PurchaseEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['lines']);
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back')
                ->url(fn () => url()->previous() !== url()->current() ? url()->previous() : static::$resource::getUrl('index'))
                ->color('gray')
                ->icon('heroicon-m-arrow-left'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('create_purchase_entry')
                ->label('Create')
                ->requiresConfirmation()
                ->modalHeading(function () {
                    $raw = is_array($this->form->getRawState()) ? $this->form->getRawState() : ($this->data ?? []);
                    $isReturn = ($raw['entry_type'] ?? 'purchase') === 'return';
                    return $isReturn ? 'Confirm Purchase Return & GL Impact' : 'Confirm Purchase Bill & GL Impact';
                })
                ->modalDescription('Review the line items and General Ledger posting impact before creating this transaction:')
                ->modalWidth(MaxWidth::FourExtraLarge)
                ->modalContent(function () {
                    $raw = is_array($this->form->getRawState()) ? $this->form->getRawState() : ($this->data ?? []);
                    return PurchaseEntryResource::renderGlPreviewHtml($raw);
                })
                ->modalSubmitActionLabel('Confirm & Create')
                ->keyBindings(['mod+s'])
                ->action(fn () => $this->create()),
            ...(static::canCreateAnother() ? [$this->getCreateAnotherFormAction()] : []),
            $this->getCancelFormAction(),
        ];
    }

    protected function getCreateAnotherFormAction(): Actions\Action
    {
        return Actions\Action::make('createAnother')
            ->label(__('filament-panels::resources/pages/create-record.form.actions.create_another.label'))
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(function () {
                $raw = is_array($this->form->getRawState()) ? $this->form->getRawState() : ($this->data ?? []);
                $isReturn = ($raw['entry_type'] ?? 'purchase') === 'return';
                return $isReturn ? 'Confirm Purchase Return & GL Impact' : 'Confirm Purchase Bill & GL Impact';
            })
            ->modalDescription('Review the line items and General Ledger posting impact before creating this transaction:')
            ->modalWidth(MaxWidth::FourExtraLarge)
            ->modalContent(function () {
                $raw = is_array($this->form->getRawState()) ? $this->form->getRawState() : ($this->data ?? []);
                return PurchaseEntryResource::renderGlPreviewHtml($raw);
            })
            ->modalSubmitActionLabel('Confirm & Create Another')
            ->keyBindings(['mod+shift+s'])
            ->action(fn () => $this->create(another: true));
    }
}
