<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

use Filament\Widgets\Concerns\InteractsWithPageFilters;

class LatestTransactions extends BaseWidget
{
    use InteractsWithPageFilters;

    protected int | string | array $columnSpan = 'full';
    
    protected static bool $isLazy = false;

    protected static ?int $sort = 10;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()
                    ->when($this->filters['startDate'] ?? null, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
                    ->when($this->filters['endDate'] ?? null, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
                    ->when($this->filters['branch_id'] ?? null, fn($q, $id) => $q->where('branch_id', $id))
                    ->when(!auth()->user()->isHeadOffice(), function ($query) {
                        return $query->where('branch_id', auth()->user()->branch_id);
                    })
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y h:i A')
                    ->sortable()
                    ->label('Date & Time'),
                
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->hidden(fn () => !auth()->user()->isHeadOffice()), // Only show branch name to HQ

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'EXPENSE' => 'danger',
                        'REPLENISHMENT' => 'success',
                    }),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('amount')
                    ->money('AED')
                    ->extraAttributes(['class' => 'privacy-mask']),
                
                Tables\Columns\TextColumn::make('payee')
                    ->limit(20),
                
                Tables\Columns\TextColumn::make('description')
                    ->limit(30)
                    ->visibleFrom('md'),
            ])
            ->actions([
                // Minimal actions for the dashboard widget
            ]);
    }

    public static function canView(): bool
    {
        // Both can view, but content differs.
        return true;
    }
}
