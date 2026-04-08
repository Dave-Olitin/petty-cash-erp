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
                            'ID', 'Date', 'Type', 'Amount', 'Total VAT', 'Payee', 'Supplier', 'TRN', 
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
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => \Filament\Resources\Components\Tab::make('All Transactions'),
        ];

        if (auth()->user()->isHeadOffice()) {
            $tabs['unassigned'] = \Filament\Resources\Components\Tab::make('Unassigned')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('items', fn ($q) => $q->whereNull('account_code_id')))
                ->badge(TransactionResource::getEloquentQuery()->whereHas('items', fn ($q) => $q->whereNull('account_code_id'))->count())
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
