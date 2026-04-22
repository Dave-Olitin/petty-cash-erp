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
    protected static ?string $cluster = \App\Filament\Clusters\Settings::class;
    protected static ?int $navigationSort = 2;
    protected static \Filament\Pages\SubNavigationPosition $subNavigationPosition = \Filament\Pages\SubNavigationPosition::Top;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Profile Information')
                        ->description('Basic account details and credentials.')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                                
                            Forms\Components\TextInput::make('email')
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                                
                            Forms\Components\TextInput::make('password')
                                ->password()
                                ->required(fn (string $context): bool => $context === 'create')
                                ->dehydrated(fn ($state) => filled($state)),
                        ])->columns(2),
                ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Petty Cash ERP Access')
                        ->description('Assign the user to a branch to grant them Petty Cash access.')
                        ->schema([
                            Forms\Components\Select::make('branch_id')
                                ->relationship('branch', 'name')
                                ->label('Assigned Branch')
                                ->placeholder('Head Office (Super Admin)')
                                ->searchable()
                                ->preload(),
                        ]),

                    Forms\Components\Section::make('Voucher System Access')
                        ->description('Assign roles to grant access to the separate Vouchers software.')
                        ->schema([
                            Forms\Components\CheckboxList::make('roles')
                                ->relationship('roles', 'name')
                                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name}" . ($record->description ? " - {$record->description}" : ''))
                                ->columns(1),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Petty Cash Branch')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state ? 'gray' : 'success') // Gray for branch, Green for HQ
                    ->formatStateUsing(fn ($state) => $state ?? 'HQ / Super Admin')
                    ->searchable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Voucher Roles')
                    ->badge()
                    ->separator(',')
                    ->color(fn (string $state): string => match ($state) {
                        'Accountant' => 'warning',
                        'Approver' => 'success',
                        'Requester' => 'info',
                        default => 'gray',
                    })
                    ->searchable(),
                    
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
    return auth()->user()->can('manage_settings');
}
}
