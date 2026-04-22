<?php

namespace App\Filament\Vouchers\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Denomination;

class DenominationsTableWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Denomination::query()->with('denominatable')->latest()
            )
            ->defaultPaginationPageOption(10)
            ->heading('Recent Cash Breakdown Logs')
            ->description('Live log of all cash denominations explicitly tracked (Replenishments and Voucher Disbursals)')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y h:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('denominatable_type')
                    ->label('Source')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->badge()
                    ->color(fn ($state) => str_contains($state, 'Voucher') ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('ref_number')
                    ->label('Ref / Number')
                    ->state(fn ($record) => $record->denominatable->reference ?? $record->denominatable->voucher_number ?? '-')
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder {
                        return $query->whereHasMorph('denominatable', '*', function ($q, $type) use ($search) {
                            if ($type === \App\Models\Voucher::class) {
                                $q->where('voucher_number', 'like', "%{$search}%");
                            } else {
                                $q->where('reference', 'like', "%{$search}%");
                            }
                        });
                    }),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Gross Amount Given')
                    ->money('AED'),
                Tables\Columns\TextColumn::make('change_given')
                    ->label('Change Given')
                    ->money('AED'),
                Tables\Columns\TextColumn::make('net')
                    ->label('Net Change')
                    ->state(fn ($record) => $record->total_amount - $record->change_given)
                    ->money('AED')
                    ->color('primary')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
                Tables\Columns\IconColumn::make('is_change_received')
                    ->label('Change RTN')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip('Has the exact change been physically returned yet?'),
                Tables\Columns\TextColumn::make('remarks')
                    ->limit(20)
                    ->tooltip(function (\Filament\Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return (strlen($state) > 20) ? $state : null;
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from'),
                        \Filament\Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn ($query, $date) => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn ($query, $date) => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                Tables\Filters\SelectFilter::make('denominatable_type')
                    ->label('Source')
                    ->options([
                        \App\Models\FloatReplenishment::class => 'Float Replenishment',
                        \App\Models\Voucher::class => 'Voucher',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_filtered')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($livewire) {
                        $records = $livewire->getFilteredTableQuery()->get();
                        $exportData = $records->map(function ($r) {
                            $source = str_contains($r->denominatable_type, 'Voucher') ? 'Voucher' : 'Replenishment';
                            $ref = $r->denominatable->reference ?? $r->denominatable->voucher_number ?? '-';
                            return [
                                $r->created_at->format('Y-m-d H:i:s'),
                                $source,
                                $ref,
                                number_format((float) $r->total_amount, 2, '.', ''),
                                number_format((float) $r->change_given, 2, '.', ''),
                                number_format((float) ($r->total_amount - $r->change_given), 2, '.', ''),
                                $r->is_change_received ? 'Yes' : 'No',
                                $r->remarks
                            ];
                        })->toArray();

                        array_unshift($exportData, ['Date', 'Source', 'Reference', 'Gross Amount', 'Change', 'Net Change', 'Change RTN', 'Remarks']);

                        return response()->streamDownload(function () use ($exportData) {
                            $file = fopen('php://output', 'w');
                            foreach ($exportData as $row) {
                                fputcsv($file, $row);
                            }
                            fclose($file);
                        }, 'denominations_breakdown_' . now()->format('Y-m-d_H-i') . '.csv');
                    })
            ])
            ->actions([
                Tables\Actions\Action::make('view_source')
                    ->label('View Source')
                    ->url(function (Denomination $record) {
                        return str_contains($record->denominatable_type, 'Voucher')
                            ? \App\Filament\Vouchers\Resources\VoucherResource::getUrl('view', ['record' => $record->denominatable_id])
                            : null;
                    })
                    ->visible(fn (Denomination $record) => str_contains($record->denominatable_type, 'Voucher')),
            ]);
    }
}
