<?php

namespace App\Filament\Vouchers\Clusters\Settings\Resources;

use App\Filament\Vouchers\Clusters\Settings;
use App\Filament\Vouchers\Clusters\Settings\Resources\LedgerBranchResource\Pages;
use App\Filament\Vouchers\Clusters\Settings\Resources\LedgerBranchResource\RelationManagers;
use App\Models\LedgerBranch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LedgerBranchResource extends Resource
{
    protected static ?string $model = LedgerBranch::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $cluster = Settings::class;

    protected static ?string $navigationLabel = 'Ledger Branches';

    protected static ?int $navigationSort = 11;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_settings') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Branch Details')->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->placeholder('e.g. ETC, TG, SB MAIN'),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vouchers_count')
                    ->counts('voucherItems')
                    ->label('Total Vouchers')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount('voucherItems')->orderBy('voucher_items_count', $direction);
                    }),
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
            'index' => Pages\ListLedgerBranches::route('/'),
            'create' => Pages\CreateLedgerBranch::route('/create'),
            'edit' => Pages\EditLedgerBranch::route('/{record}/edit'),
        ];
    }
}
