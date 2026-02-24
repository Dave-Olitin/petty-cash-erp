<?php

namespace App\Filament\Vouchers\Resources\ApprovalWorkflowResource\Pages;

use App\Filament\Vouchers\Resources\ApprovalWorkflowResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageApprovalWorkflows extends ManageRecords
{
    protected static string $resource = ApprovalWorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
