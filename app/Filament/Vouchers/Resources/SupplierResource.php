<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\SupplierResource\Pages;
use App\Models\TaxRegistration;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = TaxRegistration::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    
    protected static ?string $navigationGroup = 'Accounting';
    
    protected static ?string $modelLabel = 'Supplier Entry';
    protected static ?string $pluralModelLabel = 'Supplier Entries';
    protected static ?string $navigationLabel = 'Supplier Entries';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Super Admin']) || auth()->user()?->can('purchase_entry.view');
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('trn')
                    ->label('TRN (Optional)')
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
                Forms\Components\TextInput::make('supplier_code')
                    ->label('Supplier Code')
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
                Forms\Components\TextInput::make('name')
                    ->label('Supplier Name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('payment_terms')
                    ->label('Payment Terms')
                    ->maxLength(255),
                Forms\Components\TextInput::make('contact_name')
                    ->label('Contact Name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label('Phone')
                    ->tel()
                    ->maxLength(50),
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('entity')
                    ->label('Entity')
                    ->maxLength(255),
                Forms\Components\DatePicker::make('started_date')
                    ->label('Started Date')
                    ->native(false)
                    ->displayFormat('d-m-Y'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Status (Active)')
                    ->default(true),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('row_number')
                    ->label('SL.NO')
                    ->rowIndex(),
                Tables\Columns\TextColumn::make('trn')
                    ->label('TRN')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('supplier_code')
                    ->label('SUPPLIER CODE')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('name')
                    ->label('SUPPLIER NAME')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_terms')
                    ->label('PAYMENT TERMS')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('contact_name')
                    ->label('CONTACT NAME')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('PHONE')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('email')
                    ->label('EMAIL')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('entity')
                    ->label('ENTITY')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('started_date')
                    ->label('STARTED DATE')
                    ->date('d-m-Y')
                    ->placeholder('-'),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('STATUS')
                    ->onColor('success')
                    ->offColor('danger'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Active Suppliers')
                    ->falseLabel('Inactive Suppliers'),
                Tables\Filters\SelectFilter::make('entity')
                    ->label('Entity')
                    ->options(function () {
                        return \App\Models\TaxRegistration::select('entity')
                            ->distinct()
                            ->whereNotNull('entity')
                            ->pluck('entity', 'entity')
                            ->toArray();
                    })
                    ->searchable(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSuppliers::route('/'),
        ];
    }
}
