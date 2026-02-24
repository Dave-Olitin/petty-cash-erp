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

class VoucherReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Petty Cash Report';
    protected static ?string $title = 'Petty Cash Usage Report';
    protected static ?string $navigationGroup = null;
    protected static ?int $navigationSort = 10;
    protected static string $view = 'filament.vouchers.pages.voucher-report-page';

    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?string $category_id = null;
    public ?string $type = null;

    public function mount(): void
    {
        $this->date_from = now()->startOfMonth()->toDateString();
        $this->date_to   = now()->toDateString();
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

        $vouchers = $query->orderByDesc('created_at')->get();

        $byCategory = $vouchers->groupBy('category.name')->map(fn ($v) => $v->sum('amount'))->sortDesc();

        return [
            'vouchers'      => $vouchers,
            'total_amount'  => $vouchers->sum('amount'),
            'total_count'   => $vouchers->count(),
            'by_category'   => $byCategory,
        ];
    }

    public function exportCsv(): mixed
    {
        $data = $this->getReportData();
        $vouchers = $data['vouchers'];

        return response()->streamDownload(function () use ($vouchers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Voucher #', 'Type', 'Payee', 'Category', 'Requester', 'Amount (AED)']);
            foreach ($vouchers as $v) {
                fputcsv($out, [
                    $v->created_at->format('Y-m-d'),
                    $v->voucher_number,
                    $v->type === 'petty_cash' ? 'Petty Cash' : 'Payment',
                    $v->payee,
                    $v->category?->name ?? 'N/A',
                    $v->user?->name ?? 'N/A',
                    number_format($v->amount, 2),
                ]);
            }
            fclose($out);
        }, 'petty_cash_report_' . $this->date_from . '_to_' . $this->date_to . '.csv');
    }
}
