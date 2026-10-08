<?php

namespace App\Filament\Resources\ItemMasterResource\Pages;

use App\Filament\Resources\ItemMasterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListItemMasters extends ListRecords
{
    protected static string $resource = ItemMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('auto_create_from_transactions')
                ->label('Auto-add from Transactions')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Auto-add Items from Transaction History')
                ->modalDescription('Scan classified transaction items and automatically add any new items into the Item Masterfile.')
                ->action(function (\App\Services\ItemMasterLinkerService $linker) {
                    $result = $linker->autoCreateMastersFromTransactions();
                    \Filament\Notifications\Notification::make()
                        ->title('Item Masterfile Updated')
                        ->body("Added {$result['created']} new master item(s) from transaction history.")
                        ->success()
                        ->send();
                }),
            Actions\Action::make('auto_link_accounts')
                ->label('Auto-link Transactions')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->modalWidth(\Filament\Support\Enums\MaxWidth::FiveExtraLarge)
                ->modalHeading('Auto-link Account Codes to Unassigned Transactions')
                ->modalDescription('Preview detected matches below before applying. Verified Chart of Accounts will be linked directly to transactions.')
                ->modalSubmitActionLabel('Auto-link Now')
                ->form([
                    \Filament\Forms\Components\Toggle::make('include_smart')
                        ->label('Include smart pattern matching')
                        ->helperText('Also links slight variations such as taxi routes, fuel receipts, barcodes, and common spelling.')
                        ->default(true)
                        ->live(),
                    \Filament\Forms\Components\Placeholder::make('preview')
                        ->hiddenLabel()
                        ->content(function (\App\Services\ItemMasterLinkerService $linker, callable $get) {
                            $includeSmart = (bool) ($get('include_smart') ?? true);
                            $preview = $linker->preview(includeSmart: $includeSmart);
                            return view('filament.components.item-linker-preview', [
                                'preview'      => $preview,
                                'includeSmart' => $includeSmart,
                            ]);
                        }),
                ])
                ->action(function (array $data, \App\Services\ItemMasterLinkerService $linker) {
                    $includeSmart = (bool) ($data['include_smart'] ?? true);
                    $result = $linker->link(includeSmart: $includeSmart);

                    \Filament\Notifications\Notification::make()
                        ->title('Transactions Linked')
                        ->body("Successfully linked {$result['total_linked']} item(s) across {$result['affected_transactions']} transaction(s).")
                        ->success()
                        ->send();
                }),
        ];
    }
}
