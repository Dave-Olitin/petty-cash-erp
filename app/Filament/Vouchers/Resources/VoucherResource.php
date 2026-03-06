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

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Payee'  => $record->payee,
            'Amount' => 'AED ' . number_format($record->amount, 2),
            'Status' => ucwords(str_replace('_', ' ', $record->status)),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['voucher_number', 'payee', 'description'];
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (!$user) return null;

        // Cache per-user for 30 seconds — this query fires on every Filament page render.
        $count = \Illuminate\Support\Facades\Cache::remember(
            "voucher_badge_{$user->id}",
            30,
            fn () => static::getModel()::actionRequired($user)->count()
        );

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
        return true; // Delegation to Policy
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return true; // Delegation to Policy
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return true; // Delegation to Policy
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ── VOUCHER HEADER ───────────────────────────────────────
                Forms\Components\Section::make('Voucher Details')
                    ->icon('heroicon-o-document-text')
                    ->description('Select the company template and fill in voucher information.')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->options([
                                'petty_cash' => 'Petty Cash Request',
                                'payment' => 'Payment Voucher',
                            ])
                            ->required()
                            ->default('payment')
                            ->live()
                            ->afterStateUpdated(function (\Filament\Forms\Set $set, $state) {
                                if ($state === 'petty_cash') {
                                    $template = \App\Models\VoucherTemplate::where('company_name', 'Erick Trading Co.')->first();
                                    if ($template) {
                                        $set('voucher_template_id', $template->id);
                                    }
                                } else {
                                    $set('voucher_template_id', null);
                                }
                            }),

                        Forms\Components\Select::make('voucher_template_id')
                            ->label('Company / Header Template')
                            ->relationship('template', 'company_name', function (\Illuminate\Database\Eloquent\Builder $query, \Filament\Forms\Get $get) {
                                $query->where('is_active', true);
                                if ($get('type') === 'petty_cash') {
                                    $query->where('company_name', 'Erick Trading Co.');
                                }
                                return $query;
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Forms\Set $set, \Filament\Forms\Get $get) {
                                if ($state) {
                                    $template = \App\Models\VoucherTemplate::find($state);
                                    if ($template) {
                                        $items = $get('items') ?? [];
                                        foreach ($items as $key => $item) {
                                            $set("items.{$key}.branch_code", $template->branch_code);
                                        }
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('payee')
                            ->label('Paid To')
                            ->required()
                            ->maxLength(255)
                            ->live(debounce: 500),

                        Forms\Components\Textarea::make('description')
                            ->label('Being (Purpose)')
                            ->rows(2)
                            ->columnSpanFull()
                            ->live(debounce: 500),
                    ])
                    ->columns(['default' => 1, 'md' => 3])
                    ->columnSpan('full')
                    ->collapsible(),

                // ── CHEQUE / PAYMENT INFO ─────────────────────────────────
                Forms\Components\Section::make('Payment / Cheque Details')
                    ->icon('heroicon-o-credit-card')
                    ->description('Fill in cheque or payment details if applicable.')
                    ->schema([
                        Forms\Components\TextInput::make('cheque_no')
                            ->label('Cheque No.')
                            ->maxLength(50)
                            ->placeholder('Enter cheque number'),

                        Forms\Components\DatePicker::make('cheque_date')
                            ->label('Cheque Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Forms\Components\TextInput::make('bank')
                            ->label('Bank')
                            ->maxLength(100)
                            ->placeholder('e.g. Emirates NBD'),
                    ])
                    ->columns(['default' => 1, 'md' => 3])
                    ->columnSpan('full')
                    ->collapsible()
                    ->collapsed(),

                // ── LEDGER ENTRIES ────────────────────────────────────────
                Forms\Components\Section::make('Ledger Entries')
                    ->icon('heroicon-o-table-cells')
                    ->description('Add debit and credit entries. Total auto-calculates from debit amounts.')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->label('')
                            ->schema([
                                // Row 1: Type, Branch, Account Code
                                Forms\Components\Grid::make(['default' => 1, 'md' => 12])->schema([
                                    Forms\Components\Select::make('entry_type')
                                        ->label('Entry type')
                                        ->options([
                                            'debit'  => 'DR — Debit',
                                            'credit' => 'CR — Credit',
                                        ])
                                        ->default('debit')
                                        ->required()
                                        ->live()
                                        ->columnSpan(['default' => 1, 'md' => 6]),

                                    Forms\Components\Select::make('branch_code')
                                        ->label('Branch')
                                        ->searchable()
                                        ->options(\App\Models\LedgerBranch::pluck('name', 'name'))
                                        ->createOptionForm([
                                            Forms\Components\TextInput::make('name')
                                                ->label('New Branch Name')
                                                ->required()
                                                ->unique('ledger_branches', 'name'),
                                        ])
                                        ->createOptionUsing(function (array $data) {
                                            $branch = \App\Models\LedgerBranch::create(['name' => $data['name']]);
                                            return $branch->name;
                                        })
                                        ->default(fn (Forms\Get $get) => \App\Models\VoucherTemplate::find($get('../../voucher_template_id'))?->branch_code)
                                        ->columnSpan(['default' => 1, 'md' => 6]),

                                    Forms\Components\Select::make('account_code')
                                        ->label('Account Code')
                                        ->searchable()
                                        ->allowHtml()
                                        ->getSearchResultsUsing(function (string $search) {
                                            return \App\Models\AccountCode::where('code', 'like', "%{$search}%")
                                                ->orWhere('name', 'like', "%{$search}%")
                                                ->limit(30)
                                                ->get()
                                                ->mapWithKeys(fn ($ac) => [$ac->code => $ac->code . ' — ' . $ac->name])
                                                ->toArray();
                                        })
                                        ->getOptionLabelUsing(fn (?string $value) => $value
                                            ? ($ac = \App\Models\AccountCode::where('code', $value)->first())
                                                ? "{$ac->code} — {$ac->name}"
                                                : $value
                                            : null
                                        )
                                        ->createOptionForm([
                                            Forms\Components\TextInput::make('code')->required()->unique('account_codes', 'code'),
                                            Forms\Components\TextInput::make('name')->required(),
                                        ])
                                        ->createOptionUsing(function (array $data) {
                                            \App\Models\AccountCode::create($data);
                                            return $data['code'];
                                        })
                                        ->live(debounce: 500)
                                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state) {
                                            if (blank($state)) return;
                                            $account = \App\Models\AccountCode::where('code', $state)->first();
                                            if ($account) {
                                                $set('description', $account->name);
                                            }
                                        })
                                        ->columnSpan(['default' => 1, 'md' => 12]),

                                    Forms\Components\TextInput::make('amount')
                                        ->numeric()
                                        ->required()
                                        ->prefix('AED')
                                        ->default(0)
                                        ->live(debounce: 500)
                                        ->columnSpan(['default' => 1, 'md' => 12]),

                                    Forms\Components\Hidden::make('description'),
                                ]),
                            ])
                            ->defaultItems(2)
                            ->reorderable()
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->cloneable()
                            ->addActionLabel('+ Add Ledger Entry')
                            ->itemLabel(fn (array $state): ?string =>
                                (($state['entry_type'] ?? 'debit') === 'credit' ? '🔴 CR' : '🟢 DR') .
                                ' — ' . ($state['description'] ?: ($state['account_code'] ?: 'New Entry')) .
                                ' — AED ' . number_format((float) ($state['amount'] ?? 0), 2)
                            )
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                $items = $get('items') ?? [];
                                $totalDebit = 0;
                                foreach ($items as $item) {
                                    if (($item['entry_type'] ?? 'debit') === 'debit') {
                                        $totalDebit += (float) ($item['amount'] ?? 0);
                                    }
                                }
                                $set('amount', number_format($totalDebit, 2, '.', ''));
                            })
                            ->live(),
                    ])
                    ->columnSpan(['default' => 'full', 'lg' => 2])
                    ->collapsible(),

                // ── LIVE PREVIEW ──────────────────────────────────────────
                Forms\Components\Section::make('Live Preview')
                    ->extraAttributes(['class' => 'preview-section-no-padding', 'style' => 'padding:0!important; margin:0!important;'])
                    ->schema([
                        Forms\Components\Placeholder::make('pdf_preview_form')
                            ->label('')
                            ->content(fn (Forms\Get $get) => view('filament.forms.components.voucher-preview', ['get' => $get])),
                    ])
                    ->columnSpan(['default' => 'full', 'lg' => 1])
                    ->collapsible()
                    ->collapsed(false),

                // ── SUMMARY & ATTACHMENTS ─────────────────────────────────
                Forms\Components\Section::make('Summary & Attachments')
                    ->icon('heroicon-o-calculator')
                    ->compact()
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Total Amount')
                            ->numeric()
                            ->prefix('AED')
                            ->required()
                            ->live(debounce: 500)
                            ->helperText('Auto-calculated from debit entries. You can override this manually.')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('transaction_summary')
                            ->label('Transaction Summary')
                            ->rows(3)
                            ->helperText('Optional manual text to print on the voucher summary at the bottom.')
                            ->live(debounce: 500)
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('attachment_paths')
                            ->multiple()
                            ->preserveFilenames()
                            ->directory('voucher-attachments')
                            ->disk('public')
                            ->downloadable()
                            ->openable()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->maxSize(10240)
                            ->label('Invoices & Receipts')
                            ->helperText('Upload receipts, invoices, or supporting documents (JPG, PNG, PDF, max 10MB each).')
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpan('full')
                    ->collapsible(),
            ])->columns(['default' => 1, 'lg' => 3]);
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
            ->filtersFormColumns(2)
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::Modal)
            ->groups([
                \Filament\Tables\Grouping\Group::make('status')
                    ->label('Status')
                    ->collapsible(),
                \Filament\Tables\Grouping\Group::make('type')
                    ->label('Type')
                    ->collapsible(),
                \Filament\Tables\Grouping\Group::make('user.name')
                    ->label('Requester')
                    ->collapsible(),
            ])
            ->headerActions([
                \Filament\Tables\Actions\ImportAction::make()
                    ->importer(\App\Filament\Imports\VoucherImporter::class),
                \Filament\Tables\Actions\Action::make('export_custom')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($livewire) {
                        return \Maatwebsite\Excel\Facades\Excel::download(
                            new \App\Exports\VouchersExport($livewire->getFilteredTableQuery()),
                            'vouchers_export_' . now()->format('Y-m-d_H-i-s') . '.xlsx'
                        );
                    }),
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
                        $error = app(\App\Services\VoucherApprovalService::class)->submit($record, auth()->user());
                        if ($error) {
                            Notification::make()->title($error)->danger()->send();
                            return;
                        }
                        Notification::make()->title('Voucher submitted successfully')->success()->send();
                        $record->refresh();
                    }),

                Tables\Actions\Action::make('check')
                    ->label('Verify & Forward')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->color('warning')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->visible(fn (Voucher $record): bool => $record->status === 'pending_checker' && auth()->user()->can('voucher.check'))
                    ->action(function (Voucher $record) {
                        $error = app(\App\Services\VoucherApprovalService::class)->check($record, auth()->user());
                        if ($error) {
                            Notification::make()->title($error)->danger()->send();
                            return;
                        }
                        Notification::make()->title('Voucher forwarded to Approver')->success()->send();
                        $record->refresh();
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
                        $service = app(\App\Services\VoucherApprovalService::class);
                        $wasMultiStep = \App\Models\ApprovalWorkflow::totalSteps() > 0;
                        $currentStep  = (int) ($record->current_approval_step ?? 1);
                        $totalSteps   = \App\Models\ApprovalWorkflow::totalSteps();
                        $isLastStep   = $totalSteps === 0 || $currentStep >= $totalSteps;

                        $error = $service->approve($record, auth()->user());
                        if ($error) {
                            Notification::make()->title($error)->danger()->send();
                            return;
                        }

                        $title = $isLastStep
                            ? 'Voucher fully approved'
                            : 'Step ' . $currentStep . ' approved — forwarded to next approver';

                        Notification::make()->title($title)->success()->send();
                        $record->refresh();
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
                        $error = app(\App\Services\VoucherApprovalService::class)->reject($record, auth()->user(), $data['comments']);
                        if ($error) {
                            Notification::make()->title($error)->danger()->send();
                            return;
                        }
                        Notification::make()->title('Voucher rejected')->danger()->send();
                        $record->refresh();
                    }),

                Tables\Actions\Action::make('mark_paid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-m-banknotes')
                    ->color('success')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->visible(fn (Voucher $record): bool => in_array($record->status, ['pending_checker', 'pending_approver', 'approved']) && auth()->user()->can('voucher.pay'))
                    ->action(function (Voucher $record) {
                        $error = app(\App\Services\VoucherApprovalService::class)->markPaid($record, auth()->user());
                        if ($error) {
                            Notification::make()->title($error)->danger()->send();
                            return;
                        }
                        Notification::make()->title('Voucher marked as paid')->success()->send();
                        $record->refresh();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export_selected')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            return \Maatwebsite\Excel\Facades\Excel::download(
                                new \App\Exports\VouchersExport(\App\Models\Voucher::whereIn('vouchers.id', $records->pluck('id'))),
                                'vouchers_selected_' . now()->format('Y-m-d_H-i-s') . '.xlsx'
                            );
                        }),
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
        $query = parent::getEloquentQuery()
            ->with(['approvals.user.roles', 'items.category', 'template']);
            
        $user = auth()->user();

        // Scope queries based on user permissions/branch
        if ($user && !$user->isHeadOffice() && !$user->hasAnyRole(['Accountant', 'Approver', 'Admin', 'Super Admin'])) {
            $query->where('user_id', $user->id);
        }

        return $query;
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

                        \Filament\Infolists\Components\TextEntry::make('cheque_no')
                            ->label('Cheque #')
                            ->visible(fn ($record) => !empty($record->cheque_no)),
                        \Filament\Infolists\Components\TextEntry::make('cheque_date')
                            ->date()
                            ->visible(fn ($record) => !empty($record->cheque_no)),
                        \Filament\Infolists\Components\TextEntry::make('bank')
                            ->visible(fn ($record) => !empty($record->cheque_no)),

                        \Filament\Infolists\Components\TextEntry::make('attachment_paths')
                            ->label('Invoices / Receipts')
                            ->columnSpanFull()
                            ->formatStateUsing(fn (string $state): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString('<a href="'.asset('storage/'.$state).'" target="_blank" class="text-primary-600 underline flex items-center gap-2"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>'.basename($state).'</a>'))
                            ->html()
                            ->listWithLineBreaks(),
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

