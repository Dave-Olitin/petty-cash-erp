<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\RoleResource\Pages;
use App\Filament\Vouchers\Resources\RoleResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $cluster = \App\Filament\Vouchers\Clusters\Settings::class;
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Role Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Role Name')
                            ->placeholder('e.g. PRO Officer, Senior Accountant')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('description')
                            ->label('Role Description')
                            ->placeholder('Summary of duties and access level')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Assigned Permissions')
                    ->description('Select the permissions granted to this role. You can search by module (e.g. "pro", "voucher") or bulk select.')
                    ->schema([
                        Forms\Components\CheckboxList::make('permissions')
                            ->relationship('permissions', 'name', modifyQueryUsing: fn (Builder $query) => $query->orderBy('name'))
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name}" . ($record->description ? " — {$record->description}" : ''))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Role Name')
                    ->searchable()
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('permissions.name')
                    ->label('Permissions')
                    ->badge()
                    ->separator(',')
                    ->limitList(5)
                    ->expandableLimitedList(),
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('Admin');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole('Admin');
    }
}
