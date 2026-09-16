<?php

namespace App\Filament\Vouchers\Widgets;

use App\Models\FloatReplenishment;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class FloatReplenishmentsTable extends BaseWidget
{
    protected static ?int $sort = 5;
    
    // Make the widget take up the full width of the dashboard
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->isHeadOffice() || auth()->user()->hasRole('Accountant');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(FloatReplenishment::query()->with(['creator', 'voucher']))
            ->heading('Head Office Float Replenishments')
            ->description('Manage deposits and transfers into the main petty cash float.')
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference')
                    ->searchable(),
                Tables\Columns\TextColumn::make('bank_reference')
                    ->label('Bank Ref')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('aed', true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('voucher.voucher_number')
                    ->label('Linked Voucher')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Recorded By')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_all')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->button()
                    ->action(function ($livewire) {
                        $records = $livewire->getFilteredTableQuery()->get();
                        $exportData = $records->map(function ($r) {
                            return [
                                'Date' => \Carbon\Carbon::parse($r->date)->format('Y-m-d'),
                                'Reference' => $r->reference,
                                'Bank Reference' => $r->bank_reference,
                                'Amount (AED)' => $r->amount,
                                'Linked Voucher' => $r->voucher?->voucher_number,
                                'Recorded By' => $r->creator?->name,
                                'Remarks' => $r->remarks,
                                'Created At' => $r->created_at->format('Y-m-d H:i:s'),
                                'Attachments' => collect($r->attachment_paths ?? [])->map(fn($p) => url('storage/' . implode('/', array_map('rawurlencode', explode('/', $p)))))->join(', '),
                            ];
                        })->toArray();
                        
                        array_unshift($exportData, ['Date', 'Reference', 'Bank Reference', 'Amount (AED)', 'Linked Voucher', 'Recorded By', 'Remarks', 'Created At', 'Attachments']);

                        return response()->streamDownload(function () use ($exportData) {
                            $handle = fopen('php://output', 'w');
                            foreach ($exportData as $row) {
                                fputcsv($handle, $row);
                            }
                            fclose($handle);
                        }, 'float_replenishments_all_' . now()->format('Ymd_His') . '.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->prefix('AED'),
                        Forms\Components\DatePicker::make('date')
                            ->required()
                            ->default(now()),
                        Forms\Components\TextInput::make('reference')
                            ->label('Reference (Auto-Generated)')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Will be generated upon save'),
                        Forms\Components\Select::make('voucher_id')
                            ->label('Link to Payment Voucher')
                            ->relationship('voucher', 'voucher_number', function ($query) {
                                return $query->where('type', 'payment')->where('status', 'paid');
                            })
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Voucher $record) => 
                                "{$record->voucher_number} — AED " . number_format((float) $record->amount, 2) . 
                                ($record->cheque_no ? " (Cheque: {$record->cheque_no})" : '') .
                                ($record->bank ? " [{$record->bank}]" : '')
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if (!$state) return;
                                $voucher = \App\Models\Voucher::find($state);
                                if (!$voucher) return;

                                $cheque = $voucher->cheque_no;
                                $bank = $voucher->bank;
                                if (empty($cheque) && !empty($voucher->multiple_payments)) {
                                    $first = $voucher->multiple_payments[0] ?? [];
                                    $cheque = $first['cheque_no'] ?? null;
                                    if (empty($bank)) {
                                        $bank = $first['bank'] ?? null;
                                    }
                                }
                                if ($cheque && !$get('bank_reference')) {
                                    $set('bank_reference', $cheque);
                                }
                                if ($bank && !$get('account_code')) {
                                    $set('account_code', $bank);
                                }
                            })
                            ->nullable(),
                        Forms\Components\TextInput::make('partial_amount')
                            ->label('Partial Amount (if applicable)')
                            ->numeric()
                            ->prefix('AED')
                            ->nullable(),
                        Forms\Components\Select::make('account_code')
                            ->label('Reference (Account Code)')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\AccountCode::where('is_active', true)
                                    ->where(function ($query) use ($search) {
                                        $query->where('code', 'like', "%{$search}%")
                                            ->orWhere('name', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($ac) => [$ac->code => "{$ac->code} — {$ac->name}"])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(fn (?string $value) => $value
                                ? (($ac = \App\Models\AccountCode::where('code', $value)->first())
                                    ? "{$ac->code} — {$ac->name}"
                                    : $value)
                                : null
                            )
                            ->nullable(),
                        Forms\Components\TextInput::make('bank_reference')
                            ->label('Bank Reference')
                            ->placeholder('Cheque # / Bank Ref #')
                            ->maxLength(100)
                            ->nullable(),
                        Forms\Components\Textarea::make('remarks')
                            ->columnSpanFull(),
                    ])
                    ->after(function (\App\Models\FloatReplenishment $record) {
                        if ($record->voucher_id) {
                            $linkedVoucher = \App\Models\Voucher::find($record->voucher_id);
                            if ($linkedVoucher) {
                                $updates = [];
                                if ($record->bank_reference && $linkedVoucher->cheque_no !== $record->bank_reference) {
                                    $updates['cheque_no'] = $record->bank_reference;
                                }
                                if ($record->account_code && $linkedVoucher->bank !== $record->account_code) {
                                    $updates['bank'] = $record->account_code;
                                }
                                if (!empty($updates)) {
                                    $linkedVoucher->update($updates);
                                }
                            }
                        }
                    })
                    ->successNotificationTitle('Replenishment updated successfully'),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('update_attachments')
                    ->label('Manage Attachments')
                    ->icon('heroicon-o-paper-clip')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Add/update receipts for this replenishment')
                    ->fillForm(fn (FloatReplenishment $record): array => [
                        'attachment_paths' => $record->attachment_paths,
                        'remarks'          => $record->remarks,
                    ])
                    ->form([
                        Forms\Components\FileUpload::make('attachment_paths')
                            ->label('Upload Receipts / Bank Transfers')
                            ->multiple()
                            ->directory('replenishment-attachments')
                            ->maxFiles(5)
                            ->maxSize(10240)
                            ->openable()
                            ->downloadable()
                            ->panelLayout('grid'),
                        Forms\Components\Textarea::make('remarks')
                            ->label('Remarks')
                            ->rows(3),
                    ])
                    ->action(function (array $data, FloatReplenishment $record): void {
                        $record->update([
                            'attachment_paths' => $data['attachment_paths'] ?? null,
                            'remarks'          => $data['remarks'] ?? null,
                        ]);
                        \Filament\Notifications\Notification::make()->title('Attachments and remarks securely updated')->success()->send();
                    }),
                Tables\Actions\Action::make('print')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->iconButton()
                    ->url(fn (FloatReplenishment $record) => route('replenishment.pdf', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export_custom')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $exportData = $records->map(function ($r) {
                                return [
                                    'Date' => \Carbon\Carbon::parse($r->date)->format('Y-m-d'),
                                    'Reference' => $r->reference,
                                    'Amount (AED)' => $r->amount,
                                    'Recorded By' => $r->creator?->name,
                                    'Remarks' => $r->remarks,
                                    'Created At' => $r->created_at->format('Y-m-d H:i:s'),
                                    'Attachments' => collect($r->attachment_paths ?? [])->map(fn($p) => url('storage/' . implode('/', array_map('rawurlencode', explode('/', $p)))))->join(', '),
                                ];
                            })->toArray();
                            
                            // Insert header row
                            array_unshift($exportData, ['Date', 'Reference', 'Amount (AED)', 'Recorded By', 'Remarks', 'Created At', 'Attachments']);

                            return response()->streamDownload(function () use ($exportData) {
                                $handle = fopen('php://output', 'w');
                                foreach ($exportData as $row) {
                                    fputcsv($handle, $row);
                                }
                                fclose($handle);
                            }, 'float_replenishments_' . now()->format('Ymd_His') . '.csv', [
                                'Content-Type' => 'text/csv',
                            ]);
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
