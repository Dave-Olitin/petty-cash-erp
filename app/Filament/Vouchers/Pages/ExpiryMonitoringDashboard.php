<?php

namespace App\Filament\Vouchers\Pages;

use App\Models\LedgerBranch;
use App\Models\ProRecord;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Components\Tab;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;

class ExpiryMonitoringDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'PRO & Government Relations';
    protected static ?string $navigationLabel = 'Expiry Monitoring';
    protected static ?string $title           = 'Expiry Monitoring Dashboard';
    public ?string $subheading                 = 'Real-time compliance tracking, document expiry countdowns, and automated UAE fine projections.';
    protected static ?int $navigationSort     = 2;
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view             = 'filament.vouchers.pages.expiry-monitoring-dashboard';

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

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Vouchers\Widgets\ExpiryMonitoringStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        $today = Carbon::today();
        $in30Days = $today->copy()->addDays(30);
        $in6Months = $today->copy()->addMonths(6);

        return [
            'all' => Tab::make('All Employees')
                ->badge(ProRecord::count()),

            'action_required' => Tab::make('Action Required')
                ->badge(
                    ProRecord::where(function ($q) use ($today, $in6Months) {
                        $q->where('residence_visa_expiry', '<', $today)
                          ->orWhere('emirates_id_expiry', '<', $today)
                          ->orWhere('labour_card_expiry', '<', $today)
                          ->orWhere('passport_expiry', '<', $in6Months);
                    })->count()
                )
                ->badgeColor('danger')
                ->modifyQueryUsing(function ($q) use ($today, $in6Months) {
                    $q->where(function ($sub) use ($today, $in6Months) {
                        $sub->where('residence_visa_expiry', '<', $today)
                            ->orWhere('emirates_id_expiry', '<', $today)
                            ->orWhere('labour_card_expiry', '<', $today)
                            ->orWhere('passport_expiry', '<', $in6Months);
                    });
                }),

            'expiring_soon' => Tab::make('Expiring Soon (≤30d)')
                ->badge(
                    ProRecord::where(function ($q) use ($today, $in30Days, $in6Months) {
                        $q->where(function ($active) use ($today, $in6Months) {
                            $active->where('residence_visa_expiry', '>=', $today)
                                   ->where('emirates_id_expiry', '>=', $today)
                                   ->where('labour_card_expiry', '>=', $today)
                                   ->where('passport_expiry', '>=', $in6Months);
                        })->where(function ($soon) use ($today, $in30Days) {
                            $soon->whereBetween('residence_visa_expiry', [$today, $in30Days])
                                 ->orWhereBetween('emirates_id_expiry', [$today, $in30Days])
                                 ->orWhereBetween('labour_card_expiry', [$today, $in30Days]);
                        });
                    })->count()
                )
                ->badgeColor('warning')
                ->modifyQueryUsing(function ($q) use ($today, $in30Days, $in6Months) {
                    $q->where(function ($active) use ($today, $in6Months) {
                        $active->where('residence_visa_expiry', '>=', $today)
                               ->where('emirates_id_expiry', '>=', $today)
                               ->where('labour_card_expiry', '>=', $today)
                               ->where('passport_expiry', '>=', $in6Months);
                    })->where(function ($soon) use ($today, $in30Days) {
                        $soon->whereBetween('residence_visa_expiry', [$today, $in30Days])
                             ->orWhereBetween('emirates_id_expiry', [$today, $in30Days])
                             ->orWhereBetween('labour_card_expiry', [$today, $in30Days]);
                    });
                }),

            'on_track' => Tab::make('On Track')
                ->badge(
                    ProRecord::where(function ($q) use ($in30Days, $in6Months) {
                        $q->where('residence_visa_expiry', '>', $in30Days)
                          ->where('emirates_id_expiry', '>', $in30Days)
                          ->where('labour_card_expiry', '>', $in30Days)
                          ->where('passport_expiry', '>=', $in6Months);
                    })->count()
                )
                ->badgeColor('success')
                ->modifyQueryUsing(function ($q) use ($in30Days, $in6Months) {
                    $q->where('residence_visa_expiry', '>', $in30Days)
                      ->where('emirates_id_expiry', '>', $in30Days)
                      ->where('labour_card_expiry', '>', $in30Days)
                      ->where('passport_expiry', '>=', $in6Months);
                }),
        ];
    }

    public function table(Table $table): Table
    {
        $today = Carbon::today();

        return $table
            ->query(ProRecord::query()->with(['ledgerBranch', 'proCategory']))
            ->columns([
                TextColumn::make('employee_name')
                    ->label('Employee')
                    ->description(fn (ProRecord $record) => ($record->ledgerBranch?->name ?? 'Head Office') . ($record->job_description ? ' • ' . $record->job_description : ''))
                    ->searchable(['employee_name', 'nationality', 'job_description', 'residence_visa_number', 'emirates_id_number', 'passport_number'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('residence_visa_expiry')
                    ->label('Residence Visa')
                    ->date('d M Y')
                    ->description(function (ProRecord $record) use ($today) {
                        if (! $record->residence_visa_expiry) return null;
                        $days = (int) $today->diffInDays(Carbon::parse($record->residence_visa_expiry), false);
                        if ($days < 0) return abs($days) . 'd overdue';
                        if ($days === 0) return 'Expires today';
                        return $days . 'd left';
                    })
                    ->badge(fn (ProRecord $record) => (bool) $record->residence_visa_expiry)
                    ->color(function (ProRecord $record) use ($today) {
                        if (! $record->residence_visa_expiry) return 'gray';
                        $days = (int) $today->diffInDays(Carbon::parse($record->residence_visa_expiry), false);
                        if ($days <= 0) return 'danger';
                        if ($days <= 30) return 'warning';
                        return 'success';
                    })
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('emirates_id_expiry')
                    ->label('Emirates ID')
                    ->date('d M Y')
                    ->description(function (ProRecord $record) use ($today) {
                        if (! $record->emirates_id_expiry) return null;
                        $days = (int) $today->diffInDays(Carbon::parse($record->emirates_id_expiry), false);
                        if ($days < 0) return abs($days) . 'd overdue';
                        if ($days === 0) return 'Expires today';
                        return $days . 'd left';
                    })
                    ->badge(fn (ProRecord $record) => (bool) $record->emirates_id_expiry)
                    ->color(function (ProRecord $record) use ($today) {
                        if (! $record->emirates_id_expiry) return 'gray';
                        $days = (int) $today->diffInDays(Carbon::parse($record->emirates_id_expiry), false);
                        if ($days <= 0) return 'danger';
                        if ($days <= 30) return 'warning';
                        return 'success';
                    })
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('labour_card_expiry')
                    ->label('Work Permit')
                    ->date('d M Y')
                    ->description(function (ProRecord $record) use ($today) {
                        if (! $record->labour_card_expiry) return null;
                        $days = (int) $today->diffInDays(Carbon::parse($record->labour_card_expiry), false);
                        if ($days < 0) return abs($days) . 'd overdue';
                        if ($days === 0) return 'Expires today';
                        return $days . 'd left';
                    })
                    ->badge(fn (ProRecord $record) => (bool) $record->labour_card_expiry)
                    ->color(function (ProRecord $record) use ($today) {
                        if (! $record->labour_card_expiry) return 'gray';
                        $days = (int) $today->diffInDays(Carbon::parse($record->labour_card_expiry), false);
                        if ($days <= 0) return 'danger';
                        if ($days <= 30) return 'warning';
                        return 'gray';
                    })
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('passport_expiry')
                    ->label('Passport')
                    ->date('d M Y')
                    ->description(function (ProRecord $record) use ($today) {
                        if (! $record->passport_expiry) return null;
                        $days = (int) $today->diffInDays(Carbon::parse($record->passport_expiry), false);
                        if ($days < 180) return '< 6mo validity';
                        return $days . 'd left';
                    })
                    ->badge(fn (ProRecord $record) => (bool) $record->passport_expiry)
                    ->color(function (ProRecord $record) use ($today) {
                        if (! $record->passport_expiry) return 'gray';
                        $days = (int) $today->diffInDays(Carbon::parse($record->passport_expiry), false);
                        if ($days < 180) return 'danger';
                        return 'gray';
                    })
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('fines_start_date')
                    ->label('Fine Start')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('calculated_total_fines')
                    ->label('Estimated Fines')
                    ->money('AED')
                    ->alignEnd()
                    ->badge(fn ($state) => $state > 0)
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),

                TextColumn::make('compliance_status')
                    ->label('Status')
                    ->badge()
                    ->state(function (ProRecord $record) use ($today) {
                        if (
                            ($record->residence_visa_expiry && Carbon::parse($record->residence_visa_expiry)->lt($today)) ||
                            ($record->emirates_id_expiry && Carbon::parse($record->emirates_id_expiry)->lt($today)) ||
                            ($record->labour_card_expiry && Carbon::parse($record->labour_card_expiry)->lt($today)) ||
                            ($record->passport_expiry && Carbon::parse($record->passport_expiry)->lt($today->copy()->addMonths(6)))
                        ) {
                            return 'Action Required';
                        }

                        if (
                            ($record->residence_visa_expiry && Carbon::parse($record->residence_visa_expiry)->lte($today->copy()->addDays(30))) ||
                            ($record->emirates_id_expiry && Carbon::parse($record->emirates_id_expiry)->lte($today->copy()->addDays(30))) ||
                            ($record->labour_card_expiry && Carbon::parse($record->labour_card_expiry)->lte($today->copy()->addDays(30)))
                        ) {
                            return 'Expiring Soon';
                        }

                        return 'On Track';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Action Required' => 'danger',
                        'Expiring Soon'   => 'warning',
                        default           => 'success',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Action Required' => 'heroicon-m-exclamation-triangle',
                        'Expiring Soon'   => 'heroicon-m-clock',
                        default           => 'heroicon-m-check-circle',
                    }),
            ])
            ->filters([
                SelectFilter::make('ledger_branch_id')
                    ->label('Sponsoring Branch')
                    ->relationship('ledgerBranch', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('has_fines')
                    ->label('Has Active Overstay Fines')
                    ->query(function ($q) use ($today) {
                        $q->where(function ($sub) use ($today) {
                            $sub->where('fine_pr_50', '>', 0)
                                ->orWhere('fine_eid_20', '>', 0)
                                ->orWhere(function ($fineQ) use ($today) {
                                    $fineQ->where('residence_visa_expiry', '<', $today)
                                          ->whereNotNull('fines_start_date')
                                          ->where('fines_start_date', '<', $today);
                                });
                        });
                    }),

                Filter::make('passport_flagged')
                    ->label('Passport < 6 Months Validity')
                    ->query(fn ($query) => $query->where('passport_expiry', '<', Carbon::today()->addMonths(6))),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->url(fn (ProRecord $record): string => url("/vouchers/pro-records/{$record->id}")),
            ])
            ->defaultSort('fines_start_date', 'asc')
            ->paginated([10, 25, 50, 100]);
    }
}
