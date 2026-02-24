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
        return [
            'all' => \Filament\Resources\Components\Tab::make('All'),
            'draft' => \Filament\Resources\Components\Tab::make('Draft')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'draft')),
            'pending_checker' => \Filament\Resources\Components\Tab::make('Pending Checker')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'pending_checker')),
            'pending_approver' => \Filament\Resources\Components\Tab::make('Pending Approver')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'pending_approver')),
            'approved' => \Filament\Resources\Components\Tab::make('Approved')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'approved')),
            'rejected' => \Filament\Resources\Components\Tab::make('Rejected')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'rejected')),
            'paid' => \Filament\Resources\Components\Tab::make('Paid')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'paid')),
        ];
    }
}
