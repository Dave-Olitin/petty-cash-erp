<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\ApprovalWorkflowResource\Pages;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ApprovalWorkflowResource extends Resource
{
    protected static ?string $model = ApprovalWorkflow::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrows-up-down';
    protected static ?string $navigationLabel = 'Approval Chain';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $pluralModelLabel = 'Approval Chain';
    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('Admin');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole('Admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('Admin');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Approver Step')
                ->description('Define who approves at each step, in order.')
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label('Approver')
                        ->options(
                            User::role('Approver')->pluck('name', 'id')->toArray()
                        )
                        ->searchable()
                        ->required(),

                    Forms\Components\TextInput::make('step_order')
                        ->label('Step Order')
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->helperText('1 = first to approve, 2 = second, etc.'),

                    Forms\Components\TextInput::make('label')
                        ->label('Role Label')
                        ->placeholder('e.g. Finance Manager, CEO')
                        ->maxLength(100),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('step_order')
                    ->label('Step')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Approver')
                    ->searchable(),

                Tables\Columns\TextColumn::make('label')
                    ->label('Role Label')
                    ->default('—'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('step_order')
            ->reorderable('step_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No approval chain configured')
            ->emptyStateDescription('Add approvers in the order they should approve vouchers.')
            ->emptyStateIcon('heroicon-o-arrows-up-down');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageApprovalWorkflows::route('/'),
        ];
    }
}
