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
                ->form([
                    \Filament\Forms\Components\Placeholder::make('info')
                        ->label('Transaction Linker')
                        ->content(function (\App\Services\ItemMasterLinkerService $linker) {
                            $preview = $linker->preview(includeSmart: true);
                            $exact = $preview['exact_count'];
                            $smart = $preview['smart_count'];
                            $total = $preview['total_unassigned'];
                            return new \Illuminate\Support\HtmlString("
                                <div class='space-y-2 text-sm'>
                                    <p>Found <strong>{$total}</strong> unassigned expense items in the queue.</p>
                                    <ul class='list-disc pl-5 space-y-1 text-gray-600 dark:text-gray-300'>
                                        <li><span class='text-success-600 font-semibold'>{$exact} item(s)</span> match directly to Item Masterfile names.</li>
                                        <li><span class='text-primary-600 font-semibold'>{$smart} item(s)</span> match smart patterns (taxi routes, fuel receipts, barcodes).</li>
                                    </ul>
                                </div>
                            ");
                        }),
                    \Filament\Forms\Components\Toggle::make('include_smart')
                        ->label('Include smart pattern matching')
                        ->helperText('Also links slight variations such as taxi routes, fuel receipts, barcodes, and common spelling.')
                        ->default(true),
                ])
                ->modalHeading('Auto-link Account Codes to Unassigned Transactions')
                ->modalDescription('Scan unassigned transaction lines and link account codes based on your Item Masterfile.')
                ->modalSubmitActionLabel('Auto-link Now')
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
