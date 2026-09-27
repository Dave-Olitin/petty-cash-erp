<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $cluster = \App\Filament\Clusters\Settings::class;
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Suppliers';
    protected static ?string $modelLabel = 'Supplier';
    protected static ?string $pluralModelLabel = 'Suppliers';
    protected static \Filament\Pages\SubNavigationPosition $subNavigationPosition = \Filament\Pages\SubNavigationPosition::Top;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Supplier Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Supplier Name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('e.g., Al Boom Gas Distribution'),
                        Forms\Components\TextInput::make('trn')
                            ->label('TRN (Tax Registration Number)')
                            ->maxLength(255)
                            ->placeholder('e.g., 100007637000003'),
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('address')
                            ->label('Address / Location')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive suppliers will be hidden from the transaction dropdown.'),
                    ])->columns(['default' => 1, 'sm' => 2]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Supplier Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('trn')
                    ->label('TRN')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('transactions_count')
                    ->counts('transactions')
                    ->label('Transactions')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                Tables\Filters\Filter::make('has_trn')
                    ->label('Has TRN')
                    ->query(fn ($query) => $query->whereNotNull('trn')->where('trn', '!=', '')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Supplier $record, Tables\Actions\DeleteAction $action) {
                        if ($record->transactions()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot Delete Supplier')
                                ->body("'{$record->name}' is linked to {$record->transactions()->count()} transaction(s). Please deactivate it instead.")
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
                        ->color('info')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            return response()->streamDownload(function () use ($records) {
                                $file = fopen('php://output', 'w');
                                fputs($file, "\xEF\xBB\xBF");
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
                                $records->loadCount('transactions');
                                foreach ($records as $supplier) {
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
                                fclose($file);
                            }, 'suppliers_selected_' . now()->format('Y-m-d_H-i') . '.csv');
                        }),
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records, Tables\Actions\DeleteBulkAction $action) {
                            $inUse = $records->filter(fn (Supplier $s) => $s->transactions()->exists());
                            if ($inUse->isNotEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Some Suppliers Cannot Be Deleted')
                                    ->body('The following suppliers are linked to transactions: ' . $inUse->pluck('name')->take(5)->join(', ') . (count($inUse) > 5 ? ' and more.' : '.') . ' Please deactivate them instead.')
                                    ->danger()
                                    ->send();
                                $action->cancel();
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit'   => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        // Only Head Office (branch_id is NULL) can manage settings
        return auth()->user()->branch_id === null;
    }
}
