<?php

namespace App\Filament\Vouchers\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentActivityWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 100;

    public static function canView(): bool
    {
        return auth()->user()->can('access_vouchers_panel');
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $query = \Spatie\Activitylog\Models\Activity::query()
            ->where('subject_type', \App\Models\Voucher::class)
            ->latest();

        if ($user && !$user->isHeadOffice() && !$user->hasAnyRole(['Accountant', 'Approver', 'Admin', 'Super Admin'])) {
            $query->whereHasMorph('subject', [\App\Models\Voucher::class], function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        return $table
            ->heading('Recent Voucher Activity')
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('subject.voucher_number')
                    ->label('Voucher'),
            ])
            ->paginated(true);
    }
}
