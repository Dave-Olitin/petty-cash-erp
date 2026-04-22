<?php

namespace App\Filament\Vouchers\Widgets;

use App\Models\Voucher;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopUnliquidatedTableWidget extends BaseWidget
{
    protected static ?int $sort = 2; // Position it below the Summary stat cards
    
    // Lives on the Liquidations list page, not the Overview dashboard
    protected static bool $isDiscovered = false;
    
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Voucher::query()
                    ->join('liquidations', 'vouchers.id', '=', 'liquidations.voucher_id')
                    ->where('liquidations.status', 'pending')
                    ->selectRaw('MIN(vouchers.id) as id, vouchers.payee, GROUP_CONCAT(vouchers.voucher_number SEPARATOR ", ") as voucher_numbers, COUNT(vouchers.id) as pending_advances, SUM(vouchers.amount - COALESCE(liquidations.prior_deduction, 0)) as total_outstanding, MIN(liquidations.due_date) as next_due_date')
                    ->groupBy('vouchers.payee')
                    ->orderByDesc('total_outstanding')
                    ->limit(5)
            )
            ->heading('⚠️ Top 5 Employees with Outstanding Advances')
            ->description('These individuals have the highest unliquidated petty cash balances and should be reminded to submit their receipts.')
            ->columns([
                Tables\Columns\TextColumn::make('payee')
                    ->label('Employee / Payee')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('voucher_numbers')
                    ->label('References')
                    ->wrap()
                    ->fontFamily('mono')
                    ->color('gray')
                    ->size('xs'),
                Tables\Columns\TextColumn::make('total_outstanding')
                    ->label('Total Unliquidated')
                    ->money('AED')
                    ->color('danger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('pending_advances')
                    ->label('Pending Vouchers')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('next_due_date')
                    ->label('Next Deadline')
                    ->date('d M Y')
                    ->color(fn ($record) => $record->next_due_date && $record->next_due_date < now() ? 'danger' : 'gray')
            ])
            ->paginated(false);
    }
}
