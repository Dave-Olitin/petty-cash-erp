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
            ->query(FloatReplenishment::query()->with('creator'))
            ->heading('Head Office Float Replenishments')
            ->description('Manage deposits and transfers into the main petty cash float.')
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('aed', true)
                    ->sortable(),
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
                                'Amount (AED)' => $r->amount,
                                'Recorded By' => $r->creator?->name,
                                'Remarks' => $r->remarks,
                                'Created At' => $r->created_at->format('Y-m-d H:i:s'),
                                'Attachments' => collect($r->attachment_paths ?? [])->map(fn($p) => url('storage/' . implode('/', array_map('rawurlencode', explode('/', $p)))))->join(', '),
                            ];
                        })->toArray();
                        
                        array_unshift($exportData, ['Date', 'Reference', 'Amount (AED)', 'Recorded By', 'Remarks', 'Created At', 'Attachments']);

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
                        Forms\Components\Textarea::make('remarks')
                            ->columnSpanFull(),
                    ])
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
