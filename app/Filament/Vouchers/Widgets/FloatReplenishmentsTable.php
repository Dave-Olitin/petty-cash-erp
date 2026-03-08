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
            ->query(FloatReplenishment::query())
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
                // Moved to Dashboard header
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
                Tables\Actions\Action::make('print')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->iconButton()
                    ->url(fn (FloatReplenishment $record) => route('replenishment.pdf', $record))
                    ->openUrlInNewTab(),
            ]);
    }
}
