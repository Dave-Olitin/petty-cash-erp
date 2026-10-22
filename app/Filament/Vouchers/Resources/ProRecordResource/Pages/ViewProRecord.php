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
                ->modalIcon('heroicon-o-banknotes')
                ->modalWidth('lg')
                ->form([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Placeholder::make('active_fines_info')
                            ->label('Active Overstay Fines')
                            ->content(fn (): string => 'AED ' . number_format($this->record->calculated_total_fines, 2) . ($this->record->days_overdue > 0 ? " ({$this->record->days_overdue} days overdue)" : ''))
                            ->columnSpanFull(),

                        Forms\Components\Select::make('fine_settlement_type')
                            ->label('Settlement Type')
                            ->options([
                                'paid'   => 'Paid (Full / Discounted Settlement)',
                                'waived' => 'Waived / Exempted by GDRFA',
                            ])
                            ->default('paid')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $state) {
                                if ($state === 'waived') {
                                    $set('fine_amount_paid', 0);
                                } else {
                                    $set('fine_amount_paid', $this->record->calculated_total_fines);
                                }
                            }),

                        Forms\Components\TextInput::make('fine_amount_paid')
                            ->label('Amount Paid (AED)')
                            ->prefix('AED')
                            ->numeric()
                            ->default(fn () => $this->record->calculated_total_fines)
                            ->required()
                            ->helperText('Actual amount paid at GDRFA / Amer center.'),

                        Forms\Components\TextInput::make('fine_receipt_number')
                            ->label('Receipt / Ref #')
                            ->placeholder('e.g. GDRFA-2026-98124 or Amer Receipt #')
                            ->required(fn (callable $get) => $get('fine_settlement_type') === 'paid')
                            ->helperText('Government receipt or MB transaction number.'),

                        Forms\Components\DatePicker::make('fines_settled_at')
                            ->label('Settlement Date')
                            ->default(Carbon::today())
                            ->native(false)
                            ->required(),

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
