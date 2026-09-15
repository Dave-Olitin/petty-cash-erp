<?php

namespace App\Filament\Vouchers\Resources\BankReconciliationResource\Pages;

use App\Filament\Vouchers\Resources\BankReconciliationResource;
use App\Models\AccountCode;
use Filament\Resources\Pages\CreateRecord;

class CreateBankReconciliation extends CreateRecord
{
    protected static string $resource = BankReconciliationResource::class;

    /**
     * Pre-fill account_code from query string when coming from the
     * overview widget "Reconcile" button (e.g. ?account_code=1010-02).
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $accountCode = request()->query('account_code');
        if ($accountCode) {
            $data['account_code'] = $accountCode;
            $ac = AccountCode::where('code', $accountCode)->first();
            if ($ac) {
                $data['account_name'] = $ac->name;
            }
        }
        return $data;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        // Cache account name from the code
        if (empty($data['account_name']) && !empty($data['account_code'])) {
            $ac = AccountCode::where('code', $data['account_code'])->first();
            $data['account_name'] = $ac?->name ?? $data['account_code'];
        }

        return $data;
    }

    /**
     * NOTE: No auto-import of payment vouchers as reconciliation lines.
     *
     * Bank reconciliation lines = bank STATEMENT entries (what the bank sent).
     * Payment vouchers           = your BOOKS side (already in the system).
     *
     * These are two separate sides. You enter statement lines manually,
     * then match them to vouchers. The workspace (Edit page) shows both
     * sides clearly so there is no confusion or duplicate data.
     */
    protected function getRedirectUrl(): string
    {
        // Go straight to the reconciliation workspace after creation
        return BankReconciliationResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
