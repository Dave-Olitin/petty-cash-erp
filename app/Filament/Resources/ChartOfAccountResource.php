<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChartOfAccountResource\Pages;
use App\Models\AccountCode;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChartOfAccountResource extends Resource
{
    protected static ?string $model = AccountCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $cluster = \App\Filament\Clusters\Settings::class;
    protected static ?string $navigationLabel = 'Chart of Accounts';
    protected static ?string $modelLabel = 'Chart of Account';
    protected static ?string $pluralModelLabel = 'Chart of Accounts';
    protected static ?int $navigationSort = 5;
    protected static \Filament\Pages\SubNavigationPosition $subNavigationPosition = \Filament\Pages\SubNavigationPosition::Top;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Account Code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->placeholder('e.g. 5100')
                    ->maxLength(50),

                Forms\Components\TextInput::make('name')
                    ->label('Account Name')
                    ->required()
                    ->placeholder('e.g. Cash Replenishment')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Account Code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Account Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('transaction_items_sum_total_price')
                    ->label('Total Spend')
                    ->money('AED')
                    ->sortable()
                    ->placeholder('AED 0.00'),

                Tables\Columns\TextColumn::make('transaction_items_count')
                    ->label('Transactions (Items)')
                    ->counts('transactionItems')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('categories_count')
                    ->label('Linked Categories')
                    ->counts('categories')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code', 'asc')
            ->filters([
                Tables\Filters\Filter::make('has_transactions')
                    ->label('With Transactions Only')
                    ->query(fn (Builder $query) => $query->has('transactionItems')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (AccountCode $record, Tables\Actions\DeleteAction $action) {
                        if ($record->categories()->exists() || $record->transactionItems()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot Delete Account')
                                ->body("'{$record->code} - {$record->name}' is in use by categories or transactions. Remove the assignment first.")
                                ->danger()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export_selected')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            return response()->streamDownload(function () use ($records) {
                                $file = fopen('php://output', 'w');
                                fputs($file, "\xEF\xBB\xBF");

                                fputcsv($file, [
                                    'Account Code',
                                    'Account Name',
                                    'Total Spend (AED)',
                                    'Usage in Transactions (Items)',
                                    'Linked Categories',
                                    'Created At',
                                ]);

                                $records->load(['categories'])->loadCount('transactionItems');

                                foreach ($records as $account) {
                                    $totalSpend = $account->transactionItems()->sum('total_price');
                                    fputcsv($file, [
                                        $account->code,
                                        $account->name,
                                        number_format((float) $totalSpend, 2),
                                        $account->transaction_items_count ?? 0,
                                        $account->categories->pluck('name')->join(', ') ?: 'None',
                                        $account->created_at ? $account->created_at->format('Y-m-d H:i') : '',
                                    ]);
                                }

                                fclose($file);
                            }, 'chart_of_accounts_selected_' . now()->format('Y-m-d_H-i') . '.csv');
                        }),
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records, Tables\Actions\DeleteBulkAction $action) {
                            $inUse = $records->filter(
                                fn (AccountCode $c) => $c->categories()->exists() || $c->transactionItems()->exists()
                            );
                            if ($inUse->isNotEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Some Accounts Cannot Be Deleted')
                                    ->body('In use: ' . $inUse->map(fn($c) => $c->code)->join(', '))
                                    ->danger()
                                    ->send();
                                $action->cancel();
                            }
                        }),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withSum('transactionItems', 'total_price');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListChartOfAccounts::route('/'),
            'create' => Pages\CreateChartOfAccount::route('/create'),
            'edit'   => Pages\EditChartOfAccount::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->branch_id === null;
    }
}
