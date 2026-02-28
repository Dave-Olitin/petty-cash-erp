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
                        if ($firstStep) {
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
                            if ($next) {
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
                ->label('Mark as Paid')
                ->icon('heroicon-m-banknotes')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => in_array($record->status, ['pending_checker', 'pending_approver', 'approved']) && auth()->user()->can('voucher.pay'))
                ->action(function () use ($record) {
                    $error = app(\App\Services\VoucherApprovalService::class)->markPaid($record, auth()->user());
                    if ($error) {
                        Notification::make()->title($error)->danger()->send();
                        return;
                    }
                    Notification::make()->title('Voucher marked as paid')->success()->send();
                    $record->refresh();
                }),
                
            Actions\EditAction::make()
                ->visible(fn (): bool => $record->status === 'draft' && auth()->user()->can('voucher.edit')),
        ];
    }
}
