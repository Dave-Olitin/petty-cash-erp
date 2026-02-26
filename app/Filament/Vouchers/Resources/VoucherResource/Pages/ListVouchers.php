<?php

namespace App\Filament\Vouchers\Resources\VoucherResource\Pages;

use App\Filament\Vouchers\Resources\VoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVouchers extends ListRecords
{
    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \EightyNine\ExcelImport\ExcelImportAction::make()
                ->color('primary')
                ->slideOver(),
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();

        // Determine what statuses constitute "Action Required" for the current user
        $actionRequiredStatuses = [];
        if ($user->hasRole('Accountant')) {
            $actionRequiredStatuses[] = 'pending_checker';
        }
        if ($user->hasRole('Approver') || $user->hasRole('Super Admin')) {
            $actionRequiredStatuses[] = 'pending_approver';
        }
        // Basic users (and everyone else) need to take action on their own rejected vouchers
        $actionRequiredStatuses[] = 'rejected';

        // Prepare base query clauses for 'Action Required' tab
        $actionRequiredQuery = function ($query) use ($user, $actionRequiredStatuses) {
            return $query->where(function ($q) use ($user, $actionRequiredStatuses) {
                if (in_array('pending_checker', $actionRequiredStatuses)) {
                    $q->orWhere('status', 'pending_checker');
                }
                if (in_array('pending_approver', $actionRequiredStatuses)) {
                    $q->orWhere('status', 'pending_approver');
                }
                // Always include rejected vouchers if the user is the author
                $q->orWhere(function ($sub) use ($user) {
                    $sub->where('status', 'rejected')->where('user_id', $user->id);
                });
            });
        };

        $draftCount = \App\Models\Voucher::where('status', 'draft')->where('user_id', $user->id)->count();
        $actionCount = \App\Models\Voucher::tap($actionRequiredQuery)->count();

        $tabs = [
            'all' => \Filament\Resources\Components\Tab::make('All'),
        ];

        if ($draftCount > 0) {
            $tabs['draft'] = \Filament\Resources\Components\Tab::make('My Drafts')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'draft')->where('user_id', $user->id))
                ->badge($draftCount)
                ->badgeColor('gray');
        }

        $tabs['action_required'] = \Filament\Resources\Components\Tab::make('Action Required')
            ->modifyQueryUsing($actionRequiredQuery)
            ->badge($actionCount)
            ->badgeColor($actionCount > 0 ? 'danger' : 'gray');

        $tabs['in_progress'] = \Filament\Resources\Components\Tab::make('Processing & Completed')
            ->modifyQueryUsing(fn ($query) => $query->whereIn('status', [
                'pending_checker', 
                'pending_approver', 
                'approved', 
                'paid'
            ]));

        return $tabs;
    }
}
