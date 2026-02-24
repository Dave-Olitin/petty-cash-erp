<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\FloatReplenishmentResource\Pages;
use App\Filament\Vouchers\Resources\FloatReplenishmentResource\RelationManagers;
use App\Models\FloatReplenishment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FloatReplenishmentResource extends Resource
{
    protected static ?string $model = FloatReplenishment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Head Office Float';
    protected static ?string $pluralModelLabel = 'Float Replenishments';

    public static function canAccess(): bool
    {
        return auth()->user()->isHeadOffice() || auth()->user()->hasRole('Accountant');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Replenishment Details')->schema([
                    Forms\Components\TextInput::make('amount')
                        ->required()
                        ->numeric()
                        ->prefix('AED'),
                    Forms\Components\DatePicker::make('date')
                        ->required()
                        ->default(now()),
                    Forms\Components\TextInput::make('reference')
                        ->required()
                        ->label('Reference (e.g. Bank Transfer Ref, Cheque No)')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('remarks')
                        ->columnSpanFull(),
                    Forms\Components\Hidden::make('created_by')
                        ->default(fn () => auth()->id()),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('aed', true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Recorded By')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListFloatReplenishments::route('/'),
            'create' => Pages\CreateFloatReplenishment::route('/create'),
            'edit' => Pages\EditFloatReplenishment::route('/{record}/edit'),
        ];
    }
}
