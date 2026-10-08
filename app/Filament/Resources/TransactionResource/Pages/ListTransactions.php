<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTransactions extends ListRecords
{
    #[\Livewire\Attributes\Url]
    public ?string $activeTab = null;

    protected static string $resource = TransactionResource::class;

    public function mount(): void
    {
        if (request()->has('activeTab')) {
            $this->activeTab = request()->query('activeTab');
        }
        
        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('export_view')
                ->label('Export Current View')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return response()->streamDownload(function () {
                        $file = fopen('php://output', 'w');
                        
                        // 1. Define Headers
                        $headers = [
                            'ID', 'Date', 'Type', 'Fund', 'Amount', 'Total VAT', 'Payee', 'Supplier', 'TRN', 
                            'Reference #', 'Description', 'Items', 'Branch', 'Category', 
                            'Status', 'Created By', 'Receipt URL'
                        ];
                        
                        // Removed Accounting Remarks Header Check
                        
                        fputcsv($file, $headers);
                        
                        // 2. Export Helper Function
                        $exportRow = function ($record) {
                            $itemsSummary = $record->items->map(fn($item) => "{$item->name} (x{$item->quantity})")->join(', ');
                            $totalVat = $record->items->sum('vat') + $record->vat; // Include Global VAT
                            
                            // Get Unique Account Codes from Items
                            $accountCodes = $record->items->map(fn($item) => $item->accountCode ? "{$item->accountCode->code} – {$item->accountCode->name}" : null)->filter()->unique()->join(', ');


                            $row = [
                                $record->id,
                                $record->created_at->format('Y-m-d H:i'),
                                $record->type,
                                $record->fund_type ?: '—',
                                (float) $record->amount,
                                (float) $totalVat,
                                $record->payee,
                                $record->supplier,
                                $record->trn,
                                $record->reference_number,
                                $record->description,
                                $itemsSummary,
                                $record->branch ? $record->branch->name : 'Head Office',
                                $accountCodes ?: 'N/A', // Account codes from items
                                $record->status,
                                $record->user ? $record->user->name : 'Unknown',
                                $record->receipt_path ? route('transaction.receipt', $record) : '',
                            ];
                            
                            // Removed Accounting Remarks Check
                            
                            return $row;
                        };

                        // 3. Query - Use the Current Filtered Query!
                        $query = $this->getFilteredTableQuery(); // Respects Tabs & Search
                        $query->with(['branch', 'items.accountCode', 'user']); // items.accountCode fixes N+1
                        $query->latest(); // Ensure order

                        $query->chunk(100, function ($transactions) use ($file, $exportRow) {
                            foreach ($transactions as $record) {
                                fputcsv($file, $exportRow($record));
                            }
                        });
                        
                        fclose($file);
                    }, 'transactions_view_' . now()->format('Y-m-d_H-i') . '.csv');
                }),
            Actions\Action::make('auto_link_accounts')
                ->label('Auto-link Accounts')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->visible(fn () => auth()->user()->isHeadOffice())
                ->form([
                    \Filament\Forms\Components\Placeholder::make('info')
                        ->label('Item Masterfile Auto-Linker')
                        ->content(function (\App\Services\ItemMasterLinkerService $linker) {
                            $preview = $linker->preview(includeSmart: true);
                            $exact = $preview['exact_count'];
                            $smart = $preview['smart_count'];
                            $total = $preview['total_unassigned'];
                            return new \Illuminate\Support\HtmlString("
                                <div class='space-y-2 text-sm'>
                                    <p>Found <strong>{$total}</strong> unassigned expense items in the queue.</p>
                                    <ul class='list-disc pl-5 space-y-1 text-gray-600 dark:text-gray-300'>
                                        <li><span class='text-success-600 font-semibold'>{$exact} item(s)</span> match directly to the Item Masterfile.</li>
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
                ->modalHeading('Auto-link Account Codes from Item Masterfile')
                ->modalDescription('Automatically link Chart of Account codes to existing transactions based on the item text.')
                ->modalSubmitActionLabel('Auto-link Now')
                ->action(function (array $data, \App\Services\ItemMasterLinkerService $linker) {
                    $includeSmart = (bool) ($data['include_smart'] ?? true);
                    $result = $linker->link(includeSmart: $includeSmart);

                    \Filament\Notifications\Notification::make()
                        ->title('Account Codes Linked')
                        ->body("Successfully linked {$result['total_linked']} item(s) across {$result['affected_transactions']} transaction(s).")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => \Filament\Resources\Components\Tab::make('All Transactions'),
        ];

        if (auth()->user()->isHeadOffice()) {
            $tabs['unassigned'] = \Filament\Resources\Components\Tab::make('Unassigned')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'EXPENSE')->whereHas('items', fn ($q) => $q->whereNull('account_code_id')))
                ->badge(TransactionResource::getEloquentQuery()->where('type', 'EXPENSE')->whereHas('items', fn ($q) => $q->whereNull('account_code_id'))->count())
                ->badgeColor('danger');
        }

        $tabs['pending'] = \Filament\Resources\Components\Tab::make('Pending')
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('status', 'pending'))
            ->badge(TransactionResource::getEloquentQuery()->where('status', 'pending')->count())
            ->badgeColor('warning');

        $tabs['approved'] = \Filament\Resources\Components\Tab::make('Approved')
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('status', 'approved'))
            ->badge(TransactionResource::getEloquentQuery()->where('status', 'approved')->count())
            ->badgeColor('success');

        $tabs['rejected'] = \Filament\Resources\Components\Tab::make('Rejected')
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('status', 'rejected'))
            ->badge(TransactionResource::getEloquentQuery()->where('status', 'rejected')->count())
            ->badgeColor('danger');

        return $tabs;
    }
}
