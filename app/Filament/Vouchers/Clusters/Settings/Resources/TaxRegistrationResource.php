<?php

namespace App\Filament\Vouchers\Clusters\Settings\Resources;

use App\Filament\Vouchers\Clusters\Settings;
use App\Filament\Vouchers\Clusters\Settings\Resources\TaxRegistrationResource\Pages;
use App\Models\TaxRegistration;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TaxRegistrationResource extends Resource
{
    protected static ?string $model = TaxRegistration::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $cluster = Settings::class;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('manage_settings') ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_settings') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('trn')
                    ->label('Tax Registration Number (TRN)')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
                Forms\Components\TextInput::make('name')
                    ->label('Vendor / Company Name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('trn')
                    ->label('TRN')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Vendor / Company Name')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTaxRegistrations::route('/'),
        ];
    }
}
