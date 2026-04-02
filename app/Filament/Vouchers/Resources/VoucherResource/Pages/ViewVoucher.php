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
                        ->maxSize(10240),
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
                ->modalHeading(fn () => $record->type === 'payment' ? 'Disburse via Cheque / Bank' : ($record->type === 'receipt' ? 'Collect Cash Denominations' : 'Disburse Cash Denominations'))
                ->modalSubmitActionLabel('Process & Mark Paid')
                ->mountUsing(function (Forms\Form $form) use ($record) {
                    $form->fill([
                        'voucher_amount' => $record->amount,
                        'cheque_no'      => $record->cheque_no,
                        'cheque_date'    => $record->cheque_date,
                        'bank'           => $record->bank,
                    ]);
                })
                ->form([
                    Forms\Components\Hidden::make('voucher_amount'),
                    
                    Forms\Components\Section::make('Cheque / Bank Transfer Details')
                        ->description('Confirm the final payment references for this voucher.')
                        ->visible(fn () => $record->type === 'payment')
                        ->schema([
                            Forms\Components\TextInput::make('cheque_no')
                                ->label('Cheque / Ref No.')
                                ->maxLength(50),
                            Forms\Components\DatePicker::make('cheque_date')
                                ->label('Cheque Date')
                                ->native(false),
                            Forms\Components\TextInput::make('bank')
                                ->label('Bank Name')
                                ->maxLength(100),
                        ])->columns(3),

                    Forms\Components\Section::make('Cash Handover / Collection')
                        ->description(fn () => new \Illuminate\Support\HtmlString("Voucher Amount: <strong>AED " . number_format((float) $record->amount, 2) . "</strong> &mdash; Enter the bills/coins you hand over. Change back is auto-calculated."))
                        ->visible(fn () => $record->type !== 'payment')
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
                                Forms\Components\TextInput::make('change_given')
                                    ->label('Change Received Back (AED)')
                                    ->numeric()
                                    ->default(0)
                                    ->readOnly()
                                    ->helperText('Auto-calculated: Cash tendered minus voucher amount. Adjust denominations above to update.'),
                                Forms\Components\Placeholder::make('net_summary')
                                    ->label('Live Summary')
                                    ->content(function (Forms\Get $get) {
                                        $total  = ((int) $get('bill_1000') * 1000) + ((int) $get('bill_500') * 500) + ((int) $get('bill_200') * 200) + ((int) $get('bill_100') * 100) + ((int) $get('bill_50') * 50) + ((int) $get('bill_20') * 20) + ((int) $get('bill_10') * 10) + ((int) $get('bill_5') * 5) + ((int) $get('coin_1') * 1) + ((int) $get('coin_0_50') * 0.50) + ((int) $get('coin_0_25') * 0.25);
                                        $change = (float) ($get('change_given') ?? 0);
                                        $net    = $total - $change;
                                        return new \Illuminate\Support\HtmlString(
                                            "<div style='line-height:1.9'>" .
                                            "💵 <strong>Cash Tendered:</strong> AED " . number_format($total, 2) . "<br>" .
                                            "🔄 <strong>Change Back:</strong> AED " . number_format($change, 2) . "<br>" .
                                            "✅ <strong>Net Disbursed:</strong> AED " . number_format($net, 2) .
                                            "</div>"
                                        );
                                    }),
                                Forms\Components\Textarea::make('remarks')
                                    ->label('Remarks / Notes')
                                    ->placeholder('Optional: explain differences in denominations or general notes.')
                                    ->columnSpanFull(),
                                Forms\Components\Toggle::make('is_change_received')
                                    ->label('Employee has already returned the exact change for this transaction.')
                                    ->default(true)
                                    ->visible(fn (Forms\Get $get) => (float)($get('change_given') ?? 0) > 0)
                                    ->columnSpanFull(),
                            ]),
                        ])
                ])
                ->visible(fn (): bool => in_array($record->status, ['pending_checker', 'pending_approver', 'approved']) && auth()->user()->can('voucher.pay'))
                ->action(function (array $data) use ($record) {
                    if ($record->type === 'payment') {
                        $record->update([
                            'cheque_no' => $data['cheque_no'] ?? null,
                            'cheque_date' => $data['cheque_date'] ?? null,
                            'bank' => $data['bank'] ?? null,
                        ]);
                        
                        $error = app(\App\Services\VoucherApprovalService::class)->markPaid($record, auth()->user());
                        if ($error) {
                            Notification::make()->title($error)->danger()->send();
                            return;
                        }
                        Notification::make()->title('Payment Voucher successfully disbursed')->success()->send();
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
                    $net         = round($tendered - $changeGiven, 2);

                    if ($net !== round((float) $record->amount, 2)) {
                        Notification::make()
                            ->title('Denomination validation failed')
                            ->danger()
                            ->body('Net amount (Cash Tendered − Change) must equal the voucher amount. ' .
                                   'You tendered AED ' . number_format($tendered, 2) .
                                   ', change back AED ' . number_format($changeGiven, 2) .
                                   ', net AED ' . number_format($net, 2) .
                                   ' ≠ voucher AED ' . number_format((float) $record->amount, 2) . '.')
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
                        $fill = $denom->only(['bill_1000', 'bill_500', 'bill_200', 'bill_100', 'bill_50', 'bill_20', 'bill_10', 'bill_5', 'coin_1', 'coin_0_50', 'coin_0_25', 'change_given', 'is_change_received', 'remarks']);
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
                                Forms\Components\TextInput::make('change_given')
                                    ->label('Change Received Back (AED)')
                                    ->numeric()
                                    ->default(0)
                                    ->readOnly()
                                    ->helperText('Auto-calculated.'),
                                Forms\Components\Placeholder::make('net_summary')
                                    ->label('Live Summary')
                                    ->content(function (Forms\Get $get) {
                                        $total  = ((int) $get('bill_1000') * 1000) + ((int) $get('bill_500') * 500) + ((int) $get('bill_200') * 200) + ((int) $get('bill_100') * 100) + ((int) $get('bill_50') * 50) + ((int) $get('bill_20') * 20) + ((int) $get('bill_10') * 10) + ((int) $get('bill_5') * 5) + ((int) $get('coin_1') * 1) + ((int) $get('coin_0_50') * 0.50) + ((int) $get('coin_0_25') * 0.25);
                                        $change = (float) ($get('change_given') ?? 0);
                                        $net    = $total - $change;
                                        return new \Illuminate\Support\HtmlString(
                                            "<div style='line-height:1.9'>" .
                                            "💵 <strong>Cash Tendered:</strong> AED " . number_format($total, 2) . "<br>" .
                                            "🔄 <strong>Change Back:</strong> AED " . number_format($change, 2) . "<br>" .
                                            "✅ <strong>Net Disbursed:</strong> AED " . number_format($net, 2) .
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
                    $net = round($tendered - $changeGiven, 2);

                    if ($net !== round((float) $record->amount, 2)) {
                        \Filament\Notifications\Notification::make()->title('Denomination validation failed')->danger()->body('Net amount must equal voucher amount.')->send();
                        throw \Illuminate\Validation\ValidationException::withMessages(['bill_1000' => 'Net amount mismatch.']);
                    }

                    $fields = ['bill_1000', 'bill_500', 'bill_200', 'bill_100', 'bill_50', 'bill_20', 'bill_10', 'bill_5', 'coin_1', 'coin_0_50', 'coin_0_25', 'change_given', 'is_change_received'];
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
                        'bill_1000' => $data['bill_1000'] ?: 0, 'bill_500' => $data['bill_500'] ?: 0, 'bill_200' => $data['bill_200'] ?: 0, 'bill_100' => $data['bill_100'] ?: 0, 'bill_50' => $data['bill_50'] ?: 0, 'bill_20' => $data['bill_20'] ?: 0, 'bill_10' => $data['bill_10'] ?: 0, 'bill_5' => $data['bill_5'] ?: 0, 'coin_1' => $data['coin_1'] ?: 0, 'coin_0_50' => $data['coin_0_50'] ?: 0, 'coin_0_25' => $data['coin_0_25'] ?: 0, 'total_amount' => $tendered, 'change_given' => $changeGiven, 'is_change_received' => $data['is_change_received'] ?? true, 'remarks' => $data['remarks'] ?? null,
                    ]);

                    activity()
                        ->performedOn($record)
                        ->causedBy(auth()->user())
                        ->event('updated')
                        ->log("Denominations Overridden\nReason: {$data['override_reason']}\nMutations: " . implode(' | ', $changes));

                    \Filament\Notifications\Notification::make()->title('Denominations successfully permanently overridden.')->success()->send();
                    $record->refresh();
                }),
                
            Actions\EditAction::make()
                ->visible(fn (): bool => $record->status === 'draft' && auth()->user()->can('voucher.edit')),
        ];
    }
}
