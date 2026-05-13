<?php

namespace App\Filament\Vouchers\Resources\AccountCodeResource\Widgets;

use App\Models\AccountCode;
use App\Models\JournalEntryLine;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class AccountLedgerTableWidget extends BaseWidget
{
    public ?AccountCode $record = null;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                JournalEntryLine::query()
                    ->where('account_code_id', $this->record->id)
                    ->with(['journalEntry', 'journalEntry.voucher'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('journalEntry.date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('journalEntry.entry_number')
                    ->label('Ref #')
                    ->searchable()
                    ->url(fn ($record) => $record->journalEntry ? \App\Filament\Vouchers\Resources\JournalEntryResource::getUrl('view', ['record' => $record->journalEntry]) : null),
                Tables\Columns\TextColumn::make('journalEntry.voucher.voucher_number')
                    ->label('Voucher #')
                    ->searchable()
                    ->url(fn ($record) => $record->journalEntry?->voucher ? \App\Filament\Vouchers\Resources\VoucherResource::getUrl('view', ['record' => $record->journalEntry->voucher]) : null),
                Tables\Columns\TextColumn::make('branch')
                    ->label('Branch'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->wrap(),
                Tables\Columns\TextColumn::make('debit')
                    ->label('Debit')
                    ->money('AED')
                    ->color('success')
                    ->alignRight(),
                Tables\Columns\TextColumn::make('credit')
                    ->label('Credit')
                    ->money('AED')
                    ->color('danger')
                    ->alignRight(),
            ])
            ->filters([
                Tables\Filters\Filter::make('date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from'),
                        \Filament\Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereHas('journalEntry', fn($q) => $q->whereDate('date', '>=', $date)),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereHas('journalEntry', fn($q) => $q->whereDate('date', '<=', $date)),
                            );
                    }),
            ])
            ->defaultSort('journalEntry.date', 'desc');
    }
}
