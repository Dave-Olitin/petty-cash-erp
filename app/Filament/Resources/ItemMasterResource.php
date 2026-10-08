<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemMasterResource\Pages;
use App\Models\ItemMaster;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemMasterResource extends Resource
{
    protected static ?string $model = ItemMaster::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';
    protected static ?string $cluster = \App\Filament\Clusters\Settings::class;
    protected static ?string $navigationLabel = 'Item Masterfile';
    protected static ?string $modelLabel = 'Master Item';
    protected static ?string $pluralModelLabel = 'Item Masterfile';
    protected static ?int $navigationSort = 7;
    protected static \Filament\Pages\SubNavigationPosition $subNavigationPosition = \Filament\Pages\SubNavigationPosition::Top;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Item Name / Description')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->placeholder('e.g. Fuel / Gasoline, Taxi Fare, Drinking Water')
                    ->maxLength(255),

                Forms\Components\Select::make('account_code_id')
                    ->label('Linked Chart of Account')
                    ->relationship('accountCode', 'name', fn (Builder $query) => $query->orderBy('code'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} – {$record->name}")
                    ->searchable(['code', 'name'])
                    ->preload()
                    ->required()
                    ->placeholder('Select GL account...')
                    ->helperText('Transactions selecting this item will automatically link this account code.'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Inactive items cannot be selected on new transaction lines.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('accountCode.code')
                    ->label('Account Code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('accountCode.name')
                    ->label('Account Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('transaction_items_count')
                    ->label('Usage (Items)')
                    ->counts('transactionItems')
                    ->badge()
                    ->color('success')
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
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (ItemMaster $record, Tables\Actions\DeleteAction $action) {
                        if ($record->transactionItems()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot Delete Master Item')
                                ->body("'{$record->name}' is linked to existing transactions. Deactivate it instead.")
                                ->danger()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records, Tables\Actions\DeleteBulkAction $action) {
                            $inUse = $records->filter(fn (ItemMaster $m) => $m->transactionItems()->exists());
                            if ($inUse->isNotEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Some Items Cannot Be Deleted')
                                    ->body('The following are in use: ' . $inUse->pluck('name')->join(', ') . '. Deactivate them instead.')
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
            'index'  => Pages\ListItemMasters::route('/'),
            'create' => Pages\CreateItemMaster::route('/create'),
            'edit'   => Pages\EditItemMaster::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->branch_id === null;
    }
}
