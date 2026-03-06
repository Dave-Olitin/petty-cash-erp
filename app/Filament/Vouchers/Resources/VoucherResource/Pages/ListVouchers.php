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
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();

        $actionCount = \App\Models\Voucher::actionRequired($user)->count();
        $draftCount = \App\Models\Voucher::where('status', 'draft')->where('user_id', $user->id)->count();

        $tabs = [
            'action_required' => \Filament\Resources\Components\Tab::make('Action Required')
                ->modifyQueryUsing(fn ($query) => $query->actionRequired($user))
                ->badge($actionCount)
                ->badgeColor($actionCount > 0 ? 'danger' : 'gray')
                ->icon('heroicon-m-exclamation-circle'),
                
            'all' => \Filament\Resources\Components\Tab::make('All')
                ->icon('heroicon-m-bars-4'),
        ];

        if ($draftCount > 0) {
            $tabs['draft'] = \Filament\Resources\Components\Tab::make('My Drafts')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'draft')->where('user_id', $user->id))
                ->badge($draftCount)
                ->badgeColor('gray')
                ->icon('heroicon-m-pencil-square');
        }

        $tabs['in_progress'] = \Filament\Resources\Components\Tab::make('Processing & Completed')
            ->modifyQueryUsing(fn ($query) => $query->whereIn('status', [
                'pending_checker', 
                'pending_approver', 
                'approved', 
                'paid'
            ]))
            ->icon('heroicon-m-check-badge');

        return $tabs;
    }
}
