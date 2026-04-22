<?php

namespace App\Filament\Vouchers\Resources\SupplierResource\Pages;

use App\Filament\Vouchers\Resources\SupplierResource;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    public function getMaxContentWidth(): \Filament\Support\Enums\MaxWidth|string|null
    {
        return \Filament\Support\Enums\MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Supplier Details')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Supplier Name')
                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                            ->size(\Filament\Infolists\Components\TextEntry\TextEntrySize::Large),
                        Infolists\Components\TextEntry::make('trn')
                            ->label('TRN')
                            ->placeholder('—')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('supplier_code')
                            ->label('Supplier Code')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('payment_terms')
                            ->label('Payment Terms')
                            ->placeholder('Not set'),
                        Infolists\Components\TextEntry::make('contact_name')
                            ->label('Contact Name')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('phone')
                            ->label('Phone')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('email')
                            ->label('Email')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('entity')
                            ->label('Entity')
                            ->placeholder('—'),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Status')
                            ->boolean(),
                    ]),

                Infolists\Components\Section::make('Accounts Payable Summary')
                    ->schema([
                        Infolists\Components\TextEntry::make('ap_summary_html')
                            ->label('')
                            ->html()
                            ->columnSpanFull()
                            ->getStateUsing(fn ($record) => view(
                                'filament.vouchers.supplier-ap-summary',
                                ['record' => $record->load('purchaseEntries')]
                            )->render()),
                    ]),

                Infolists\Components\Section::make('Purchase Entry Ledger')
                    ->schema([
                        Infolists\Components\TextEntry::make('ap_ledger_html')
                            ->label('')
                            ->html()
                            ->columnSpanFull()
                            ->getStateUsing(fn ($record) => view(
                                'filament.vouchers.supplier-ap-ledger',
                                ['record' => $record->load('purchaseEntries')]
                            )->render()),
                    ]),
            ]);
    }
}
