<?php

namespace App\Filament\Vouchers\Pages;

use App\Models\PurchaseEntry;
use App\Models\TaxRegistration;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Carbon\Carbon;

class AgingReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon    = 'heroicon-o-clock';
    protected static ?string $navigationLabel   = 'AP Aging Report';
    protected static ?string $title             = 'Accounts Payable — Aging Report';
    protected static ?string $navigationGroup   = null;   // removed from sidebar
    protected static bool    $shouldRegisterNavigation = false; // accessed via table action button
    protected static ?int    $navigationSort    = 50;
    protected static string  $view              = 'filament.vouchers.pages.aging-report-page';

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'Super Admin'])
            || auth()->user()->can('purchase_entry.view');
    }

    // ── Filter Properties ─────────────────────────────────────────────────

    public ?string $as_of_date     = null;
    public ?string $supplier_id    = null;
    public ?string $entity         = null;
    public ?string $payment_status = null;

    public function mount(): void
    {
        $this->as_of_date = now()->toDateString();
    }

    // ── Form ─────────────────────────────────────────────────────────────

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('as_of_date')
                    ->label('As-of Date')
                    ->required()
                    ->live()
                    ->default(now()),

                Select::make('supplier_id')
                    ->label('Supplier')
                    ->options(TaxRegistration::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('All Suppliers')
                    ->live(),

                Select::make('entity')
                    ->label('Entity')
                    ->options(\App\Models\VoucherTemplate::where('is_active', true)->pluck('company_name', 'company_name'))
                    ->searchable()
                    ->placeholder('All Entities')
                    ->live(),

                Select::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        'unpaid'  => 'Unpaid Only',
                        'partial' => 'Partially Paid Only',
                    ])
                    ->placeholder('All (excl. Paid)')
                    ->live(),
            ])
            ->columns(['sm' => 2, 'xl' => 4]);
    }

    // ── Data ─────────────────────────────────────────────────────────────

    /**
     * Returns the full aging analysis as an array of per-supplier rows.
     * Each row has: supplier_name, entry counts and totals per bucket.
     */
    public function getAgingData(): array
    {
        $asOf = Carbon::parse($this->as_of_date ?? now());

        $query = PurchaseEntry::query()
            ->with('taxRegistration')
            ->where('entry_type', PurchaseEntry::TYPE_PURCHASE)   // only purchase bills, not returns
            ->whereIn('payment_status', [
                PurchaseEntry::STATUS_UNPAID,
                PurchaseEntry::STATUS_PARTIAL,
            ]);

        if ($this->supplier_id) {
            $query->where('tax_registration_id', $this->supplier_id);
        }
        if ($this->entity) {
            $query->where('entity', $this->entity);
        }
        if ($this->payment_status) {
            $query->where('payment_status', $this->payment_status);
        }

        $entries = $query->get();

        // ── Bucket thresholds (days overdue relative to as_of_date) ──────
        $buckets = [
            'current'  => ['label' => 'Current',      'min' => null, 'max' => 0],
            'b1_30'    => ['label' => '1–30 Days',     'min' => 1,   'max' => 30],
            'b31_60'   => ['label' => '31–60 Days',    'min' => 31,  'max' => 60],
            'b61_90'   => ['label' => '61–90 Days',    'min' => 61,  'max' => 90],
            'b90plus'  => ['label' => '90+ Days',      'min' => 91,  'max' => null],
        ];

        // ── Group by supplier ─────────────────────────────────────────────
        $grouped = $entries->groupBy(fn ($e) => $e->tax_registration_id ?? 'unknown');

        $rows = [];
        $grandTotals = array_fill_keys(array_keys($buckets), 0.0);
        $grandTotals['total'] = 0.0;
        $grandTotals['count'] = 0;

        foreach ($grouped as $supplierId => $supplierEntries) {
            $supplierName = $supplierEntries->first()?->taxRegistration?->name ?? 'Unknown Supplier';

            $row = [
                'supplier_name' => $supplierName,
                'entries'       => [],
                'count'         => $supplierEntries->count(),
                'total'         => 0.0,
            ];

            foreach ($buckets as $key => $bucket) {
                $row[$key] = 0.0;
            }

            foreach ($supplierEntries as $entry) {
                $dueDate  = $entry->due_date ? Carbon::instance($entry->due_date) : null;
                $balance  = (float) $entry->balance_due;
                $overdue  = $dueDate ? max(0, (int) $asOf->diffInDays($dueDate, false) * -1) : 0;

                // Classify into bucket
                if ($overdue <= 0) {
                    $row['current'] += $balance;
                    $grandTotals['current'] += $balance;
                } elseif ($overdue <= 30) {
                    $row['b1_30'] += $balance;
                    $grandTotals['b1_30'] += $balance;
                } elseif ($overdue <= 60) {
                    $row['b31_60'] += $balance;
                    $grandTotals['b31_60'] += $balance;
                } elseif ($overdue <= 90) {
                    $row['b61_90'] += $balance;
                    $grandTotals['b61_90'] += $balance;
                } else {
                    $row['b90plus'] += $balance;
                    $grandTotals['b90plus'] += $balance;
                }

                $row['total'] += $balance;
                $grandTotals['total'] += $balance;

                $row['entries'][] = [
                    'entry_no'       => $entry->entry_no,
                    'date'           => $entry->date?->format('d/m/Y'),
                    'due_date'       => $dueDate?->format('d/m/Y') ?? '—',
                    'days_overdue'   => $overdue,
                    'grand_total'    => (float) $entry->grand_total,
                    'amount_paid'    => (float) $entry->amount_paid,
                    'balance_due'    => $balance,
                    'payment_status' => $entry->payment_status,
                    'invoice_no'     => $entry->invoice_no ?? '—',
                ];
            }

            $grandTotals['count'] += $row['count'];
            $rows[] = $row;
        }

        // Sort by 90+ bucket descending (most at-risk first)
        usort($rows, fn ($a, $b) => $b['b90plus'] <=> $a['b90plus']);

        return [
            'rows'         => $rows,
            'grand_totals' => $grandTotals,
            'as_of_date'   => $asOf->format('d M Y'),
            'buckets'      => $buckets,
        ];
    }

    // ── Header Actions ────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export_aging')
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return \Maatwebsite\Excel\Facades\Excel::download(
                        new \App\Exports\AgingReportExport($this->getAgingData()),
                        'ap_aging_' . ($this->as_of_date ?? now()->toDateString()) . '.xlsx'
                    );
                }),

            \Filament\Actions\Action::make('back_to_purchases')
                ->label('Back to Purchases')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => \App\Filament\Vouchers\Resources\PurchaseEntryResource::getUrl('index')),
        ];
    }
}
