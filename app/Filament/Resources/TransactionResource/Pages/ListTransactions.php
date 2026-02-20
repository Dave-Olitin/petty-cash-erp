<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

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
                            
                            // Get Unique Categories from Items
                            $categoryNames = $record->items->map(fn($item) => $item->category?->name)->filter()->unique()->join(', ');

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
                                $categoryNames ?: 'N/A', // Use item categories
                                $record->status,
                                $record->user ? $record->user->name : 'Unknown',
                                $record->receipt_path ? route('transaction.receipt', $record) : '',
                            ];
                            
                            // Removed Accounting Remarks Check
                            
                            return $row;
                        };

                        // 3. Query - Use the Current Filtered Query!
                        $query = $this->getFilteredTableQuery(); // Respects Tabs & Search
                        $query->with(['branch', 'items.category', 'user']); // items.category fixes N+1
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
        return [
            'pending' => \Filament\Resources\Components\Tab::make('Pending')
                ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('status', 'pending'))
                ->badge(TransactionResource::getEloquentQuery()->where('status', 'pending')->count())
                ->badgeColor('warning'),
            'approved' => \Filament\Resources\Components\Tab::make('Approved')
                ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('status', 'approved'))
                ->badgeColor('success'),
            'rejected' => \Filament\Resources\Components\Tab::make('Rejected')
                ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('status', 'rejected'))
                ->badgeColor('danger'),
            'all' => \Filament\Resources\Components\Tab::make('All Transactions'),
        ];
    }
}
