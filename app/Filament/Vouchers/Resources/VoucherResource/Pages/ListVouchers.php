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

        // Avoid multiple heavy count queries. Only count actionable small subsets.
        $actionCount = \App\Models\Voucher::actionRequired($user)->count();
        $draftCount = \App\Models\Voucher::where('status', 'draft')->where('user_id', $user->id)->count();

        $tabs = [
            'action_required' => \Filament\Resources\Components\Tab::make('Needs My Action')
                ->icon('heroicon-m-exclamation-circle')
                ->modifyQueryUsing(fn ($query) => $query->actionRequired($user))
                ->badge($actionCount)
                ->badgeColor($actionCount > 0 ? 'danger' : 'gray'),
        ];

        if ($draftCount > 0) {
            $tabs['draft'] = \Filament\Resources\Components\Tab::make('My Drafts')
                ->icon('heroicon-m-pencil-square')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'draft')->where('user_id', $user->id))
                ->badge($draftCount)
                ->badgeColor('gray');
        }

        $tabs['in_progress'] = \Filament\Resources\Components\Tab::make('In Progress')
            ->icon('heroicon-m-arrow-path')
            ->modifyQueryUsing(fn ($query) => $query->whereIn('status', ['pending_checker', 'pending_approver']));

        $tabs['completed'] = \Filament\Resources\Components\Tab::make('Processed & Paid')
            ->icon('heroicon-m-check-badge')
            ->modifyQueryUsing(fn ($query) => $query->whereIn('status', ['approved', 'paid']));

        $tabs['all'] = \Filament\Resources\Components\Tab::make('All Records')
            ->icon('heroicon-m-queue-list');

        return $tabs;
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Vouchers\Widgets\RecentActivityWidget::class,
        ];
    }
}
