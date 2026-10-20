<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\PermissionResource\Pages;
use App\Filament\Vouchers\Resources\PermissionResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $cluster = \App\Filament\Vouchers\Clusters\Settings::class;
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('description')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Permission Name')
                    ->searchable()
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('module')
                    ->label('Module')
                    ->state(function (Permission $record): string {
                        $name = $record->name;
                        if (str_starts_with($name, 'pro_') || str_starts_with($name, 'ledger_branch.')) return 'PRO Tracking';
                        if (str_starts_with($name, 'voucher.')) return 'Vouchers';
                        if (str_starts_with($name, 'journal_entry.') || str_starts_with($name, 'purchase_entry.') || str_starts_with($name, 'gl_report.')) return 'Accounting';
                        return 'System & Settings';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PRO Tracking' => 'info',
                        'Vouchers' => 'success',
                        'Accounting' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(60)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('module')
                    ->label('Filter by Module')
                    ->options([
                        'pro'        => 'PRO Tracking',
                        'voucher'    => 'Vouchers',
                        'accounting' => 'Accounting',
                        'system'     => 'System & Settings',
                    ])
                    ->query(function ($query, array $data) {
                        $val = $data['value'] ?? null;
                        if (! $val) return;
                        match ($val) {
                            'pro' => $query->where(function ($q) {
                                $q->where('name', 'like', 'pro_%')
                                  ->orWhere('name', 'like', 'ledger_branch.%');
                            }),
                            'voucher' => $query->where('name', 'like', 'voucher.%'),
                            'accounting' => $query->where(function ($q) {
                                $q->where('name', 'like', 'journal_entry.%')
                                  ->orWhere('name', 'like', 'purchase_entry.%')
                                  ->orWhere('name', 'like', 'gl_report.%');
                            }),
                            'system' => $query->where(function ($q) {
                                $q->where('name', 'not like', 'pro_%')
                                  ->where('name', 'not like', 'ledger_branch.%')
                                  ->where('name', 'not like', 'voucher.%')
                                  ->where('name', 'not like', 'journal_entry.%')
                                  ->where('name', 'not like', 'purchase_entry.%')
                                  ->where('name', 'not like', 'gl_report.%');
                            }),
                            default => null,
                        };
                    }),
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
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
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
