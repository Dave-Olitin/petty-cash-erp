<?php

namespace App\Filament\Vouchers\Resources\LiquidationResource\Pages;

use App\Filament\Vouchers\Resources\LiquidationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewLiquidation extends ViewRecord
{
    protected static string $resource = LiquidationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => in_array($this->getRecord()->status, ['pending', 'short'])),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $record  = $this->getRecord();
        $voucher = $record->voucher;

        return $infolist->schema([
            Infolists\Components\Section::make('Voucher Reference')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('voucher.voucher_number')->label('Voucher No.'),
                        Infolists\Components\TextEntry::make('voucher.payee')->label('Employee / Payee'),
                        Infolists\Components\TextEntry::make('voucher.amount')->label('Original Amount')->money('AED'),
                    ]),
                ]),

            Infolists\Components\Section::make('Liquidation Settlement')
                ->schema([
                    Infolists\Components\Grid::make(4)->schema([
                        Infolists\Components\TextEntry::make('amount_spent')->label('Amount Spent')->money('AED'),
                        Infolists\Components\TextEntry::make('amount_returned')->label('Cash Returned')->money('AED'),
                        Infolists\Components\TextEntry::make('amount_short')->label('Short / Unaccounted')->money('AED')
                            ->color(fn ($state) => (float)$state > 0 ? 'danger' : 'success'),
                        Infolists\Components\TextEntry::make('status')->badge()
                            ->color(fn ($state) => match($state) {
                                'complete' => 'success',
                                'short'    => 'danger',
                                'excess'   => 'info',
                                default    => 'warning',
                            }),
                    ]),
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('due_date')->label('Deadline')->date('d M Y'),
                        Infolists\Components\TextEntry::make('liquidated_at')->label('Filed At')->dateTime('d M Y h:i A'),
                        Infolists\Components\TextEntry::make('custodian.name')->label('Filed By'),
                    ]),
                    Infolists\Components\TextEntry::make('remarks')->label('Remarks')->columnSpanFull(),
                ]),
        ]);
    }
}
