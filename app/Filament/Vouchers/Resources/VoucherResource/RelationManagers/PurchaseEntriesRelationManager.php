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
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with('lines'))
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

                Tables\Columns\TextColumn::make('invoice_no')
                    ->label('INV #')
                    ->default('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('po_number')
                    ->label('PO #')
                    ->default('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->getStateUsing(fn ($record) => $record->lines->first()?->description ?? '—')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->lines->first()?->description ?? '—'),

                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('d M Y'),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Amount')
                    ->money('AED')
                    ->getStateUsing(fn ($record) => $record->isReturn() ? -(float)($record->grand_total ?? $record->total_amount ?? 0) : (float)($record->grand_total ?? $record->total_amount ?? 0))
                    ->alignRight()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->color(fn ($record) => $record->isReturn() ? 'warning' : null),
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
