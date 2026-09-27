<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class TopPayeesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Top 5 Payees';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];
    protected static ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return false;
    }

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $branchId = auth()->user()->isHeadOffice() ? ($this->filters['branch_id'] ?? null) : auth()->user()->branch_id;
        $entityBranchIds = null;
        if (auth()->user()->isHeadOffice() && !$branchId && !empty($this->filters['entity_id'])) {
            $entityBranchIds = \App\Models\Branch::where('entity_id', $this->filters['entity_id'])
                ->pluck('id')->toArray();
        }

        $data = \App\Models\Transaction::query()
            ->where('type', 'EXPENSE')
            ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when($entityBranchIds, fn($q) => $q->whereIn('branch_id', $entityBranchIds))
            ->selectRaw('payee, sum(amount) as total')
            ->groupBy('payee')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Total Paid',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => '#EC4899', // Pink
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $data->pluck('payee')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
    
    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
