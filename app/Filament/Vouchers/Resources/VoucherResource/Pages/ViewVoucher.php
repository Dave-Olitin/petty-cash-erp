<?php

namespace App\Filament\Vouchers\Resources\VoucherResource\Pages;

use App\Filament\Vouchers\Resources\VoucherResource;
use App\Models\Voucher;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewVoucher extends ViewRecord
{
    protected static string $resource = VoucherResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        // Record the view for the current user
        \App\Models\VoucherView::updateOrCreate(
            [
                'voucher_id' => $this->getRecord()->id,
                'user_id' => auth()->id(),
            ],
            [
                'updated_at' => now(),
            ]
        );
    }

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return $this->getRecord()->voucher_number ?? 'View Voucher';
    }

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();
        if (! $record) return [];

        return [
            Actions\Action::make('file_liquidation')
                ->label('File Liquidation')
                ->icon('heroicon-m-clipboard-document-check')
                ->color('warning')
                ->url(fn () => \App\Filament\Vouchers\Resources\LiquidationResource::getUrl('create') . '?voucher_id=' . $record->id)
                ->visible(fn () => $record->type === 'petty_cash'
                    && $record->status === 'paid'
                    && in_array($record->liquidation_status, ['pending', 'overdue'])
                ),

            Actions\Action::make('view_liquidation')
                ->label('View Liquidation')
                ->icon('heroicon-m-check-badge')
                ->color('success')
                ->url(fn () => $record->liquidation
                    ? \App\Filament\Vouchers\Resources\LiquidationResource::getUrl('view', ['record' => $record->liquidation->id])
                    : null)
                ->visible(fn () => $record->type === 'petty_cash'
                    && $record->liquidation_status === 'liquidated'
                    && $record->liquidation !== null
                ),

            Actions\Action::make('submit_page')
                ->label('Submit for Checking')
                ->icon('heroicon-m-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->visible(fn (): bool => $record->status === 'draft' && auth()->user()->can('voucher.submit'))
                ->action(function () use ($record) {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                        $lockedRecord = \App\Models\Voucher::lockForUpdate()->find($record->id);
                        if ($lockedRecord->status !== 'draft') {
                            Notification::make()->title('Voucher status has changed.')->danger()->send();
                            return;
                        }

                        $lockedRecord->update(['status' => 'pending_checker']);

                        $lockedRecord->load('user');
                        $checkers = User::role('Accountant')->get();
                        $checkers->each->notify(new \App\Notifications\VoucherStatusNotification($lockedRecord, 'submitted'));

                        Notification::make()
                            ->title('Voucher submitted successfully')
                            ->success()
                            ->send();

                        $record->refresh();
                    });
                }),

            Actions\Action::make('check_page')
                ->label('Verify & Forward')
                ->icon('heroicon-m-clipboard-document-check')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => $record->status === 'pending_checker' && auth()->user()->can('voucher.check'))
                ->action(function () use ($record) {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                        $lockedRecord = \App\Models\Voucher::lockForUpdate()->find($record->id);
                        if ($lockedRecord->status !== 'pending_checker') {
                            Notification::make()->title('Voucher status changed by another user.')->danger()->send();
                            return;
                        }

                        $lockedRecord->update([
                            'status'               => 'pending_approver',
                            'current_approval_step' => 1,
                        ]);
                        $lockedRecord->approvals()->create([
                            'user_id' => auth()->id(),
                            'action'  => 'checked',
                        ]);

                        $lockedRecord->load('user');

                        $firstStep = \App\Models\ApprovalWorkflow::getApproverAtStep(1);
                        if ($firstStep && $firstStep->user) {
                            $firstStep->user->notify(new \App\Notifications\VoucherStatusNotification($lockedRecord, 'checked'));
                        } else {
                            User::role('Approver')->get()->each->notify(new \App\Notifications\VoucherStatusNotification($lockedRecord, 'checked'));
                        }

                        Notification::make()
                            ->title('Voucher forwarded to Approver')
                            ->success()
                            ->send();

                        $record->refresh();
                    });
                }),

            Actions\Action::make('approve_page')
                ->label('Approve')
                ->icon('heroicon-m-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(function () use ($record): bool {
                    if ($record->status !== 'pending_approver') return false;
                    if (!auth()->user()->can('voucher.approve')) return false;

                    if (\App\Models\ApprovalWorkflow::isConfigured()) {
                        $step = \App\Models\ApprovalWorkflow::getApproverAtStep((int) ($record->current_approval_step ?? 1));
                        return $step && $step->user_id == auth()->id();
                    }

                    return auth()->user()->hasRole('Approver');
                })
                ->action(function () use ($record) {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                        $lockedRecord = \App\Models\Voucher::lockForUpdate()->find($record->id);
                        
                        if ($lockedRecord->status !== 'pending_approver' || $lockedRecord->current_approval_step !== $record->current_approval_step) {
                            Notification::make()->title('Voucher was modified by another user. Please refresh.')->danger()->send();
                            return;
                        }

                        if (\App\Models\ApprovalWorkflow::isConfigured()) {
                            $step = \App\Models\ApprovalWorkflow::getApproverAtStep((int) ($lockedRecord->current_approval_step ?? 1));
                            if (!$step || $step->user_id != auth()->id()) {
                                Notification::make()->title('Unauthorized: You are not the correct approver for this step.')->danger()->send();
                                return;
                            }
                        } else {
                            if (!auth()->user()->hasRole('Approver')) {
                                Notification::make()->title('Unauthorized: You lack Approver privileges.')->danger()->send();
                                return;
                            }
                        }

                        $lockedRecord->load('user');
                        $currentStep  = (int) ($lockedRecord->current_approval_step ?? 1);
                        $totalSteps   = \App\Models\ApprovalWorkflow::totalSteps();

                        $lockedRecord->approvals()->create([
                            'user_id' => auth()->id(),
                            'action'  => 'approved',
                            'comments' => \App\Models\ApprovalWorkflow::getApproverAtStep($currentStep)?->label
                                ? 'Approved as ' . \App\Models\ApprovalWorkflow::getApproverAtStep($currentStep)->label
                                : null,
                        ]);

                        $nextStep = $currentStep + 1;

                        if ($totalSteps > 0 && $nextStep <= $totalSteps) {
                            $lockedRecord->update(['current_approval_step' => $nextStep]);

                            $next = \App\Models\ApprovalWorkflow::getApproverAtStep($nextStep);
                            if ($next && $next->user) {
                                $next->user->notify(new \App\Notifications\VoucherStatusNotification($lockedRecord, 'checked'));
                            }

                            Notification::make()
                                ->title('Step ' . $currentStep . ' approved — forwarded to next approver')
                                ->success()
                                ->send();
                        } else {
                            $lockedRecord->update(['status' => 'approved', 'current_approval_step' => null]);

                            $lockedRecord->user->notify(new \App\Notifications\VoucherStatusNotification($lockedRecord, 'approved'));
                            User::role('Accountant')->get()->each->notify(new \App\Notifications\VoucherStatusNotification($lockedRecord, 'approved'));

                            Notification::make()
                                ->title('Voucher fully approved')
                                ->success()
                                ->send();
                        }

                        $record->refresh();
                    });
                }),

            Actions\Action::make('reject_page')
                ->label('Return / Reject')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->form([
                    Forms\Components\Textarea::make('comments')->required()->label('Reason for Rejection'),
                ])
                ->visible(fn (): bool => in_array($record->status, ['pending_checker', 'pending_approver']) && auth()->user()->can('voucher.reject'))
                ->action(function (array $data) use ($record) {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data) {
                        $lockedRecord = \App\Models\Voucher::lockForUpdate()->find($record->id);
                        
                        if (!in_array($lockedRecord->status, ['pending_checker', 'pending_approver'])) {
                            Notification::make()->title('Voucher status changed by another user.')->danger()->send();
                            return;
                        }

                        $lockedRecord->update(['status' => 'rejected']);
                        $lockedRecord->approvals()->create([
                            'user_id'  => auth()->id(),
                            'action'   => 'rejected',
                            'comments' => $data['comments'],
                        ]);

                        $lockedRecord->load('user');
                        $lockedRecord->user->notify(new \App\Notifications\VoucherStatusNotification($lockedRecord, 'rejected', $data['comments']));

                        Notification::make()
                            ->title('Voucher rejected')
                            ->danger()
                            ->send();

                        $record->refresh();
                    });
                }),

            Actions\Action::make('update_attachments_page')
                ->label('Manage Attachments & Notes')
                ->icon('heroicon-o-paper-clip')
                ->color('info')
                ->visible(fn (): bool => $record->status === 'paid' && (auth()->user()->hasAnyRole(['Super Admin', 'Admin', 'Accountant', 'Approver']) || auth()->id() === $record->user_id))
                ->fillForm(fn (): array => [
                    'attachment_paths' => $record->attachment_paths,
                    'description'      => $record->description,
                ])
                ->form([
                    Forms\Components\FileUpload::make('attachment_paths')
                        ->label('Upload Receipts / Invoices')
                        ->multiple()
                        ->directory('voucher-attachments')
                        ->maxFiles(5)
                        ->maxSize(10240)
                        ->openable()
                        ->downloadable()
                        ->panelLayout('grid'),
                    Forms\Components\Textarea::make('description')
                        ->label('Description / Follow-up Notes')
                        ->rows(3),
                ])
                ->action(function (array $data) use ($record) {
                    $record->update([
                        'attachment_paths' => $data['attachment_paths'] ?? null,
                        'description'      => $data['description'] ?? null,
                    ]);

                    activity()
                        ->performedOn($record)
                        ->causedBy(auth()->user())
                        ->log('Attachments and descriptions updated post-disbursement via View page');

                    Notification::make()->title('Attachments and notes securely updated')->success()->send();
                    $record->refresh();
                }),

            Actions\Action::make('mark_paid_page')
                ->label(fn () => $record->type === 'receipt' ? 'Collect Funds' : 'Disburse Funds')
                ->icon('heroicon-m-banknotes')
                ->color('success')
                ->modalHeading(fn () => in_array($record->type, ['payment', 'bank_encashment']) ? 'Disburse via Cheque / Bank' : ($record->type === 'receipt' ? 'Collect Cash Denominations' : 'Disburse Cash Denominations'))
                ->modalSubmitActionLabel('Process & Mark Paid')
                ->mountUsing(fn (Forms\Form $form) => $form->fill([
                    'voucher_amount' => $record->amount,
                    'multiple_payments' => $record->multiple_payments ?? [
                        [
                            'cheque_no' => $record->cheque_no,
                            'cheque_date' => $record->cheque_date,
                            'bank' => $record->bank,
                            'amount' => $record->amount,
                        ]
                    ],
                ]))
                ->form([
                    Forms\Components\Hidden::make('voucher_amount'),
                    
                    Forms\Components\Repeater::make('multiple_payments')
                        ->label('Payment References')
                        ->schema([
                            Forms\Components\Grid::make(4)->schema([
                                Forms\Components\TextInput::make('cheque_no')
                                    ->label('Ref/Cheque #')
                                    ->required(),
                                Forms\Components\DatePicker::make('cheque_date')
                                    ->label('Date')
                                    ->required()
                                    ->native(false),
                                Forms\Components\TextInput::make('bank')
                                    ->label('Bank')
                                    ->required(),
                                Forms\Components\TextInput::make('amount')
                                    ->label('Amount')
                                    ->numeric()
                                    ->prefix('AED')
                                    ->required()
                                    ->live(debounce: 500),
                            ]),
                        ])
                        ->default([])
                        ->reorderable(false)
                        ->itemLabel(fn (array $state): ?string => 
                            ($state['cheque_no'] ?? 'Payment') . 
                            ($state['amount'] ? ' — AED ' . number_format((float) $state['amount'], 2) : '')
                        )
                        ->hint(function (Forms\Get $get) {
                            $payments = $get('multiple_payments') ?? [];
                            $total = collect($payments)->sum(fn($p) => (float)($p['amount'] ?? 0));
                            $target = (float)$get('voucher_amount');
                            
                            if (abs($total - $target) < 0.01) {
                                return new \Illuminate\Support\HtmlString('<span class="text-success-600 font-bold">✅ Total Matches (AED ' . number_format($total, 2) . ')</span>');
                            }
                            return new \Illuminate\Support\HtmlString('<span class="text-danger-600 font-bold">⚠️ Total (AED ' . number_format($total, 2) . ') must equal AED ' . number_format($target, 2) . '</span>');
                        }),

                    Forms\Components\Section::make('Physical Cash Breakdown')
                        ->description(fn () => new \Illuminate\Support\HtmlString("Voucher Total: <strong>AED " . number_format((float) $record->amount, 2) . "</strong> &mdash; Please count the currency notes and coins."))
                        ->visible(fn () => !in_array($record->type, ['payment', 'bank_encashment']))
                        ->schema([
                            \Filament\Forms\Components\Fieldset::make('Currency Notes (Bills)')
                                ->schema([
                                    Forms\Components\TextInput::make('bill_1000')->label('1000s')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('bill_500')->label('500s')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('bill_200')->label('200s')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('bill_100')->label('100s')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('bill_50')->label('50s')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('bill_20')->label('20s')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('bill_10')->label('10s')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('bill_5')->label('5s')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                ])->columns(4),

                            \Filament\Forms\Components\Fieldset::make('Coins')
                                ->schema([
                                    Forms\Components\TextInput::make('coin_1')->label('1.00')->numeric()->default(0)->live()->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('coin_0_50')->label('0.50')->numeric()->default(0)->live()->step('1')->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                    Forms\Components\TextInput::make('coin_0_25')->label('0.25')->numeric()->default(0)->live()->step('1')->minValue(0)
                                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                ])->columns(3),

                            \Filament\Forms\Components\Section::make()
                                ->schema([
                                    Forms\Components\Grid::make(3)->schema([
                                        Forms\Components\TextInput::make('prior_deduction')
                                            ->label('Cash Advance / Prior Deduction')
                                            ->numeric()
                                            ->default(0)
                                            ->live()
                                            ->prefix('AED')
                                            ->extraInputAttributes(['class' => 'font-bold'])
                                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),

                                        Forms\Components\TextInput::make('change_given')
                                            ->label('Cash Return / Change Due')
                                            ->numeric()
                                            ->default(0)
                                            ->readOnly()
                                            ->extraInputAttributes(['class' => 'font-bold text-lg text-primary-600'])
                                            ->helperText('Change automatically calculated based on tendered amount.'),
                                        
                                        Forms\Components\Placeholder::make('net_summary')
                                            ->label('Financial Summary')
                                            ->content(function (Forms\Get $get) use ($record) {
                                                $tendered = ((int) $get('bill_1000') * 1000) + ((int) $get('bill_500') * 500) + ((int) $get('bill_200') * 200) + ((int) $get('bill_100') * 100) + ((int) $get('bill_50') * 50) + ((int) $get('bill_20') * 20) + ((int) $get('bill_10') * 10) + ((int) $get('bill_5') * 5) + ((int) $get('coin_1') * 1) + ((int) $get('coin_0_50') * 0.50) + ((int) $get('coin_0_25') * 0.25);
                                                $target   = (float) $record->amount;
                                                $deduction = (float) ($get('prior_deduction') ?? 0);
                                                $netCashTarget = max(0, round($target - $deduction, 2));
                                                
                                                $change   = (float) ($get('change_given') ?? 0);
                                                $netPhysical = round($tendered - $change, 2);
                                                $diff     = round($netPhysical - $netCashTarget, 2);

                                                $panelColor = match(true) {
                                                    abs($diff) < 0.01 => 'background-color:#f0fdf4; border-color:#86efac;',
                                                    $diff < 0         => 'background-color:#fef2f2; border-color:#fca5a5;',
                                                    default           => 'background-color:#eff6ff; border-color:#93c5fd;',
                                                };
                                                $statusBadge = match(true) {
                                                    abs($diff) < 0.01 => '<span style="padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;background:#dcfce7;color:#15803d;">✓ BALANCED</span>',
                                                    $diff < 0         => '<span style="padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;background:#fee2e2;color:#b91c1c;">⚠ SHORT BY AED '.number_format(abs($diff), 2).'</span>',
                                                    $diff > 0         => '<span style="padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;background:#dbeafe;color:#1d4ed8;">ℹ EXCESS BY AED '.number_format($diff, 2).'</span>',
                                                };

                                                return new \Illuminate\Support\HtmlString(
                                                    "<div style='padding:12px;border-radius:8px;border:1px solid;{$panelColor}'>" .
                                                    "<div style='display:grid;grid-template-columns:1fr 1fr;gap:4px 0;font-size:13px;'>" .
                                                    "<div style='color:#6b7280;'>Voucher Total (Gross):</div><div style='text-align:right;font-family:monospace;'>AED " . number_format($target, 2) . "</div>" .
                                                    "<div style='color:#6b7280;'>Less: Cash Advance:</div><div style='text-align:right;font-family:monospace; color:#b91c1c;'>- AED " . number_format($deduction, 2) . "</div>" .
                                                    "<div style='grid-column:span 2;margin:4px 0;border-top:1px dashed #d1d5db;'></div>" .
                                                    "<div style='font-weight:700;'>Net Cash to Pay:</div><div style='text-align:right;font-family:monospace;font-weight:700; font-size:14px;'>AED " . number_format($netCashTarget, 2) . "</div>" .
                                                    "<div style='color:#6b7280;'>Physical Cash:</div><div style='text-align:right;font-family:monospace;'>AED " . number_format($netPhysical, 2) . "</div>" .
                                                    "<div style='grid-column:span 2;margin:6px 0;border-top:1px solid #d1d5db;'></div>" .
                                                    "<div style='font-weight:700;'>Verification:</div><div style='text-align:right;'>{$statusBadge}</div>" .
                                                    "</div>" .
                                                    "</div>"
                                                );
                                            }),
                                    ]),

                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\Textarea::make('remarks')
                                            ->label('Remarks / Notes')
                                            ->placeholder('Brief notes about physical cash state if needed...')
                                            ->columnSpanFull(),
                                        Forms\Components\Toggle::make('is_change_received')
                                            ->label('Exact change has been verified and accounted for.')
                                            ->inline(false)
                                            ->default(true)
                                            ->visible(fn (Forms\Get $get) => (float)($get('change_given') ?? 0) > 0)
                                            ->columnSpanFull(),
                                    ]),
                                ])
                        ])
                ])
                ->visible(fn (): bool => in_array($record->status, ['pending_checker', 'pending_approver', 'approved']) && auth()->user()->can('voucher.pay'))
                ->action(function (array $data) use ($record) {
                    if (in_array($record->type, ['payment', 'bank_encashment'])) {
                        $payments = $data['multiple_payments'] ?? [];
                        $total = collect($payments)->sum(fn($p) => (float)($p['amount'] ?? 0));
                        
                        if (abs($total - (float)$record->amount) > 0.01) {
                            Notification::make()
                                ->title('Payment total mismatch')
                                ->body('The sum of multiple payments (AED ' . number_format($total, 2) . ') must equal the voucher total (AED ' . number_format($record->amount, 2) . ').')
                                ->danger()
                                ->send();
                            return;
                        }

                        // Sync legacy fields with the first payment for backward compatibility
                        $first = $payments[0] ?? null;

                        $record->update([
                            'multiple_payments' => $payments,
                            'cheque_no' => $first['cheque_no'] ?? null,
                            'cheque_date' => $first['cheque_date'] ?? null,
                            'bank' => $first['bank'] ?? null,
                        ]);
                        
                        $error = app(\App\Services\VoucherApprovalService::class)->markPaid($record, auth()->user());
                        if ($error) {
                            Notification::make()->title($error)->danger()->send();
                            return;
                        }
                        Notification::make()->title('Voucher successfully disbursed via Bank/Cheque')->success()->send();
                        $record->refresh();
                        return;
                    }

                    $tendered = ((int) ($data['bill_1000'] ?? 0) * 1000) 
                        + ((int) ($data['bill_500'] ?? 0) * 500)
                        + ((int) ($data['bill_200'] ?? 0) * 200)
                        + ((int) ($data['bill_100'] ?? 0) * 100)
                        + ((int) ($data['bill_50'] ?? 0) * 50)
                        + ((int) ($data['bill_20'] ?? 0) * 20)
                        + ((int) ($data['bill_10'] ?? 0) * 10)
                        + ((int) ($data['bill_5'] ?? 0) * 5)
                        + ((int) ($data['coin_1'] ?? 0) * 1)
                        + ((int) ($data['coin_0_50'] ?? 0) * 0.50)
                        + ((int) ($data['coin_0_25'] ?? 0) * 0.25);

                    $changeGiven = round((float) ($data['change_given'] ?? 0), 2);
                    $deduction   = round((float) ($data['prior_deduction'] ?? 0), 2);
                    $netPhysical = round($tendered - $changeGiven, 2);
                    $targetWithDeduction = round((float)$record->amount - $deduction, 2);

                    if (abs($netPhysical - $targetWithDeduction) > 0.01) {
                        Notification::make()
                            ->title('Denomination validation failed')
                            ->danger()
                            ->body('Net Physical Cash (Tendered − Change) must equal the Net target (Voucher − Deduction). ' .
                                   'You tendered AED ' . number_format($tendered, 2) .
                                   ', change back AED ' . number_format($changeGiven, 2) .
                                   ', net physical AED ' . number_format($netPhysical, 2) .
                                   ' ≠ net target AED ' . number_format($targetWithDeduction, 2) . '.')
                            ->send();
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'bill_1000' => 'Net amount mismatch.'
                        ]);
                    }

                    // Save denominations securely
                    $record->denominations()->create([
                        'bill_1000'    => $data['bill_1000'] ?: 0,
                        'bill_500'     => $data['bill_500'] ?: 0,
                        'bill_200'     => $data['bill_200'] ?: 0,
                        'bill_100'     => $data['bill_100'] ?: 0,
                        'bill_50'      => $data['bill_50'] ?: 0,
                        'bill_20'      => $data['bill_20'] ?: 0,
                        'bill_10'      => $data['bill_10'] ?: 0,
                        'bill_5'       => $data['bill_5'] ?: 0,
                        'coin_1'       => $data['coin_1'] ?: 0,
                        'coin_0_50'    => $data['coin_0_50'] ?: 0,
                        'coin_0_25'    => $data['coin_0_25'] ?: 0,
                        'total_amount' => $tendered,
                        'change_given' => $changeGiven,
                        'prior_deduction' => $deduction,
                        'is_change_received' => $data['is_change_received'] ?? true,
                        'remarks'      => $data['remarks'] ?? null,
                    ]);

                    $error = app(\App\Services\VoucherApprovalService::class)->markPaid($record, auth()->user());
                    if ($error) {
                        Notification::make()->title($error)->danger()->send();
                        return;
                    }
                    Notification::make()->title($record->type === 'receipt' ? 'Receipt funds safely collected' : 'Voucher funds safely disbursed')->success()->send();
                    $record->refresh();
                }),
                
            Actions\Action::make('log_change_returned')
                ->label('Log Change Returned')
                ->icon('heroicon-m-hand-raised')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirm Change Received')
                ->modalDescription('Confirm that the exact change for this voucher has been securely placed into the petty cash box.')
                ->visible(function () use ($record) {
                    // Only show for Payment Vouchers, because Petty Cash uses the Liquidation flow for change, and Receipts don't give change back to the company.
                    if ($record->type === 'petty_cash') return false;
                    
                    $denom = $record->denominations()->first();
                    return $denom && $denom->change_given > 0 && !$denom->is_change_received && auth()->user()->can('voucher.pay');
                })
                ->action(function () use ($record) {
                    $denom = $record->denominations()->first();
                    if ($denom) {
                        $denom->update(['is_change_received' => true]);
                        \Filament\Notifications\Notification::make()->title('Exact change successfully logged as returned to float.')->success()->send();
                    }
                    $record->refresh();
                }),
            Actions\Action::make('override_denominations')
                ->label('Edit / Override')
                ->icon('heroicon-m-exclamation-triangle')
                ->color('warning')
                ->modalHeading('Override Paid Denominations')
                ->modalDescription('WARNING: You are altering the immutable cash history. This edit will be permanently tracked on the Activity Timeline. Please ensure absolute accuracy.')
                ->modalSubmitActionLabel('Override Exact Cash')
                ->requiresConfirmation()
                ->visible(function () use ($record) {
                    return $record->denominations()->exists() && auth()->user()->hasAnyRole(['Super Admin', 'Admin', 'Accountant', 'Head Office']);
                })
                ->mountUsing(function (Forms\Form $form) use ($record) {
                    $denom = $record->denominations()->first();
                    if ($denom) {
                        $fill = $denom->only(['bill_1000', 'bill_500', 'bill_200', 'bill_100', 'bill_50', 'bill_20', 'bill_10', 'bill_5', 'coin_1', 'coin_0_50', 'coin_0_25', 'prior_deduction', 'change_given', 'is_change_received', 'remarks']);
                        $fill['voucher_amount'] = $record->amount;
                        $form->fill($fill);
                    }
                })
                ->form([
                    Forms\Components\Hidden::make('voucher_amount'),
                    Forms\Components\Textarea::make('override_reason')
                        ->label('Reason for Override Audit Trail')
                        ->required()
                        ->placeholder('Explain why this paid voucher is being retroactively changed. This will be publicly logged on the timeline.'),
                    Forms\Components\Section::make('Corrected Cash Handover')
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('bill_1000')->label('1000 Bills')->numeric()->default(0)->live()->minValue(0)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                Forms\Components\TextInput::make('bill_500')->label('500 Bills')->numeric()->default(0)->live()->minValue(0)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                Forms\Components\TextInput::make('bill_200')->label('200 Bills')->numeric()->default(0)->live()->minValue(0)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                Forms\Components\TextInput::make('bill_100')->label('100 Bills')->numeric()->default(0)->live()->minValue(0)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                Forms\Components\TextInput::make('bill_50')->label('50 Bills')->numeric()->default(0)->live()->minValue(0)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                Forms\Components\TextInput::make('bill_20')->label('20 Bills')->numeric()->default(0)->live()->minValue(0)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                Forms\Components\TextInput::make('bill_10')->label('10 Bills')->numeric()->default(0)->live()->minValue(0)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                Forms\Components\TextInput::make('bill_5')->label('5 Bills')->numeric()->default(0)->live()->minValue(0)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                Forms\Components\TextInput::make('coin_1')->label('1 Coins')->numeric()->default(0)->live()->minValue(0)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                Forms\Components\TextInput::make('coin_0_50')->label('0.50 Coins')->numeric()->default(0)->live()->step('1')->minValue(0)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                Forms\Components\TextInput::make('coin_0_25')->label('0.25 Coins')->numeric()->default(0)->live()->step('1')->minValue(0)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                            ]),
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('prior_deduction')
                                    ->label('Cash Advance / Prior Deduction')
                                    ->numeric()
                                    ->default(0)
                                    ->live()
                                    ->prefix('AED')
                                    ->extraInputAttributes(['class' => 'font-bold'])
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => \App\Filament\Vouchers\Resources\VoucherResource::recomputeChange($get, $set)),
                                Forms\Components\TextInput::make('change_given')
                                    ->label('Change Received Back (AED)')
                                    ->numeric()
                                    ->default(0)
                                    ->readOnly()
                                    ->helperText('Auto-calculated.'),
                                Forms\Components\Placeholder::make('net_summary')
                                    ->label('Live Summary')
                                    ->columnSpanFull()
                                    ->content(function (Forms\Get $get) use ($record) {
                                        $total  = ((int) $get('bill_1000') * 1000) + ((int) $get('bill_500') * 500) + ((int) $get('bill_200') * 200) + ((int) $get('bill_100') * 100) + ((int) $get('bill_50') * 50) + ((int) $get('bill_20') * 20) + ((int) $get('bill_10') * 10) + ((int) $get('bill_5') * 5) + ((int) $get('coin_1') * 1) + ((int) $get('coin_0_50') * 0.50) + ((int) $get('coin_0_25') * 0.25);
                                        $change = (float) ($get('change_given') ?? 0);
                                        $deduction = (float) ($get('prior_deduction') ?? 0);
                                        $net    = $total - $change;
                                        return new \Illuminate\Support\HtmlString(
                                            "<div style='line-height:1.9; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;'>" .
                                            "💵 <strong>Cash Tendered:</strong> AED " . number_format($total, 2) . "<br>" .
                                            "🔄 <strong>Change Back:</strong> AED " . number_format($change, 2) . "<br>" .
                                            "📉 <strong>Prior Deduction:</strong> AED " . number_format($deduction, 2) . "<br>" .
                                            "✅ <strong>Net Disbursed physically:</strong> AED " . number_format($net, 2) . "<br>" .
                                            "🎯 <strong>Voucher Target:</strong> AED " . number_format(max(0, $record->amount - $deduction), 2) .
                                            "</div>"
                                        );
                                    }),
                                Forms\Components\Textarea::make('remarks')->label('Remarks / Notes')->columnSpanFull(),
                                Forms\Components\Toggle::make('is_change_received')
                                    ->label('Employee has already returned the exact change for this transaction.')
                                    ->default(true)
                                    ->visible(fn (Forms\Get $get) => (float)($get('change_given') ?? 0) > 0)
                                    ->columnSpanFull(),
                            ]),
                        ])
                ])
                ->action(function (array $data) use ($record) {
                    $denom = $record->denominations()->first();
                    if (!$denom) return;

                    $tendered = ((int) ($data['bill_1000'] ?? 0) * 1000) + ((int) ($data['bill_500'] ?? 0) * 500) + ((int) ($data['bill_200'] ?? 0) * 200) + ((int) ($data['bill_100'] ?? 0) * 100) + ((int) ($data['bill_50'] ?? 0) * 50) + ((int) ($data['bill_20'] ?? 0) * 20) + ((int) ($data['bill_10'] ?? 0) * 10) + ((int) ($data['bill_5'] ?? 0) * 5) + ((int) ($data['coin_1'] ?? 0) * 1) + ((int) ($data['coin_0_50'] ?? 0) * 0.50) + ((int) ($data['coin_0_25'] ?? 0) * 0.25);
                    $changeGiven = round((float) ($data['change_given'] ?? 0), 2);
                    $deduction   = round((float) ($data['prior_deduction'] ?? 0), 2);
                    $netPhysical = round($tendered - $changeGiven, 2);
                    $targetWithDeduction = round((float)$record->amount - $deduction, 2);

                    if (abs($netPhysical - $targetWithDeduction) > 0.01) {
                        \Filament\Notifications\Notification::make()->title('Denomination validation failed')->danger()->body('Net physical cash must equal voucher target amount minus deduction.')->send();
                        throw \Illuminate\Validation\ValidationException::withMessages(['bill_1000' => 'Net amount mismatch.']);
                    }

                    $fields = ['bill_1000', 'bill_500', 'bill_200', 'bill_100', 'bill_50', 'bill_20', 'bill_10', 'bill_5', 'coin_1', 'coin_0_50', 'coin_0_25', 'prior_deduction', 'change_given', 'is_change_received'];
                    $changes = [];
                    foreach ($fields as $field) {
                        $oldVal = $denom->{$field};
                        $newVal = $data[$field] ?? ($field === 'is_change_received' ? true : 0);
                        if ($oldVal != $newVal) {
                            $name = str_replace('_', ' ', \Illuminate\Support\Str::title(str_replace('bill_', '', $field)));
                            $changes[] = "{$name}: {$oldVal} -> {$newVal}";
                        }
                    }

                    if (empty($changes)) {
                        \Filament\Notifications\Notification::make()->title('No changes made.')->warning()->send();
                        return;
                    }

                    $denom->update([
                        'bill_1000' => $data['bill_1000'] ?: 0, 'bill_500' => $data['bill_500'] ?: 0, 'bill_200' => $data['bill_200'] ?: 0, 'bill_100' => $data['bill_100'] ?: 0, 'bill_50' => $data['bill_50'] ?: 0, 'bill_20' => $data['bill_20'] ?: 0, 'bill_10' => $data['bill_10'] ?: 0, 'bill_5' => $data['bill_5'] ?: 0, 'coin_1' => $data['coin_1'] ?: 0, 'coin_0_50' => $data['coin_0_50'] ?: 0, 'coin_0_25' => $data['coin_0_25'] ?: 0, 'total_amount' => $tendered, 'change_given' => $changeGiven, 'prior_deduction' => $deduction, 'is_change_received' => $data['is_change_received'] ?? true, 'remarks' => $data['remarks'] ?? null,
                    ]);

                    activity()
                        ->performedOn($record)
                        ->causedBy(auth()->user())
                        ->event('updated')
                        ->log("Denominations Overridden\nReason: {$data['override_reason']}\nMutations: " . implode(' | ', $changes));

                    \Filament\Notifications\Notification::make()->title('Denominations successfully permanently overridden.')->success()->send();
                    $record->refresh();
                }),
                
            Actions\Action::make('void_only')
                ->label('Void Only')
                ->icon('heroicon-m-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Void Transaction')
                ->modalDescription('WARNING: This will permanently void this disbursed voucher and reverse its accounting. It will NOT create a new draft.')
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Reason for Voiding')
                        ->required()
                        ->placeholder('e.g. Transaction was fully cancelled/refunded.'),
                ])
                ->visible(fn (): bool => $record->status === 'paid' && auth()->user()->can('voucher.void_only'))
                ->action(function (array $data) use ($record) {
                    $result = app(\App\Services\VoucherApprovalService::class)->voidVoucher($record, auth()->user(), $data['reason'], false);
                    
                    if (is_string($result)) {
                        Notification::make()->title($result)->danger()->send();
                        return;
                    }
                    
                    Notification::make()->title("Voucher successfully voided.")->success()->send();
                    $record->refresh();
                }),
                
            Actions\Action::make('void_and_reissue')
                ->label('Void & Reissue')
                ->icon('heroicon-m-arrow-path-rounded-square')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Void & Reissue Voucher')
                ->modalDescription('WARNING: This will permanently void this disbursed voucher, reverse its accounting, and clone a new draft for you to fix and re-submit.')
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Reason for Voiding')
                        ->required()
                        ->placeholder('e.g. Incorrect amount. Needs to be 50.00 instead of 500.00.'),
                ])
                ->visible(fn (): bool => $record->status === 'paid' && auth()->user()->can('voucher.void_and_reissue'))
                ->action(function (array $data) use ($record) {
                    $result = app(\App\Services\VoucherApprovalService::class)->voidVoucher($record, auth()->user(), $data['reason'], true);
                    
                    if (is_string($result)) {
                        Notification::make()->title($result)->danger()->send();
                        return;
                    }
                    
                    Notification::make()->title("Voucher voided. Cloned as {$result->voucher_number}.")->success()->send();
                    
                    redirect()->to(\App\Filament\Vouchers\Resources\VoucherResource::getUrl('edit', ['record' => $result->id]));
                }),
                
            Actions\EditAction::make()
                ->visible(fn (): bool => $record->status === 'draft' && auth()->user()->can('voucher.edit')),
        ];
    }
}
