<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Contracts\Database\Eloquent\Builder;
use App\Models\Branch;
use App\Models\Entity;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filters')
                    ->description('Filter the dashboard data by entity, branch and date range.')
                    ->schema([
                        // Entity filter — HQ only, appears first so it narrows the branch list
                        Select::make('entity_id')
                            ->label('Entity')
                            ->options(Entity::where('is_active', true)->pluck('name', 'id'))
                            ->visible(fn () => auth()->user()->branch_id === null)
                            ->searchable()
                            ->prefixIcon('heroicon-o-building-library')
                            ->preload()
                            ->placeholder('All Entities')
                            ->live()   // triggers reactive re-render of branch dropdown
                            ->afterStateUpdated(fn (callable $set) => $set('branch_id', null)),

                        Select::make('branch_id')
                            ->label('Branch')
                            ->options(function (callable $get) {
                                $entityId = $get('entity_id');
                                return Branch::where('is_active', true)
                                    ->when($entityId, fn ($q) => $q->where('entity_id', $entityId))
                                    ->pluck('name', 'id');
                            })
                            ->visible(fn () => auth()->user()->branch_id === null)
                            ->searchable()
                            ->prefixIcon('heroicon-o-building-office')
                            ->preload()
                            ->placeholder('All Branches'),
                        
                        DatePicker::make('startDate')
                            ->label('Start Date')
                            ->prefixIcon('heroicon-o-calendar'),
                            
                        DatePicker::make('endDate')
                            ->label('End Date')
                            ->prefixIcon('heroicon-o-calendar'),
                    ])
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                    ->collapsible(),
            ]);
    }

    // Export Action Removed
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('create_transaction')
                ->label('Create Transaction')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->url(fn (): string => \App\Filament\Resources\TransactionResource::getUrl('create')),
        ];
    }
    
    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\StatsOverview::class,
            \App\Filament\Widgets\CashFlowChart::class,
            \App\Filament\Widgets\ExpensesByCategoryChart::class,
            \App\Filament\Widgets\LatestTransactions::class,
        ];
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
