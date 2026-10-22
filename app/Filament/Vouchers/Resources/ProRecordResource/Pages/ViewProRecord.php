<?php

namespace App\Filament\Vouchers\Resources\ProRecordResource\Pages;

use App\Filament\Vouchers\Resources\ProRecordResource;
use App\Models\ProRecord;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewProRecord extends ViewRecord
{
    protected static string $resource = ProRecordResource::class;

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return ($this->record->employee_name ?? 'PRO Record') . ' — PRO Profile';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to Tracking')
                ->url(ProRecordResource::getUrl('index'))
                ->icon('heroicon-m-arrow-left')
                ->color('gray'),

            Actions\Action::make('settle_fines')
                ->label('Settle / Pay Fines')
                ->icon('heroicon-m-banknotes')
                ->color('success')
                ->visible(fn (): bool => ! $this->record->fines_settled_at && ($this->record->calculated_total_fines > 0 || ($this->record->fines_start_date && Carbon::today()->gt($this->record->fines_start_date))))
                ->modalHeading(fn (): string => "Settle Overstay Fines — {$this->record->employee_name}")
                ->modalDescription('Record fine payment or official GDRFA waiver to clear active overstay penalties.')
                ->modalWidth('2xl')
                ->form([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Placeholder::make('active_fines_info')
                            ->hiddenLabel()
                            ->content(function () {
                                $record = $this->record;
                                $fines = number_format($record->calculated_total_fines, 2);
                                $overdue = $record->days_overdue;
                                $pr50 = number_format($record->calculated_fine_pr_50, 0);
                                $eid20 = number_format($record->calculated_fine_eid_20, 0);
                                $startDate = $record->effective_fines_start_date ? $record->effective_fines_start_date->format('d M Y') : 'N/A';

                                return new \Illuminate\Support\HtmlString("
                                    <div style='background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 0.75rem; padding: 1rem 1.25rem; display: flex; align-items: center; justify-content: space-between;'>
                                        <div style='display: flex; align-items: center; gap: 0.75rem;'>
                                            <div style='padding: 0.5rem; background-color: #fee2e2; border-radius: 0.5rem; color: #dc2626; display: flex; align-items: center; justify-content: center;'>
                                                <svg style='width: 1.5rem; height: 1.5rem;' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <div style='font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #991b1b;'>Active Accrued Fine</div>
                                                <div style='font-size: 1.35rem; font-weight: 800; color: #b91c1c; line-height: 1.2;'>AED {$fines}</div>
                                            </div>
                                        </div>
                                        <div style='text-align: right;'>
                                            <span style='display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background-color: #fee2e2; color: #991b1b;'>
                                                {$overdue} days overdue
                                            </span>
                                            <div style='font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;'>
                                                PR-50: AED {$pr50} • EID: AED {$eid20} (Since {$startDate})
                                            </div>
                                        </div>
                                    </div>
                                ");
                            })
                            ->columnSpanFull(),

                        Forms\Components\Select::make('fine_settlement_type')
                            ->label('Settlement Type')
                            ->options([
                                'paid'   => 'Paid (Full / Partial Payment)',
                                'waived' => 'Waived / Discounted by GDRFA',
                            ])
                            ->default('paid')
                            ->native(false)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $state) {
                                if ($state === 'waived') {
                                    $set('fine_amount_paid', 0);
                                } else {
                                    $set('fine_amount_paid', $this->record->calculated_total_fines);
                                }
                            })
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('fine_amount_paid')
                            ->label('Amount Paid (AED)')
                            ->prefix('AED')
                            ->numeric()
                            ->default(fn () => $this->record->calculated_total_fines)
                            ->required()
                            ->helperText('Actual amount paid at GDRFA / Amer center.'),

                        Forms\Components\DatePicker::make('fines_settled_at')
                            ->label('Settlement Date')
                            ->default(Carbon::today())
                            ->native(false)
                            ->required(),

                        Forms\Components\TextInput::make('fine_receipt_number')
                            ->label('Receipt / Ref #')
                            ->placeholder('e.g. GDRFA-2026-98124 or Amer Receipt #')
                            ->required(fn (callable $get) => $get('fine_settlement_type') === 'paid')
                            ->helperText('Government receipt or MB transaction ID.')
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('fine_receipt_attachment')
                            ->label('Receipt Document / Payment Proof (PDF or Image)')
                            ->disk('public')
                            ->directory('pro-fines-receipts')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(10240)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('fine_settlement_notes')
                            ->label('Settlement Notes / Remarks')
                            ->placeholder('e.g. Paid at Amer Center Al Twar, fines cleared in GDRFA system...')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                ])
                ->action(function (array $data) {
                    $this->record->settleFines(
                        type: $data['fine_settlement_type'] ?? 'paid',
                        amountPaid: (float) ($data['fine_amount_paid'] ?? 0),
                        receiptNumber: $data['fine_receipt_number'] ?? null,
                        attachment: $data['fine_receipt_attachment'] ?? null,
                        notes: $data['fine_settlement_notes'] ?? null,
                        settledAt: ! empty($data['fines_settled_at']) ? Carbon::parse($data['fines_settled_at']) : Carbon::now(),
                        userId: auth()->id()
                    );

                    Notification::make()
                        ->title('Fines Settled')
                        ->body("Overstay penalties for {$this->record->employee_name} have been cleared.")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('reopen_fines')
                ->label('Reopen Fines')
                ->icon('heroicon-m-arrow-path')
                ->color('danger')
                ->outlined()
                ->visible(fn (): bool => (bool) $this->record->fines_settled_at)
                ->requiresConfirmation()
                ->modalHeading('Reopen Overstay Fines?')
                ->modalDescription('This will cancel the fine settlement and re-enable dynamic overstay fine tracking.')
                ->action(function () {
                    $this->record->reopenFines();
                    Notification::make()
                        ->title('Settlement Cancelled')
                        ->body("Fines reopened for {$this->record->employee_name}.")
                        ->warning()
                        ->send();
                }),

            Actions\EditAction::make()
                ->icon('heroicon-m-pencil-square'),
        ];
    }
}
