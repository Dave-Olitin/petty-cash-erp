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
                Tables\Actions\CreateAction::make()
                    ->label('Record Replenishment')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->prefix('AED'),
                        Forms\Components\DatePicker::make('date')
                            ->required()
                            ->default(now()),
                        Forms\Components\TextInput::make('reference')
                            ->required()
                            ->label('Reference (e.g. Bank Transfer Ref, Cheque No)')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('remarks')
                            ->columnSpanFull(),
                        Forms\Components\Hidden::make('created_by')
                            ->default(fn () => auth()->id()),
                    ])
                    ->successNotificationTitle('Replenishment recorded successfully'),
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
                            ->required()
                            ->label('Reference (e.g. Bank Transfer Ref, Cheque No)')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('remarks')
                            ->columnSpanFull(),
                    ])
                    ->successNotificationTitle('Replenishment updated successfully'),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
