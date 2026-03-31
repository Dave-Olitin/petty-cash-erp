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

            Actions\Action::make('mark_paid_page')
                ->label($record->type === 'receipt' ? 'Collect Funds' : 'Disburse Funds')
                ->icon('heroicon-m-banknotes')
                ->color('success')
                ->modalHeading($record->type === 'receipt' ? 'Collect Cash Denominations' : 'Disburse Cash Denominations')
                ->modalSubmitActionLabel('Process & Mark Paid')
                ->form([
                    Forms\Components\Section::make('Cash Handover / Collection')
                        ->description(new \Illuminate\Support\HtmlString("Target Amount to Match: <strong>AED " . number_format((float) $record->amount, 2) . "</strong>"))
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('bill_1000')->label('1000 Bills')->numeric()->default(0)->live()->minValue(0),
                                Forms\Components\TextInput::make('bill_500')->label('500 Bills')->numeric()->default(0)->live()->minValue(0),
                                Forms\Components\TextInput::make('bill_200')->label('200 Bills')->numeric()->default(0)->live()->minValue(0),
                                Forms\Components\TextInput::make('bill_100')->label('100 Bills')->numeric()->default(0)->live()->minValue(0),
                                Forms\Components\TextInput::make('bill_50')->label('50 Bills')->numeric()->default(0)->live()->minValue(0),
                                Forms\Components\TextInput::make('bill_20')->label('20 Bills')->numeric()->default(0)->live()->minValue(0),
                                Forms\Components\TextInput::make('bill_10')->label('10 Bills')->numeric()->default(0)->live()->minValue(0),
                                Forms\Components\TextInput::make('bill_5')->label('5 Bills')->numeric()->default(0)->live()->minValue(0),
                                Forms\Components\TextInput::make('coin_1')->label('1 Coins')->numeric()->default(0)->live()->minValue(0),
                                Forms\Components\TextInput::make('coin_0_50')->label('0.50 Coins')->numeric()->default(0)->live()->step('1')->minValue(0),
                                Forms\Components\TextInput::make('coin_0_25')->label('0.25 Coins')->numeric()->default(0)->live()->step('1')->minValue(0),
                                Forms\Components\Placeholder::make('total_sum')
                                    ->label('Calculated Total')
                                    ->content(function (Forms\Get $get) {
                                        $total = ((float)$get('bill_1000') * 1000)
                                            + ((float)$get('bill_500') * 500)
                                            + ((float)$get('bill_200') * 200)
                                            + ((float)$get('bill_100') * 100)
                                            + ((float)$get('bill_50') * 50)
                                            + ((float)$get('bill_20') * 20)
                                            + ((float)$get('bill_10') * 10)
                                            + ((float)$get('bill_5') * 5)
                                            + ((float)$get('coin_1') * 1)
                                            + ((float)$get('coin_0_50') * 0.50)
                                            + ((float)$get('coin_0_25') * 0.25);
                                        return 'AED ' . number_format((float) $total, 2);
                                    }),
                            ])
                        ])
                ])
                ->visible(fn (): bool => in_array($record->status, ['pending_checker', 'pending_approver', 'approved']) && auth()->user()->can('voucher.pay'))
                ->action(function (array $data) use ($record) {
                    $total = ((float)$data['bill_1000'] * 1000)
                        + ((float)$data['bill_500'] * 500)
                        + ((float)$data['bill_200'] * 200)
                        + ((float)$data['bill_100'] * 100)
                        + ((float)$data['bill_50'] * 50)
                        + ((float)$data['bill_20'] * 20)
                        + ((float)$data['bill_10'] * 10)
                        + ((float)$data['bill_5'] * 5)
                        + ((float)$data['coin_1'] * 1)
                        + ((float)$data['coin_0_50'] * 0.50)
                        + ((float)$data['coin_0_25'] * 0.25);

                    if (round((float) $total, 2) !== round((float) $record->amount, 2)) {
                        Notification::make()
                            ->title('Denomination validation failed')
                            ->danger()
                            ->body('The total cash denominations (AED ' . number_format((float) $total, 2) . ') must exactly match the voucher amount (AED ' . number_format((float) $record->amount, 2) . ').')
                            ->send();
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'bill_1000' => 'Denomination sum mismatch. Provided: ' . number_format((float) $total, 2) . '. Required: ' . number_format((float) $record->amount, 2)
                        ]);
                    }

                    // Save denominations securely
                    $record->denominations()->create([
                        'bill_1000' => $data['bill_1000'] ?: 0,
                        'bill_500' => $data['bill_500'] ?: 0,
                        'bill_200' => $data['bill_200'] ?: 0,
                        'bill_100' => $data['bill_100'] ?: 0,
                        'bill_50' => $data['bill_50'] ?: 0,
                        'bill_20' => $data['bill_20'] ?: 0,
                        'bill_10' => $data['bill_10'] ?: 0,
                        'bill_5' => $data['bill_5'] ?: 0,
                        'coin_1' => $data['coin_1'] ?: 0,
                        'coin_0_50' => $data['coin_0_50'] ?: 0,
                        'coin_0_25' => $data['coin_0_25'] ?: 0,
                        'total_amount' => $total,
                    ]);

                    $error = app(\App\Services\VoucherApprovalService::class)->markPaid($record, auth()->user());
                    if ($error) {
                        Notification::make()->title($error)->danger()->send();
                        return;
                    }
                    Notification::make()->title($record->type === 'receipt' ? 'Receipt funds safely collected' : 'Voucher funds safely disbursed')->success()->send();
                    $record->refresh();
                }),
                
            Actions\EditAction::make()
                ->visible(fn (): bool => $record->status === 'draft' && auth()->user()->can('voucher.edit')),
        ];
    }
}
