<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $cluster = \App\Filament\Clusters\Settings::class;
    protected static ?string $navigationLabel = 'Transaction Categories';
    protected static ?string $modelLabel = 'Transaction Category';
    protected static ?string $pluralModelLabel = 'Transaction Categories';
    protected static ?int $navigationSort = 4;
    protected static \Filament\Pages\SubNavigationPosition $subNavigationPosition = \Filament\Pages\SubNavigationPosition::Top;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\Select::make('type')
                    ->options([
                        'expense' => 'Expense',
                        'replenishment' => 'Replenishment',
                    ])
                    ->required()
                    ->default('expense'),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->label('Active'),

                Forms\Components\Select::make('account_code_id')
                    ->label('Chart of Account')
                    ->relationship('accountCode', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} – {$record->name}")
                    ->searchable(['code', 'name'])
                    ->preload()
                    ->placeholder('Not assigned')
                    ->helperText('Link this category to a GL account code for reporting purposes.')
                    ->nullable(),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'expense'       => 'warning',
                        'replenishment' => 'success',
                        default         => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('accountCode.code')
                    ->label('Account Code')
                    ->badge()
                    ->color('primary')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Category $record, Tables\Actions\DeleteAction $action) {
                        if ($record->transactionItems()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot Delete Category')
                                ->body("'{$record->name}' is used by existing transaction items. Deactivate it instead.")
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
                            $inUse = $records->filter(fn (Category $c) => $c->transactionItems()->exists());
                            if ($inUse->isNotEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Some Categories Cannot Be Deleted')
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->branch_id === null;
    }
}
