<?php

namespace App\Filament\Vouchers\Pages;

use App\Models\LedgerBranch;
use App\Models\ProRecord;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class ExpiryMonitoringDashboard extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'PRO & Government Relations';
    protected static ?string $navigationLabel = 'Expiry Monitoring';
    protected static ?string $title           = 'Expiry Monitoring Dashboard';
    protected static ?int $navigationSort     = 2;
    protected static string $view             = 'filament.vouchers.pages.expiry-monitoring-dashboard';

    public string $activeTab     = 'employees'; // 'employees' | 'trade_licenses' | 'establishment_cards'
    public string $search        = '';
    public string $statusFilter  = 'all'; // 'all' | 'action_required' | 'expiring_soon' | 'on_track'
    public string $branchFilter  = 'all';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['Admin', 'Super Admin', 'PRO Officer', 'Accountant', 'Accountant 1'])
            || $user->can('pro_record.view');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncFines')
                ->label('Sync Live Fines')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->outlined()
                ->action(function () {
                    \Illuminate\Support\Facades\Artisan::call('pro:sync-fines-and-alerts');
                    Notification::make()
                        ->title('Compliance Fines Recalculated')
                        ->body('Overstay penalties, grace periods, and fine projections synchronized.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function getBranchesProperty(): Collection
    {
        return LedgerBranch::orderBy('name')->get();
    }

    /**
     * Compute all rows for the Employees Tab (Residence Visa, Emirates ID, Work Permit, Passport).
     */
    public function getEmployeesDataProperty(): Collection
    {
        $today = Carbon::today();
        $records = ProRecord::with('ledgerBranch')->get();
        $rows = collect();

        foreach ($records as $record) {
            $branchName = $record->ledgerBranch?->name ?? 'Head Office';

            // 1. Residence Visa
            if ($record->residence_visa_expiry) {
                $expiry = Carbon::parse($record->residence_visa_expiry);
                $daysRemaining = (int) $today->diffInDays($expiry, false);
                $graceDays = (int) ($record->grace_period_days ?: 30);
                
                $finesStart = $record->fines_start_date 
                    ? Carbon::parse($record->fines_start_date) 
                    : $expiry->copy()->addDays($graceDays);

                $daysToGraceEnd = (int) $today->diffInDays($finesStart, false);
                $fines = (float) $record->calculated_total_fines;

                $status = 'on_track';
                if ($daysRemaining < 0) {
                    $status = 'action_required';
                } elseif ($daysRemaining <= 30) {
                    $status = 'expiring_soon';
                }

                $rows->push([
                    'id'                   => "visa-{$record->id}",
                    'name'                 => $record->employee_name,
                    'branch'               => $branchName,
                    'document_type'        => 'Residence Visa',
                    'doc_number'           => $record->residence_visa_number ?: '—',
                    'expiry_date'          => $expiry,
                    'days_remaining'       => $daysRemaining,
                    'grace_period'         => $graceDays,
                    'days_to_grace_end'    => $daysToGraceEnd,
                    'projected_fine_start' => $finesStart,
                    'estimated_fine'       => $fines,
                    'status'               => $status,
                ]);
            }

            // 2. Emirates ID
            if ($record->emirates_id_expiry) {
                $expiry = Carbon::parse($record->emirates_id_expiry);
                $daysRemaining = (int) $today->diffInDays($expiry, false);
                $graceDays = 30; // ICP gives 30 days after expiry
                $finesStart = $expiry->copy()->addDays($graceDays + 1);
                $daysToGraceEnd = (int) $today->diffInDays($finesStart, false);
                $fines = (float) $record->calculated_fine_eid_20;

                $status = 'on_track';
                if ($daysRemaining < 0) {
                    $status = 'action_required';
                } elseif ($daysRemaining <= 30) {
                    $status = 'expiring_soon';
                }

                $rows->push([
                    'id'                   => "eid-{$record->id}",
                    'name'                 => $record->employee_name,
                    'branch'               => $branchName,
                    'document_type'        => 'Emirates ID',
                    'doc_number'           => $record->emirates_id_number ?: '—',
                    'expiry_date'          => $expiry,
                    'days_remaining'       => $daysRemaining,
                    'grace_period'         => null,
                    'days_to_grace_end'    => null,
                    'projected_fine_start' => null,
                    'estimated_fine'       => $fines > 0 ? $fines : 0,
                    'status'               => $status,
                ]);
            }

            // 3. Work Permit (MOHRE) / Labour Card
            if ($record->labour_card_expiry) {
                $expiry = Carbon::parse($record->labour_card_expiry);
                $daysRemaining = (int) $today->diffInDays($expiry, false);
                $finesStart = $expiry->copy()->addDay();
                $isOverdue = $daysRemaining < 0;
                $fines = $isOverdue ? ceil(abs($daysRemaining) / 30) * 200 : 0; // MOHRE AED 200/month delay fee

                $status = 'on_track';
                if ($daysRemaining < 0) {
                    $status = 'action_required';
                } elseif ($daysRemaining <= 30) {
                    $status = 'expiring_soon';
                }

                $rows->push([
                    'id'                   => "wp-{$record->id}",
                    'name'                 => $record->employee_name,
                    'branch'               => $branchName,
                    'document_type'        => 'Work Permit (MOHRE)',
                    'doc_number'           => $record->labour_card_number ?: '—',
                    'expiry_date'          => $expiry,
                    'days_remaining'       => $daysRemaining,
                    'grace_period'         => null,
                    'days_to_grace_end'    => null,
                    'projected_fine_start' => $finesStart,
                    'estimated_fine'       => $fines,
                    'status'               => $status,
                ]);
            }

            // 4. Passport
            if ($record->passport_expiry) {
                $expiry = Carbon::parse($record->passport_expiry);
                $daysRemaining = (int) $today->diffInDays($expiry, false);

                // UAE Rule: Must have >= 6 months (180 days) validity for visa renewals
                $status = 'on_track';
                if ($daysRemaining < 180) {
                    $status = 'action_required';
                }

                $rows->push([
                    'id'                   => "pass-{$record->id}",
                    'name'                 => $record->employee_name,
                    'branch'               => $branchName,
                    'document_type'        => 'Passport',
                    'doc_number'           => $record->passport_number ?: '—',
                    'expiry_date'          => $expiry,
                    'days_remaining'       => $daysRemaining,
                    'grace_period'         => null,
                    'days_to_grace_end'    => null,
                    'projected_fine_start' => null,
                    'estimated_fine'       => 0,
                    'status'               => $status,
                ]);
            }
        }

        return $this->applyFilters($rows);
    }

    /**
     * Compute all rows for the Trade Licenses Tab.
     */
    public function getTradeLicensesDataProperty(): Collection
    {
        $today = Carbon::today();
        $branches = LedgerBranch::all();
        $rows = collect();

        foreach ($branches as $branch) {
            if ($branch->license_expiry) {
                $expiry = Carbon::parse($branch->license_expiry);
                $daysRemaining = (int) $today->diffInDays($expiry, false);
                $graceDays = 30; // standard DED 30-day grace period
                $finesStart = $expiry->copy()->addDays($graceDays + 1);
                $daysToGraceEnd = (int) $today->diffInDays($finesStart, false);
                
                // Estimated DED penalty
                $fines = 0;
                if ($daysRemaining < -30) {
                    $monthsOverdue = ceil((abs($daysRemaining) - 30) / 30);
                    $fines = min(5000, $monthsOverdue * 250);
                }

                $status = 'on_track';
                if ($daysRemaining < 0) {
                    $status = 'action_required';
                } elseif ($daysRemaining <= 60) {
                    $status = 'expiring_soon';
                }

                $authority = $branch->issuing_authority ?: 'DED';

                $rows->push([
                    'id'                   => "lic-{$branch->id}",
                    'name'                 => $branch->name,
                    'branch'               => $branch->name,
                    'document_type'        => "Trade License ({$authority})",
                    'doc_number'           => $branch->license_number ?: '—',
                    'expiry_date'          => $expiry,
                    'days_remaining'       => $daysRemaining,
                    'grace_period'         => $graceDays,
                    'days_to_grace_end'    => $daysToGraceEnd,
                    'projected_fine_start' => $finesStart,
                    'estimated_fine'       => $fines,
                    'status'               => $status,
                ]);
            }
        }

        return $this->applyFilters($rows);
    }

    /**
     * Compute all rows for the Establishment Cards Tab.
     */
    public function getEstablishmentCardsDataProperty(): Collection
    {
        $today = Carbon::today();
        $branches = LedgerBranch::all();
        $rows = collect();

        foreach ($branches as $branch) {
            // MOHRE Establishment Card
            if ($branch->mohre_est_card_expiry) {
                $expiry = Carbon::parse($branch->mohre_est_card_expiry);
                $daysRemaining = (int) $today->diffInDays($expiry, false);
                $finesStart = $expiry->copy()->addDay();
                $isOverdue = $daysRemaining < 0;
                $fines = $isOverdue ? ceil(abs($daysRemaining) / 30) * 200 : 0;

                $status = 'on_track';
                if ($daysRemaining < 0) {
                    $status = 'action_required';
                } elseif ($daysRemaining <= 30) {
                    $status = 'expiring_soon';
                }

                $rows->push([
                    'id'                   => "mohre-est-{$branch->id}",
                    'name'                 => $branch->name,
                    'branch'               => $branch->name,
                    'document_type'        => 'MOHRE Establishment Card',
                    'doc_number'           => $branch->mohre_est_card_number ?: '—',
                    'expiry_date'          => $expiry,
                    'days_remaining'       => $daysRemaining,
                    'grace_period'         => null,
                    'days_to_grace_end'    => null,
                    'projected_fine_start' => $finesStart,
                    'estimated_fine'       => $fines,
                    'status'               => $status,
                ]);
            }

            // Immigration / ICP Establishment Card
            if ($branch->immigration_est_card_expiry) {
                $expiry = Carbon::parse($branch->immigration_est_card_expiry);
                $daysRemaining = (int) $today->diffInDays($expiry, false);
                $finesStart = $expiry->copy()->addDay();
                $isOverdue = $daysRemaining < 0;
                $fines = $isOverdue ? ceil(abs($daysRemaining) / 30) * 100 : 0;

                $status = 'on_track';
                if ($daysRemaining < 0) {
                    $status = 'action_required';
                } elseif ($daysRemaining <= 30) {
                    $status = 'expiring_soon';
                }

                $rows->push([
                    'id'                   => "icp-est-{$branch->id}",
                    'name'                 => $branch->name,
                    'branch'               => $branch->name,
                    'document_type'        => 'Immigration Establishment Card',
                    'doc_number'           => $branch->immigration_est_card_number ?: '—',
                    'expiry_date'          => $expiry,
                    'days_remaining'       => $daysRemaining,
                    'grace_period'         => null,
                    'days_to_grace_end'    => null,
                    'projected_fine_start' => $finesStart,
                    'estimated_fine'       => $fines,
                    'status'               => $status,
                ]);
            }
        }

        return $this->applyFilters($rows);
    }

    /**
     * Filter helper by search, branch and status.
     */
    protected function applyFilters(Collection $items): Collection
    {
        return $items->filter(function ($item) {
            // 1. Search Query
            if (!empty($this->search)) {
                $query = strtolower(trim($this->search));
                $haystack = strtolower(
                    ($item['name'] ?? '') . ' ' .
                    ($item['document_type'] ?? '') . ' ' .
                    ($item['doc_number'] ?? '') . ' ' .
                    ($item['branch'] ?? '')
                );
                if (!str_contains($haystack, $query)) {
                    return false;
                }
            }

            // 2. Branch Filter
            if ($this->branchFilter !== 'all') {
                if (($item['branch'] ?? '') !== $this->branchFilter) {
                    return false;
                }
            }

            // 3. Status Filter
            if ($this->statusFilter !== 'all') {
                if (($item['status'] ?? '') !== $this->statusFilter) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * High-level KPI metrics across all categories.
     */
    public function getSummaryMetricsProperty(): array
    {
        $all = $this->employeesData
            ->concat($this->tradeLicensesData)
            ->concat($this->establishmentCardsData);

        $totalDocs       = $all->count();
        $actionRequired  = $all->where('status', 'action_required')->count();
        $expiringSoon    = $all->where('status', 'expiring_soon')->count();
        $onTrack         = $all->where('status', 'on_track')->count();
        $totalFines      = $all->sum('estimated_fine');

        return [
            'total'           => $totalDocs,
            'action_required' => $actionRequired,
            'expiring_soon'   => $expiringSoon,
            'on_track'        => $onTrack,
            'total_fines'     => $totalFines,
        ];
    }
}
