<?php

namespace App\Filament\Vouchers\Resources\VoucherResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Form;

class PurchaseEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'purchaseEntries';

    protected static ?string $title = 'Linked Purchase Entries';

    protected static ?string $icon = 'heroicon-o-document-text';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entry_no')
                    ->label('Entry No.')
                    ->searchable()
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold)
                    ->url(fn ($record) => \App\Filament\Vouchers\Resources\PurchaseEntryResource::getUrl('view', ['record' => $record])),

                Tables\Columns\TextColumn::make('taxRegistration.name')
                    ->label('Supplier')
                    ->default(fn ($record) => $record->supplier_name ?? '—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('supplier_invoice_number')
                    ->label('INV #')
                    ->default('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('lpo_number')
                    ->label('PO #')
                    ->default('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->default('—')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description),

                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('d M Y'),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Amount')
                    ->money('AED')
                    ->getStateUsing(fn ($record) => $record->grand_total ?? $record->total_amount ?? 0)
                    ->alignRight()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
            ])
            ->defaultSort('date', 'desc')
            ->paginated(false)
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn ($record) => \App\Filament\Vouchers\Resources\PurchaseEntryResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([]);
    }
}
