<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Contracts\Database\Eloquent\Builder;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filters')
                    ->description('Filter the dashboard data by branch and date range.')
                    ->schema([
                        Select::make('branch_id')
                            ->label('Branch')
                            ->options(\App\Models\Branch::where('is_active', true)->pluck('name', 'id'))
                            ->visible(fn () => auth()->user()->branch_id === null)
                            ->searchable()
                            ->prefixIcon('heroicon-o-building-office')
                            ->preload(),
                        
                        DatePicker::make('startDate')
                            ->label('Start Date')
                            ->prefixIcon('heroicon-o-calendar'),
                            
                        DatePicker::make('endDate')
                            ->label('End Date')
                            ->prefixIcon('heroicon-o-calendar'),
                    ])
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                    ->collapsible(),
            ]);
    }

    // Export Action Removed
    protected function getHeaderActions(): array
    {
        return [];
    }
    
    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 3,
        ];
    }
}
