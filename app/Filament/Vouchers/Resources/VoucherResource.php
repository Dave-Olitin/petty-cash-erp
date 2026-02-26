<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\VoucherResource\Pages;
use App\Filament\Vouchers\Resources\VoucherResource\RelationManagers;
use App\Models\Voucher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (!$user) return null;
        
        $count = static::getModel()::actionRequired($user)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return static::getNavigationBadge() !== null ? 'danger' : null;
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('voucher.create');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->can('voucher.edit') && $record->status === 'draft';
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->can('voucher.delete');
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->can('voucher.view');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    // ── LEFT COLUMN: Input Form (Takes up 2 columns) ──────────
                    Forms\Components\Section::make('Voucher Details')->schema([
                        Forms\Components\Select::make('type')
                            ->options([
                                'petty_cash' => 'Petty Cash Request',
                                'payment' => 'Payment Voucher',
                            ])
                            ->required()
                            ->default('payment')
                            ->live(),

                        Forms\Components\TextInput::make('payee')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true),

                        Forms\Components\TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->prefix('AED')
                            ->live(onBlur: true),

                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->live(),

                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull()
                            ->live(onBlur: true),

                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('attachments')
                            ->collection('attachments')
                            ->multiple()
                            ->preserveFilenames()
                            ->disk('local')
                            ->visibility('private')
                            ->downloadable()
                            ->openable()
                            ->label('Invoices & Receipts')
                            ->columnSpanFull(),
                    ])->columns(2)->columnSpan(2),

                    // ── RIGHT COLUMN: Live Preview (Takes up 1 column) ──────────
                    Forms\Components\Section::make('Live Preview')
                        ->schema([
                            Forms\Components\Placeholder::make('preview')
                                ->label('')
                                ->content(fn ($get) => view('filament.forms.components.voucher-preview', ['get' => $get])),
                        ])
                        ->columnSpan(1)
                        ->extraAttributes(['class' => 'sticky top-10']),
                ]),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordAction(\Filament\Tables\Actions\ViewAction::class)
            ->columns([
                Tables\Columns\TextColumn::make('voucher_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'petty_cash' => 'info',
                        'payment' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Requester')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payee')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('aed', true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->icon(fn (string $state): string => match ($state) {
                        'draft' => 'heroicon-m-pencil-square',
                        'pending_checker' => 'heroicon-m-clock',
                        'pending_approver' => 'heroicon-m-clock',
                        'approved' => 'heroicon-m-check-circle',
                        'rejected' => 'heroicon-m-x-circle',
                        'paid' => 'heroicon-m-banknotes',
                        default => 'heroicon-m-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending_checker' => 'warning',
                        'pending_approver' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'paid' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'pending_checker' => 'Pending Checker',
                        'pending_approver' => 'Pending Approver',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'paid' => 'Paid',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'petty_cash' => 'Petty Cash',
                        'payment' => 'Payment',
                    ]),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Requester')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('created_until')->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->headerActions([
                \Filament\Tables\Actions\ImportAction::make()
                    ->importer(\App\Filament\Imports\VoucherImporter::class),
                \pxlrbt\FilamentExcel\Actions\Tables\ExportAction::make()
                    ->exports([
                        \pxlrbt\FilamentExcel\Exports\ExcelExport::make('table')->fromTable(),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton(),
                Tables\Actions\Action::make('print')
                    ->label('Print Voucher')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->iconButton()
                    ->url(fn (Voucher $record) => route('voucher.pdf', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->visible(fn (Voucher $record): bool => $record->status === 'draft' && auth()->user()->can('voucher.edit')),

                Tables\Actions\Action::make('submit')
                    ->label('Submit for Checking')
                    ->icon('heroicon-m-paper-airplane')
                    ->color('primary')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->visible(fn (Voucher $record): bool => $record->status === 'draft' && auth()->user()->can('voucher.submit'))
                    ->action(function (Voucher $record) {
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

                Tables\Actions\Action::make('check')
                    ->label('Verify & Forward')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->color('warning')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->visible(fn (Voucher $record): bool => $record->status === 'pending_checker' && auth()->user()->can('voucher.check'))
                    ->action(function (Voucher $record) {
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

                            // Notify the first approver in the chain (or all if no chain configured)
                            $firstStep = \App\Models\ApprovalWorkflow::getApproverAtStep(1);
                            if ($firstStep) {
                                $firstStep->user->notify(new \App\Notifications\VoucherStatusNotification($lockedRecord, 'checked'));
                            } else {
                                // Fallback: notify all Approver-role users
                                User::role('Approver')->get()->each->notify(new \App\Notifications\VoucherStatusNotification($lockedRecord, 'checked'));
                            }

                            Notification::make()
                                ->title('Voucher forwarded to Approver')
                                ->success()
                                ->send();

                            $record->refresh();
                        });
                    }),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->visible(function (Voucher $record): bool {
                        if ($record->status !== 'pending_approver') return false;
                        if (!auth()->user()->can('voucher.approve')) return false;

                        // If a workflow chain is configured, only show to the correct step's user
                        if (\App\Models\ApprovalWorkflow::isConfigured()) {
                            $step = \App\Models\ApprovalWorkflow::getApproverAtStep((int) ($record->current_approval_step ?? 1));
                            return $step && $step->user_id == auth()->id();
                        }

                        // No chain configured — any Approver-role user can act
                        return auth()->user()->hasRole('Approver');
                    })
                    ->action(function (Voucher $record) {
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

                            // Record this approval step
                            $lockedRecord->approvals()->create([
                                'user_id' => auth()->id(),
                                'action'  => 'approved',
                                'comments' => \App\Models\ApprovalWorkflow::getApproverAtStep($currentStep)?->label
                                    ? 'Approved as ' . \App\Models\ApprovalWorkflow::getApproverAtStep($currentStep)->label
                                    : null,
                            ]);

                            $nextStep = $currentStep + 1;

                            if ($totalSteps > 0 && $nextStep <= $totalSteps) {
                                // More steps remaining — advance to next approver
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
                                // All steps done — fully approved
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

                Tables\Actions\Action::make('reject')
                    ->label('Return / Reject')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('comments')->required()->label('Reason for Rejection'),
                    ])
                    ->visible(fn (Voucher $record): bool => in_array($record->status, ['pending_checker', 'pending_approver']) && auth()->user()->can('voucher.reject'))
                    ->action(function (Voucher $record, array $data) {
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

                Tables\Actions\Action::make('mark_paid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-m-banknotes')
                    ->color('success')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->visible(fn (Voucher $record): bool => $record->status === 'approved' && auth()->user()->can('voucher.mark_paid'))
                    ->action(function (Voucher $record) {
                        \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                            $lockedRecord = \App\Models\Voucher::lockForUpdate()->find($record->id);
                            
                            if ($lockedRecord->status !== 'approved') {
                                Notification::make()->title('Voucher status changed by another user.')->danger()->send();
                                return;
                            }

                            $lockedRecord->update(['status' => 'paid']);
                            $lockedRecord->load('user');
                            $lockedRecord->user->notify(new \App\Notifications\VoucherStatusNotification($lockedRecord, 'paid'));

                            Notification::make()
                                ->title('Voucher marked as paid')
                                ->success()
                                ->send();

                            $record->refresh();
                        });
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    \pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction::make(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVouchers::route('/'),
            'create' => Pages\CreateVoucher::route('/create'),
            'view' => Pages\ViewVoucher::route('/{record}'),
            'edit' => Pages\EditVoucher::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['approvals.user.roles']);
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                // ── TOP ROW: PDF preview (Left, 75%) | Approval Trail (Right, 25%) ──────────
                \Filament\Infolists\Components\Grid::make(4)->schema([
                    // Left: PDF iframe (takes up 3 columns)
                    \Filament\Infolists\Components\Section::make()
                        ->schema([
                            \Filament\Infolists\Components\ViewEntry::make('pdf_preview')
                                ->view('filament.infolists.pdf-preview')
                                ->label(''),
                        ])
                        ->compact()
                        ->columnSpan(3),

                    // Right: Approval Trail (takes up 1 column)
                    \Filament\Infolists\Components\Section::make()
                        ->schema([
                            \Filament\Infolists\Components\ViewEntry::make('approvals_timeline')
                                ->label('')
                                ->view('filament.infolists.approval-timeline'),
                        ])
                        ->columnSpan(1),
                ]),

                // ── BOTTOM ROW: Voucher Details — full width ───────────────────────
                \Filament\Infolists\Components\Section::make('Voucher Details')
                    ->compact()
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('voucher_number')
                            ->label('Voucher #')
                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                            ->copyable(),
                        \Filament\Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->icon(fn (string $state): string => match ($state) {
                                'draft'            => 'heroicon-m-pencil-square',
                                'pending_checker'  => 'heroicon-m-clock',
                                'pending_approver' => 'heroicon-m-clock',
                                'approved'         => 'heroicon-m-check-circle',
                                'rejected'         => 'heroicon-m-x-circle',
                                'paid'             => 'heroicon-m-banknotes',
                                default            => 'heroicon-m-question-mark-circle',
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'draft'            => 'gray',
                                'pending_checker'  => 'warning',
                                'pending_approver' => 'warning',
                                'approved'         => 'success',
                                'rejected'         => 'danger',
                                'paid'             => 'success',
                                default            => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),
                        \Filament\Infolists\Components\TextEntry::make('user.name')
                            ->label('Requester')
                            ->icon('heroicon-m-user-circle')
                            ->iconColor('primary')
                            ->weight(\Filament\Support\Enums\FontWeight::Bold),
                        \Filament\Infolists\Components\TextEntry::make('type')
                            ->formatStateUsing(fn ($state) => $state === 'petty_cash' ? 'Petty Cash' : 'Payment Voucher'),
                        \Filament\Infolists\Components\TextEntry::make('category.name')
                            ->label('Category'),
                        \Filament\Infolists\Components\TextEntry::make('payee')
                            ->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                        \Filament\Infolists\Components\TextEntry::make('amount')
                            ->money('aed')
                            ->weight(\Filament\Support\Enums\FontWeight::Bold),
                        \Filament\Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided'),
                    ])->columns(4),

                // ── ACTIVITY LOG: Full width chronological timeline ──────────────────────
                \Filament\Infolists\Components\Section::make('Activity Log')
                    ->compact()
                    ->schema([
                        \Filament\Infolists\Components\ViewEntry::make('activity_log')
                            ->label('')
                            ->view('filament.infolists.activity-log'),
                    ])
                    ->collapsed(),

            ])->columns(1);
    }
}

