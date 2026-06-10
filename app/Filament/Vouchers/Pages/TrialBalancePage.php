<?php

namespace App\Filament\Vouchers\Pages;

use App\Services\GeneralLedgerService;
use App\Models\LedgerBranch;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TrialBalanceExport;
use Carbon\Carbon;
use Livewire\Attributes\Computed;

class TrialBalancePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationGroup = 'Accounting';
    protected static ?string $navigationLabel = 'Trial Balance';
    protected static ?int $navigationSort = 7;
    protected static string $view = 'filament.vouchers.pages.trial-balance-page';


    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
        ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin']) || auth()->user()->can('gl_report.view');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Report Filters')
                    ->schema([
                        DatePicker::make('from_date')
                            ->label('From Date')
                            ->native(false),
                        DatePicker::make('to_date')
                            ->label('To Date')
                            ->native(false),
                        Select::make('branch')
                            ->label('Branch')
                            ->options(LedgerBranch::pluck('name', 'name'))
                            ->searchable()
                            ->placeholder('All Branches'),
                    ])->columns(3),
            ])
            ->statePath('data');
    }

    #[Computed]
    public function accounts(): Collection
    {
        $filters = $this->data;
        $from = !empty($filters['from_date']) ? Carbon::parse($filters['from_date']) : null;
        $to = !empty($filters['to_date']) ? Carbon::parse($filters['to_date']) : null;
        $branch = $filters['branch'] ?? null;

        return app(GeneralLedgerService::class)->getTrialBalance($from, $to, $branch);
    }

    #[Computed]
    public function totalDebit(): float
    {
        return $this->accounts->sum('total_debit');
    }

    #[Computed]
    public function totalCredit(): float
    {
        return $this->accounts->sum('total_credit');
    }

    #[Computed]
    public function isBalanced(): bool
    {
        return abs($this->totalDebit - $this->totalCredit) < 0.001;
    }

    public function updateReport(): void
    {
        // This just triggers a re-render, and computed properties will re-evaluate
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => $this->export()),
        ];
    }

    public function export()
    {
        $filters = $this->data;
        $from = !empty($filters['from_date']) ? Carbon::parse($filters['from_date']) : null;
        $to = !empty($filters['to_date']) ? Carbon::parse($filters['to_date']) : null;
        $branch = $filters['branch'] ?? null;

        return Excel::download(
            new TrialBalanceExport($this->accounts, $from, $to, $branch),
            'Trial_Balance_' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
