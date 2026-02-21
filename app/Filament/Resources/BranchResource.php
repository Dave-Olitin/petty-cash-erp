<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchResource\Pages;
use App\Filament\Resources\BranchResource\RelationManagers;
use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'System Settings';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('location')
                ->maxLength(255),
            Forms\Components\TextInput::make('max_limit')
                ->label('Max Balance (Float Limit)')
                ->helperText('The maximum cash float allowed for this branch.')
                ->prefix('AED')
                ->numeric()
                ->default(500.00),
            Forms\Components\TextInput::make('transaction_limit')
                ->label('Max Amount Per Transaction')
                ->helperText('Optional. Limits how much can be spent in a single transaction.')
                ->prefix('AED')
                ->numeric(),
            Forms\Components\Toggle::make('is_active')
                ->label('Branch Active Status')
                ->default(true),
        ]);
}

public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('location'),
            Tables\Columns\TextColumn::make('current_balance')
                ->money('AED')
                ->label('Cash on Hand')
                ->sortable()
                ->color(fn (string $state): string => $state < 100 ? 'danger' : 'success'), 
                // ERP Trick: Turn RED if balance is below $100
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])
        ->filters([
            Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
    public static function canViewAny(): bool
{
    // Only show this page if the user is Head Office (branch_id is NULL)
    return auth()->user()->branch_id === null;
}
}
