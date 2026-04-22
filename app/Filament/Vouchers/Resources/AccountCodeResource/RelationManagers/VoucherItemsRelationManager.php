<?php

namespace App\Filament\Vouchers\Resources\AccountCodeResource\RelationManagers;

use App\Filament\Vouchers\Resources\VoucherResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VoucherItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'voucherItems';

    protected static ?string $title = 'Voucher Transactions';

    protected static ?string $icon = 'heroicon-o-document-text';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('voucher'))
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('voucher.voucher_number')
                    ->label('Voucher #')
                    ->badge()
                    ->color('gray')
                    ->url(fn ($record): ?string => $record->voucher_id
                        ? VoucherResource::getUrl('view', ['record' => $record->voucher_id])
                        : null
                    )
                    ->openUrlInNewTab()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('voucher.created_at')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('voucher.payee')
                    ->label('Payee')
                    ->searchable()
                    ->limit(25)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(35)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('debit')
                    ->label('DR (AED)')
                    ->money('AED')
                    ->color('success')
                    ->sortable()
                    ->placeholder('—')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('credit')
                    ->label('CR (AED)')
                    ->money('AED')
                    ->color('danger')
                    ->sortable()
                    ->placeholder('—')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('voucher.status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state
                        ? str($state)->replace('_', ' ')->title()->toString()
                        : '—'
                    )
                    ->color(fn (?string $state) => match ($state) {
                        'paid'                             => 'success',
                        'approved'                         => 'info',
                        'pending_approver', 'pending_checker' => 'warning',
                        'rejected'                         => 'danger',
                        'draft'                            => 'gray',
                        default                            => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('voucher_status')
                    ->label('Voucher Status')
                    ->relationship('voucher', 'status')
                    ->options([
                        'draft'            => 'Draft',
                        'pending_checker'  => 'Pending Checker',
                        'pending_approver' => 'Pending Approver',
                        'approved'         => 'Approved',
                        'paid'             => 'Paid',
                        'rejected'         => 'Rejected',
                    ]),

                Tables\Filters\Filter::make('debit_only')
                    ->label('DR only')
                    ->query(fn (Builder $query) => $query->where('debit', '>', 0))
                    ->toggle(),

                Tables\Filters\Filter::make('credit_only')
                    ->label('CR only')
                    ->query(fn (Builder $query) => $query->where('credit', '>', 0))
                    ->toggle(),
            ])
            ->heading('Voucher Transactions')
            ->description('All voucher line items posted to this account code.');
    }
}
