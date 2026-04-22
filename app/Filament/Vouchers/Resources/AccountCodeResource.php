<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\AccountCodeResource\Pages;
use App\Filament\Vouchers\Resources\AccountCodeResource\RelationManagers;
use App\Models\AccountCode;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AccountCodeResource extends Resource
{
    protected static ?string $model = AccountCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';
    protected static ?string $navigationGroup = 'Accounting';
    protected static ?int $navigationSort = 4;

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
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('voucher_count')
                    ->counts('voucherItems')
                    ->label('Total Vouchers')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount('voucherItems')->orderBy('voucher_items_count', $direction);
                    }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                \Filament\Tables\Actions\ImportAction::make()
                    ->importer(\App\Filament\Imports\AccountCodeImporter::class),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountCodes::route('/'),
            'create' => Pages\CreateAccountCode::route('/create'),
            'edit' => Pages\EditAccountCode::route('/{record}/edit'),
        ];
    }
}
