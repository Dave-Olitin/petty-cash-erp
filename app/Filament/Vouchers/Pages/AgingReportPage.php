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
    protected static ?string $navigationGroup   = 'Accounting';
    protected static bool    $shouldRegisterNavigation = true;
    protected static ?int    $navigationSort    = 5;
    protected static string  $view              = 'filament.vouchers.pages.aging-report-page';

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['Accountant', 'Admin', 'Super Admin'])
            || auth()->user()->can('purchase_entry.view');
    }

    // ── Filter Properties ─────────────────────────────────────────────────

    public ?string $as_of_date     = null;
    public ?string $supplier_id    = null;
    public ?string $entity         = null;
    public ?string $payment_status = null;
    public ?string $month_filter   = null;

    public function mount(): void
    {
        $this->as_of_date = now()->toDateString();
        $this->payment_status = 'outstanding';
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

                Select::make('month_filter')
                    ->label('Bill Month')
                    ->options(function () {
                        $options = [];
                        for ($i = 0; $i < 36; $i++) {
                            $date = now()->subMonths($i);
                            $options[$date->format('Y-m')] = $date->format('F Y');
                        }
                        return $options;
                    })
                    ->searchable()
                    ->placeholder('All Months')
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
                        'outstanding' => 'Outstanding Only (Unpaid & Partial)',
                        'unpaid'      => 'Unpaid Only',
                        'partial'     => 'Partially Paid Only',
                        'paid'        => 'Paid Only',
                        'all'         => 'All Invoices',
                    ])
                    ->default('outstanding')
                    ->live(),
            ])
            ->columns(['sm' => 2, 'xl' => 5]);
    }

    // ── Data ─────────────────────────────────────────────────────────────

    /**
     * Returns the full aging analysis as an array of per-supplier rows.
     * Each row has: supplier_name, entry counts and totals per bucket.
     */
    public function getAgingData(): array
    {
        $asOf = Carbon::parse($this->as_of_date ?? now())->startOfDay();

        $query = PurchaseEntry::query()
            ->with('taxRegistration')
            ->where('date', '<=', $asOf->toDateString());

        $statusFilter = $this->payment_status ?? 'outstanding';
        if ($statusFilter === 'outstanding') {
            $query->whereIn('payment_status', [PurchaseEntry::STATUS_UNPAID, PurchaseEntry::STATUS_PARTIAL]);
        } elseif ($statusFilter === 'unpaid') {
            $query->where('payment_status', PurchaseEntry::STATUS_UNPAID);
        } elseif ($statusFilter === 'partial') {
            $query->where('payment_status', PurchaseEntry::STATUS_PARTIAL);
        } elseif ($statusFilter === 'paid') {
            $query->where('payment_status', PurchaseEntry::STATUS_PAID);
        }
        // 'all' includes all statuses without filtering

        if ($this->supplier_id) {
            $query->where('tax_registration_id', $this->supplier_id);
        }
        if ($this->entity) {
            $query->where('entity', $this->entity);
        }
        if ($this->month_filter) {
            $parts = explode('-', $this->month_filter);
            if (count($parts) === 2) {
                $query->whereYear('date', $parts[0])
                      ->whereMonth('date', $parts[1]);
            }
        }

        $entries = $query->orderBy('date', 'desc')->get();

        // ── Bucket thresholds (days overdue relative to as_of_date) ──────
        $buckets = [
            'current'  => ['label' => 'Current',      'min' => null, 'max' => 0],
            'b1_30'    => ['label' => '1–30 Days',     'min' => 1,   'max' => 30],
            'b31_60'   => ['label' => '31–60 Days',    'min' => 31,  'max' => 60],
            'b61_90'   => ['label' => '61–90 Days',    'min' => 61,  'max' => 90],
            'b90plus'  => ['label' => '90+ Days',      'min' => 91,  'max' => null],
        ];

        // ── Group by supplier ─────────────────────────────────────────────
        $grouped = $entries->groupBy(function ($e) {
            if ($e->tax_registration_id) {
                return 'tax_' . $e->tax_registration_id;
            }
            $name = trim($e->supplier_name ?? '');
            return 'supp_' . ($name !== '' ? strtolower($name) : 'unknown');
        });

        $rows = [];
        $grandTotals = array_fill_keys(array_keys($buckets), 0.0);
        $grandTotals['total'] = 0.0;
        $grandTotals['count'] = 0;

        foreach ($grouped as $groupKey => $supplierEntries) {
            $firstEntry = $supplierEntries->first();
            
            $supplierName = $firstEntry?->taxRegistration?->name 
                         ?: $firstEntry?->supplier_name 
                         ?: 'Unknown Supplier';
                         
            $supplierTrn = $firstEntry?->taxRegistration?->trn 
                        ?: $firstEntry?->supplier_trn 
                        ?: '—';

            $row = [
                'supplier_name' => $supplierName,
                'supplier_trn'  => $supplierTrn,
                'entries'       => [],
                'count'         => 0,
                'total'         => 0.0,
            ];

            foreach ($buckets as $key => $bucket) {
                $row[$key] = 0.0;
            }

            foreach ($supplierEntries as $entry) {
                $rawDue   = $entry->due_date ?? $entry->date;
                $dueDate  = $rawDue ? Carbon::parse($rawDue)->startOfDay() : null;
                $balance  = (float) $entry->balance_due;
                $isPaid   = ($entry->payment_status === PurchaseEntry::STATUS_PAID || $balance <= 0);

                // Days overdue: 0 if paid, otherwise diff from due date to as-of date
                $overdue  = ($isPaid || ! $dueDate) ? 0 : max(0, (int) $dueDate->diffInDays($asOf, false));

                // Deduct if it's a Return
                if ($entry->entry_type === PurchaseEntry::TYPE_RETURN) {
                    $balance = -$balance;
                }

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
                $row['count']++;

                $row['entries'][] = [
                    'entry_no'       => $entry->entry_no,
                    'date'           => $entry->date?->format('d/m/Y'),
                    'due_date'       => $entry->due_date?->format('d/m/Y') ?? ($entry->date ? $entry->date->format('d/m/Y') . ' (On Bill)' : '—'),
                    'days_overdue'   => $overdue,
                    'grand_total'    => (float) $entry->grand_total,
                    'amount_paid'    => (float) $entry->amount_paid,
                    'balance_due'    => $balance,
                    'payment_status' => $entry->payment_status,
                    'invoice_no'     => $entry->invoice_no ?? '—',
                    'is_paid'        => $isPaid,
                ];
            }

            $grandTotals['count'] += $row['count'];
            $rows[] = $row;
        }

        // Multi-tier sort: 90+ bucket desc, then total balance desc, then supplier name asc
        usort($rows, function ($a, $b) {
            return ($b['b90plus'] <=> $a['b90plus'])
                ?: ($b['total'] <=> $a['total'])
                ?: strcasecmp($a['supplier_name'], $b['supplier_name']);
        });

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
                ->url(fn () => \App\Filament\Vouchers\Resources\PurchaseEntryResource::getUrl('index', panel: 'vouchers')),
        ];
    }
}
