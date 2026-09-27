<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    return response()->streamDownload(function () {
                        $file = fopen('php://output', 'w');

                        // UTF-8 BOM for Excel compatibility
                        fputs($file, "\xEF\xBB\xBF");

                        // Headers
                        fputcsv($file, [
                            'Supplier Name',
                            'TRN',
                            'Phone',
                            'Email',
                            'Address',
                            'Status',
                            'Total Transactions',
                            'Created At',
                        ]);

                        $query = $this->getFilteredTableQuery()
                            ->withCount('transactions')
                            ->orderBy('name', 'asc');

                        $query->chunk(200, function ($suppliers) use ($file) {
                            foreach ($suppliers as $supplier) {
                                fputcsv($file, [
                                    $supplier->name,
                                    $supplier->trn ?: '',
                                    $supplier->phone ?: '',
                                    $supplier->email ?: '',
                                    $supplier->address ?: '',
                                    $supplier->is_active ? 'Active' : 'Inactive',
                                    $supplier->transactions_count ?? 0,
                                    $supplier->created_at ? $supplier->created_at->format('Y-m-d H:i') : '',
                                ]);
                            }
                        });

                        fclose($file);
                    }, 'suppliers_' . now()->format('Y-m-d_H-i') . '.csv');
                }),

            \Filament\Actions\ImportAction::make()
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->importer(\App\Filament\Imports\SupplierImporter::class)
                ->csvDelimiter(',')
                ->color('success'),

            Actions\CreateAction::make(),
        ];
    }
}
