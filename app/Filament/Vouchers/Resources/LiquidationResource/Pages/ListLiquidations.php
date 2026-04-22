<?php

namespace App\Filament\Vouchers\Resources\LiquidationResource\Pages;

use App\Filament\Vouchers\Resources\LiquidationResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListLiquidations extends ListRecords
{
    protected static string $resource = LiquidationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()->label('File Liquidation'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(\App\Models\Liquidation::where('status', 'pending')->count())
                ->badgeColor('warning'),
            'overdue' => Tab::make('Overdue')
                ->modifyQueryUsing(fn (Builder $query) => $query->overdue())
                ->badge(\App\Models\Liquidation::overdue()->count())
                ->badgeColor('danger'),
            'complete' => Tab::make('Settled')
                ->modifyQueryUsing(fn (Builder $query) => $query->complete()),
            'short' => Tab::make('Short / Incomplete')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'short'))
                ->badge(\App\Models\Liquidation::where('status', 'short')->count())
                ->badgeColor('danger'),
        ];
    }
    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Vouchers\Widgets\LiquidationSummaryWidget::class,
            \App\Filament\Vouchers\Widgets\TopUnliquidatedTableWidget::class,
        ];
    }
}
