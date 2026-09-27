<?php

namespace App\Filament\Resources\ChartOfAccountResource\Pages;

use App\Filament\Imports\ChartOfAccountImporter;
use App\Filament\Resources\ChartOfAccountResource;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListChartOfAccounts extends ListRecords
{
    protected static string $resource = ChartOfAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export')
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
                            'Account Code',
                            'Account Name',
                            'Total Spend (AED)',
                            'Usage in Transactions (Items)',
                            'Linked Categories',
                            'Created At',
                        ]);

                        $query = $this->getFilteredTableQuery()
                            ->with(['categories'])
                            ->withCount('transactionItems')
                            ->withSum('transactionItems', 'total_price')
                            ->orderBy('code', 'asc');

                        $query->chunk(200, function ($accounts) use ($file) {
                            foreach ($accounts as $account) {
                                fputcsv($file, [
                                    $account->code,
                                    $account->name,
                                    number_format((float) ($account->transaction_items_sum_total_price ?? 0), 2),
                                    $account->transaction_items_count ?? 0,
                                    $account->categories->pluck('name')->join(', ') ?: 'None',
                                    $account->created_at ? $account->created_at->format('Y-m-d H:i') : '',
                                ]);
                            }
                        });

                        fclose($file);
                    }, 'chart_of_accounts_' . now()->format('Y-m-d_H-i') . '.csv');
                }),

            ImportAction::make()
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->importer(ChartOfAccountImporter::class)
                ->csvDelimiter(',')
                ->color('success'),

            \Filament\Actions\CreateAction::make(),
        ];
    }
}

