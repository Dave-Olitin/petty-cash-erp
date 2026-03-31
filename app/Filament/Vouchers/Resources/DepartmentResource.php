<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\DepartmentResource\Pages;
use App\Models\Department;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $cluster = \App\Filament\Vouchers\Clusters\Settings::class;
    protected static ?string $navigationLabel = 'Departments';
    protected static ?string $modelLabel = 'Department';
    protected static ?string $pluralModelLabel = 'Departments';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Department Name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->placeholder('e.g. HR, Driver, PRO, Purchasing')
                    ->maxLength(100),

                Forms\Components\TextInput::make('code')
                    ->label('Short Code')
                    ->unique(ignoreRecord: true)
                    ->placeholder('e.g. HR, DRV, PRO')
                    ->maxLength(20),

                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Department $record, Tables\Actions\DeleteAction $action) {
                        // Check if any vouchers reference this dept name
                        if (\App\Models\Voucher::where('department', $record->name)->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot Delete Department')
                                ->body("'{$record->name}' is used by existing vouchers. Deactivate it instead.")
                                ->danger()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit'   => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('manage_settings');
    }
}
