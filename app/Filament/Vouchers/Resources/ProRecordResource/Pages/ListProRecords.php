<?php

namespace App\Filament\Vouchers\Resources\ProRecordResource\Pages;

use App\Filament\Vouchers\Resources\ProRecordResource;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProRecords extends ListRecords
{
    protected static string $resource = ProRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncFines')
                ->label('Sync Fines')
                ->color('gray')
                ->outlined()
                ->icon('heroicon-m-arrow-path')
                ->action(function () {
                    \Illuminate\Support\Facades\Artisan::call('pro:sync-fines-and-alerts');
                    \Filament\Notifications\Notification::make()
                        ->title('Fines Recalculated')
                        ->body('Overstay penalties and grace periods synchronized.')
                        ->success()
                        ->send();
                }),

            Actions\ImportAction::make()
                ->label('Import Records')
                ->importer(\App\Filament\Imports\ProRecordImporter::class)
                ->color('gray')
                ->icon('heroicon-m-arrow-up-tray'),

            Actions\CreateAction::make()
                ->label('New Record')
                ->icon('heroicon-m-plus'),
        ];
    }

    public function getTabs(): array
    {
        $today = Carbon::today();

        return [
            'all' => Tab::make('All Records')
                ->badge(static::getResource()::getModel()::count()),

            'new_joiners' => Tab::make('New Joiners')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('process_type', ['new_inside', 'new_outside']))
                ->badge(static::getResource()::getModel()::whereIn('process_type', ['new_inside', 'new_outside'])->count()),

            'renewals' => Tab::make('Renewals')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('process_type', 'renewal'))
                ->badge(static::getResource()::getModel()::where('process_type', 'renewal')->count()),

            'overdue_fines' => Tab::make('Active Fines')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('residence_visa_expiry')->where('residence_visa_expiry', '<', $today)->whereNotNull('fines_start_date')->where('fines_start_date', '<', $today))
                ->badge(static::getResource()::getModel()::whereNotNull('residence_visa_expiry')->where('residence_visa_expiry', '<', $today)->whereNotNull('fines_start_date')->where('fines_start_date', '<', $today)->count())
                ->badgeColor('danger'),

            'expiring_soon' => Tab::make('Expiring Soon')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('residence_visa_expiry')->whereBetween('residence_visa_expiry', [$today, $today->copy()->addDays(30)]))
                ->badge(static::getResource()::getModel()::whereNotNull('residence_visa_expiry')->whereBetween('residence_visa_expiry', [$today, $today->copy()->addDays(30)])->count())
                ->badgeColor('warning'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Vouchers\Widgets\ProRecordsOverviewWidget::class,
        ];
    }
}
