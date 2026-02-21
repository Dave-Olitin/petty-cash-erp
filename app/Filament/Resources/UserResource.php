<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'System Settings';
    protected static ?int $navigationSort = 3;

public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\TextInput::make('name')
                ->required(),
            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('password')
                ->password()
                ->required(fn (string $context): bool => $context === 'create') // Only required when creating new
                ->dehydrated(fn ($state) => filled($state)), // Don't update if empty
                
            // THE MAGIC FIELD: Assign a user to a branch
            Forms\Components\Select::make('branch_id')
                ->relationship('branch', 'name')
                ->label('Assigned Branch')
                ->placeholder('Head Office (Super Admin)') // If empty, they are HQ
                ->searchable()
                ->preload(),
        ]);
}

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Assigned Branch')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state ? 'info' : 'success') // Blue for branch, Green for HQ (null)
                    ->formatStateUsing(fn ($state) => $state ?? 'Head Office (Admin)'),
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
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record) =>
                        $record->id !== 1 && $record->id !== auth()->id()
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records, Tables\Actions\DeleteBulkAction $action) {
                            // Remove protected users before bulk delete executes
                            $records->reject(fn (User $record) =>
                                $record->id === 1 || $record->id === auth()->id()
                            )->each->delete();
                            $action->cancel(); // We handled deletion ourselves
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
    public static function canViewAny(): bool
{
    return auth()->user()->branch_id === null;
}
}
