<?php

namespace App\Filament\Vouchers\Pages;

use App\Models\Voucher;
use App\Models\Category;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Livewire\WithPagination;

class VoucherReportPage extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Report';
    protected static ?string $title = 'Petty Cash Usage Report';
    protected static ?string $navigationGroup = null;
    protected static ?int $navigationSort = 10;
    protected static string $view = 'filament.vouchers.pages.voucher-report-page';

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin']);
    }

    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?string $category_id = null;
    public ?string $type = null;

    public function mount(): void
    {
        $this->date_from = now()->startOfMonth()->toDateString();
        $this->date_to   = now()->toDateString();
    }

    public function updating($name)
    {
        if (in_array($name, ['date_from', 'date_to', 'type', 'category_id'])) {
            $this->resetPage();
        }
    }

    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('date_from')->label('From')->required(),
            DatePicker::make('date_to')->label('To')->required(),
            Select::make('type')->options([
                'petty_cash' => 'Petty Cash',
                'payment'    => 'Payment Voucher',
            ])->placeholder('All Types'),
            Select::make('category_id')
                ->label('Category')
                ->options(Category::pluck('name', 'id')->toArray())
                ->placeholder('All Categories'),
        ];
    }

    /**
     * The filtered voucher query (used in the view).
     */
    public function getReportData(): array
    {
        $query = Voucher::query()
            ->where('status', 'paid')
            ->with(['user', 'category'])
            ->whereBetween('created_at', [$this->date_from . ' 00:00:00', $this->date_to . ' 23:59:59']);

        if ($this->type) {
            $query->where('type', $this->type);
        }
        if ($this->category_id) {
            $query->where('category_id', $this->category_id);
        }

        $baseQuery = clone $query;
        $totalAmount = $baseQuery->sum('amount');
        $totalCount = $baseQuery->count();

        $vouchers = $query->orderByDesc('created_at')->paginate(10);

        // Calculate by category securely without loading everything into memory
        $categoryData = (clone $baseQuery)->select('category_id', DB::raw('SUM(amount) as total_amount'))
            ->withoutEagerLoads()
            ->with('category')
            ->groupBy('category_id')
            ->get();

        $byCategory = $categoryData->mapWithKeys(function ($item) {
            $name = $item->category ? $item->category->name : 'Uncategorized';
            return [$name => $item->total_amount];
        })->sortDesc();

        return [
            'vouchers'      => $vouchers,
            'total_amount'  => $totalAmount,
            'total_count'   => $totalCount,
            'by_category'   => $byCategory,
        ];
    }

    public function exportExcel(): mixed
    {
        $query = Voucher::query()
            ->where('status', 'paid')
            ->with(['user', 'category'])
            ->whereBetween('created_at', [$this->date_from . ' 00:00:00', $this->date_to . ' 23:59:59']);

        if ($this->type) {
            $query->where('type', $this->type);
        }
        if ($this->category_id) {
            $query->where('category_id', $this->category_id);
        }

        $query->orderByDesc('created_at');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\VouchersExport($query),
            'petty_cash_report_' . $this->date_from . '_to_' . $this->date_to . '.xlsx'
        );
    }
}
