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
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withSum('debitItems', 'debit')
                ->withSum('creditItems', 'credit')
            )
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('voucher_items_count')
                    ->counts('voucherItems')
                    ->label('Total Vouchers')
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('debit_items_sum_debit')
                    ->label('Total DR')
                    ->money('AED')
                    ->color('success')
                    ->sortable(query: fn (Builder $query, string $direction) =>
                        $query->orderBy('debit_items_sum_debit', $direction)
                    )
                    ->default(0)
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('credit_items_sum_credit')
                    ->label('Total CR')
                    ->money('AED')
                    ->color('danger')
                    ->sortable(query: fn (Builder $query, string $direction) =>
                        $query->orderBy('credit_items_sum_credit', $direction)
                    )
                    ->default(0)
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                \Filament\Tables\Actions\ImportAction::make()
                    ->importer(\App\Filament\Imports\AccountCodeImporter::class),
            ])
            ->recordUrl(fn ($record) => AccountCodeResource::getUrl('view', ['record' => $record]))
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            RelationManagers\VoucherItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAccountCodes::route('/'),
            'create' => Pages\CreateAccountCode::route('/create'),
            'view'   => Pages\ViewAccountCode::route('/{record}'),
            'edit'   => Pages\EditAccountCode::route('/{record}/edit'),
        ];
    }
}
