<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\SupplierResource\Pages;
use App\Models\TaxRegistration;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupplierResource extends Resource
{
    protected static ?string $model = TaxRegistration::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Accounting';

    protected static ?string $modelLabel = 'Supplier Details';
    protected static ?string $pluralModelLabel = 'Supplier Details';
    protected static ?string $navigationLabel = 'Supplier Details';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Super Admin']) || auth()->user()?->can('purchase_entry.view');
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    // Eagerly aggregate AP totals directly in the main query so balance columns are sortable
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withSum(
                ['purchaseEntries as total_billed' => fn ($q) => $q->where('entry_type', '!=', 'return')],
                'grand_total'
            )
            ->withSum('purchaseEntries as total_paid', 'amount_paid')
            ->addSelect([
                'net_balance' => \App\Models\PurchaseEntry::selectRaw('COALESCE(SUM(CASE WHEN entry_type = "return" THEN -1 * COALESCE(balance_due, grand_total - amount_paid, 0) ELSE COALESCE(balance_due, grand_total - amount_paid, 0) END), 0)')
                    ->whereColumn('tax_registration_id', 'tax_registrations.id')
            ])
            ->withCount(
                ['purchaseEntries as open_invoices' => fn ($q) => $q->where('payment_status', '!=', 'paid')]
            );
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
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('row_number')
                    ->label('#')
                    ->rowIndex(),
                Tables\Columns\TextColumn::make('name')
                    ->label('SUPPLIER NAME')
                    ->searchable()
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                Tables\Columns\TextColumn::make('trn')
                    ->label('TRN')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('supplier_code')
                    ->label('CODE')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('payment_terms')
                    ->label('PAYMENT TERMS')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('total_billed')
                    ->label('TOTAL BILLED')
                    ->formatStateUsing(fn ($state) => $state ? 'AED ' . number_format((float) $state, 2) : '—')
                    ->sortable()
                    ->alignRight()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('total_paid')
                    ->label('PAID')
                    ->formatStateUsing(fn ($state) => $state ? 'AED ' . number_format((float) $state, 2) : '—')
                    ->sortable()
                    ->alignRight()
                    ->color('success'),
                Tables\Columns\TextColumn::make('net_balance')
                    ->label('BALANCE DUE')
                    ->formatStateUsing(fn ($state) => 'AED ' . number_format((float) $state, 2))
                    ->sortable()
                    ->alignRight()
                    ->color(fn ($state) => ((float) $state > 0.01) ? 'danger' : 'success')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
                Tables\Columns\TextColumn::make('open_invoices')
                    ->label('OPEN BILLS')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state . ' open' : '✓ Clear')
                    ->alignCenter(),
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
                Tables\Filters\Filter::make('has_balance')
                    ->label('Has Outstanding Balance')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->having('net_balance', '>', 0)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->iconButton(),
                Tables\Actions\EditAction::make()->iconButton(),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->paginated([10, 25, 50, 100])
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
            'view'  => Pages\ViewSupplier::route('/{record}'),
        ];
    }
}
