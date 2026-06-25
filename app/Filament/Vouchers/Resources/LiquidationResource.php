<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\LiquidationResource\Pages;
use App\Models\Liquidation;
use App\Models\Voucher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class LiquidationResource extends Resource
{
    protected static ?string $model = Liquidation::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Liquidations';
    protected static ?string $navigationGroup = null;
    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        $count = Liquidation::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        // Red if any are overdue
        $overdue = Liquidation::overdue()->count();
        return $overdue > 0 ? 'danger' : 'warning';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Accountant', 'Head Office', 'Cashier']) 
            || $user->can('report.view');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Voucher Reference')
                ->schema([
                    Forms\Components\Select::make('voucher_id')
                        ->label('Petty Cash Voucher')
                        ->options(function (?Liquidation $record) {
                            // Include the currently-linked voucher even if already liquidated
                            $currentId = $record?->voucher_id;

                            $query = Voucher::where('type', 'petty_cash')
                                ->where('is_for_liquidation', true)
                                ->where('status', 'paid')
                                ->where(function ($q) use ($currentId) {
                                    $q->whereIn('liquidation_status', ['pending', 'overdue']);
                                    if ($currentId) {
                                        $q->orWhere('id', $currentId);
                                    }
                                });

                            return $query->get()->mapWithKeys(
                                fn ($v) => [$v->id => $v->voucher_number . ' — ' . $v->payee . ' (AED ' . number_format($v->amount, 2) . ')']
                            )->toArray();
                        })
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if (!$state) return;
                            $voucher = Voucher::find($state);
                            if ($voucher) {
                                $set('_voucher_amount', $voucher->amount);
                                $set('_voucher_payee', $voucher->payee);
                                $set('_voucher_number', $voucher->voucher_number);
                                $set('amount_short', $voucher->amount);
                                // Pre-fill the prior_deduction from the latest denomination record
                                $denom = $voucher->denominations()->latest()->first();
                                $deduction = $denom ? (float) $denom->prior_deduction : 0.0;
                                $set('prior_deduction', $deduction);
                                // Recalculate amount_short to net of deduction
                                $set('amount_short', max(0, (float)$voucher->amount - $deduction));
                            }
                        }),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Placeholder::make('_voucher_number')
                            ->label('Voucher No.')
                            ->content(fn (Forms\Get $get) => $get('_voucher_number') ?? '—'),
                        Forms\Components\Placeholder::make('_voucher_payee')
                            ->label('Employee / Payee')
                            ->content(fn (Forms\Get $get) => $get('_voucher_payee') ?? '—'),
                        Forms\Components\Placeholder::make('_voucher_amount')
                            ->label('Original Amount (AED)')
                            ->content(fn (Forms\Get $get) => $get('_voucher_amount') ? 'AED ' . number_format($get('_voucher_amount'), 2) : '—'),
                    ]),

                    Forms\Components\Hidden::make('_voucher_amount'),
                    Forms\Components\Hidden::make('_voucher_payee'),
                    Forms\Components\Hidden::make('_voucher_number'),
                    Forms\Components\Hidden::make('amount_short'),
                    Forms\Components\Hidden::make('prior_deduction'),
                ]),

            Forms\Components\Section::make('Settlement Details')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('amount_spent')
                            ->label('Amount Spent (w/ receipts) AED')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                $original  = (float) ($get('_voucher_amount') ?? 0);
                                $deduction = (float) ($get('prior_deduction') ?? 0);
                                $netTarget = max(0, $original - $deduction);
                                $spent     = (float) ($get('amount_spent') ?? 0);
                                $returned  = (float) ($get('amount_returned') ?? 0);
                                $short     = max(0, $netTarget - $spent - $returned);
                                $set('amount_short', $short);
                            })
                            ->extraInputAttributes(['class' => 'font-mono text-lg']),

                        Forms\Components\TextInput::make('amount_returned')
                            ->label('Cash Returned to Box (AED)')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                $original  = (float) ($get('_voucher_amount') ?? 0);
                                $deduction = (float) ($get('prior_deduction') ?? 0);
                                $netTarget = max(0, $original - $deduction);
                                $spent     = (float) ($get('amount_spent') ?? 0);
                                $returned  = (float) ($get('amount_returned') ?? 0);
                                $short     = max(0, $netTarget - $spent - $returned);
                                $set('amount_short', $short);
                            })
                            ->extraInputAttributes(['class' => 'font-mono text-lg text-success-600']),
                    ]),

                    Forms\Components\Placeholder::make('liquidation_summary')
                        ->label('Live Calculation')
                        ->live()
                        ->content(function (Forms\Get $get) {
                            $original  = (float) ($get('_voucher_amount') ?? 0);
                            $deduction = (float) ($get('prior_deduction') ?? 0);
                            $netTarget = max(0, $original - $deduction);
                            $spent     = (float) ($get('amount_spent') ?? 0);
                            $returned  = (float) ($get('amount_returned') ?? 0);
                            $accounted = $spent + $returned;
                            $diff      = round($accounted - $netTarget, 2);

                            $panelStyle = match (true) {
                                abs($diff) <= 0.01 => 'background-color:#f0fdf4; border-color:#86efac;',
                                $diff < 0          => 'background-color:#fef2f2; border-color:#fca5a5;',
                                default            => 'background-color:#eff6ff; border-color:#93c5fd;',
                            };

                            $statusMsg = match (true) {
                                abs($diff) <= 0.01 => '<span style="padding:2px 10px;border-radius:6px;font-size:11px;font-weight:700;background:#dcfce7;color:#15803d;">✅ Exact — Ready to Liquidate</span>',
                                $diff < 0          => '<span style="padding:2px 10px;border-radius:6px;font-size:11px;font-weight:700;background:#fee2e2;color:#b91c1c;">🔴 Short by AED ' . number_format(abs($diff), 2) . '</span>',
                                $diff > 0          => '<span style="padding:2px 10px;border-radius:6px;font-size:11px;font-weight:700;background:#dbeafe;color:#1d4ed8;">🔵 Excess by AED ' . number_format($diff, 2) . '</span>',
                                default            => '—',
                            };

                            $deductionRow = $deduction > 0
                                ? "<div style='color:#b91c1c;margin-bottom:4px;'>Advance Deducted: AED " . number_format($deduction, 2) . "</div>"
                                . "<div style='margin-bottom:4px;font-weight:600;'>Net Receipts Needed: AED " . number_format($netTarget, 2) . "</div>"
                                : '';

                            $actionMsg = '';
                            if ($diff < -0.01) {
                                $actionMsg = "<div style='margin-top:10px;padding:10px;background-color:#fff5f5;border:1px solid #fecaca;border-radius:6px;color:#c53030;font-size:12px;font-family:sans-serif;line-height:1.4;'>" .
                                    "ℹ️ <b>System Action:</b> Saving this will automatically draft a <b>Petty Cash Voucher (PCV)</b> for the shortage of <b>AED " . number_format(abs($diff), 2) . "</b> to reimburse the employee." .
                                    "</div>";
                            } elseif ($diff > 0.01) {
                                $actionMsg = "<div style='margin-top:10px;padding:10px;background-color:#f0f7ff;border:1px solid #bfdbfe;border-radius:6px;color:#1e40af;font-size:12px;font-family:sans-serif;line-height:1.4;'>" .
                                    "ℹ️ <b>System Action:</b> Saving this will automatically draft a <b>Receipt Voucher (RV)</b> for the excess of <b>AED " . number_format($diff, 2) . "</b> to record cash returned to the box." .
                                    "</div>";
                            }

                            return new \Illuminate\Support\HtmlString(
                                "<div style='padding:14px;border-radius:10px;border:1px solid;{$panelStyle}font-family:monospace;'>" .
                                "<div style='margin-bottom:4px;'>Original Voucher: AED " . number_format($original, 2) . "</div>" .
                                $deductionRow .
                                "<div style='margin-bottom:4px;'>Spent:    AED " . number_format($spent, 2) . "</div>" .
                                "<div style='margin-bottom:8px;'>Returned: AED " . number_format($returned, 2) . "</div>" .
                                "<div style='padding-top:8px;border-top:1px solid #e5e7eb;font-family:sans-serif;'>Status: {$statusMsg}</div>" .
                                $actionMsg .
                                "</div>"
                            );
                        }),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\DatePicker::make('due_date')
                            ->label('Deadline')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->helperText('Leave blank for system default.'),

                        Forms\Components\Select::make('liquidated_by')
                            ->label('Custodian')
                            ->relationship('custodian', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn () => auth()->id())
                            ->required(),
                    ]),

                    Forms\Components\Textarea::make('remarks')
                        ->label('Custodian Remarks')
                        ->rows(3)
                        ->placeholder('Internal notes regarding the liquidation...')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('Liquidation Overview')
                    ->schema([
                        \Filament\Infolists\Components\Split::make([
                            \Filament\Infolists\Components\Grid::make(3)
                                ->schema([
                                    \Filament\Infolists\Components\Group::make([
                                        \Filament\Infolists\Components\TextEntry::make('voucher.voucher_number')
                                            ->label('Original Voucher')
                                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                            ->color('primary'),
                                        \Filament\Infolists\Components\TextEntry::make('voucher.payee')
                                            ->label('Employee / Payee'),
                                    ]),
                                    \Filament\Infolists\Components\Group::make([
                                        \Filament\Infolists\Components\TextEntry::make('status')
                                            ->badge()
                                            ->color(fn ($state) => match($state) {
                                                'complete'     => 'success',
                                                'short'        => 'danger',
                                                'excess'       => 'info',
                                                'auto_settled' => 'gray',
                                                default        => 'warning',
                                            })
                                            ->formatStateUsing(fn ($state) => strtoupper($state)),
                                        \Filament\Infolists\Components\TextEntry::make('liquidated_at')
                                            ->label('Settled At')
                                            ->dateTime('M j, Y h:i A')
                                            ->placeholder('Not settled yet'),
                                    ]),
                                    \Filament\Infolists\Components\Group::make([
                                        \Filament\Infolists\Components\TextEntry::make('voucher.amount')
                                            ->label('Original Advance')
                                            ->money('AED')
                                            ->extraAttributes(['class' => 'font-mono text-xl']),
                                    ]),
                                ]),
                        ])->from('md'),
                    ]),

                \Filament\Infolists\Components\Grid::make(2)
                    ->schema([
                        \Filament\Infolists\Components\Section::make('Settlement Details')
                            ->schema([
                                \Filament\Infolists\Components\Grid::make(3)
                                    ->schema([
                                        \Filament\Infolists\Components\TextEntry::make('amount_spent')
                                            ->label('Amount Spent')
                                            ->money('AED')
                                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                            ->extraAttributes(['class' => 'font-mono text-lg text-gray-700']),
                                        \Filament\Infolists\Components\TextEntry::make('amount_returned')
                                            ->label('Cash Returned')
                                            ->money('AED')
                                            ->extraAttributes(['class' => 'font-mono text-lg text-success-600']),
                                        \Filament\Infolists\Components\TextEntry::make('amount_short')
                                            ->label('Shortage / Balance')
                                            ->money('AED')
                                            ->extraAttributes(['class' => 'font-mono text-lg'])
                                            ->color(fn ($state) => (float)$state > 0 ? 'danger' : 'success'),
                                    ]),

                                // Cash Advance deduction row — only shown if > 0
                                \Filament\Infolists\Components\TextEntry::make('prior_deduction')
                                    ->label('Cash Advance / Prior Deduction')
                                    ->money('AED')
                                    ->color('warning')
                                    ->helperText('This amount was deducted at disbursement. Receipts are only required for the net amount.')
                                    ->visible(fn ($record) => (float) $record->prior_deduction > 0)
                                    ->columnSpanFull(),

                                \Filament\Infolists\Components\TextEntry::make('remarks')
                                    ->label('Custodian Remarks')
                                    ->prose()
                                    ->placeholder('No remarks recorded.')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(1),

                        \Filament\Infolists\Components\Section::make('Audit Info')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('due_date')
                                    ->label('Deadline')
                                    ->date('M j, Y')
                                    ->icon('heroicon-o-calendar'),
                                \Filament\Infolists\Components\TextEntry::make('custodian.name')
                                    ->label('Processed By')
                                    ->icon('heroicon-o-user-circle'),
                                \Filament\Infolists\Components\TextEntry::make('created_at')
                                    ->label('Record Created')
                                    ->dateTime('M j, Y h:i A')
                                    ->icon('heroicon-o-clock'),
                            ])
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['voucher.user', 'custodian']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('voucher.voucher_number')
                    ->label('Voucher #')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => $record->voucher_id
                        ? \App\Filament\Vouchers\Resources\VoucherResource::getUrl('view', ['record' => $record->voucher_id])
                        : null)
                    ->openUrlInNewTab()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('voucher.payee')
                    ->label('Employee / Payee')
                    ->searchable(),

                Tables\Columns\TextColumn::make('voucher.amount')
                    ->label('Original (AED)')
                    ->money('AED')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount_spent')
                    ->label('Spent (AED)')
                    ->money('AED'),

                Tables\Columns\TextColumn::make('amount_returned')
                    ->label('Returned (AED)')
                    ->money('AED'),

                Tables\Columns\TextColumn::make('variance')
                    ->label('Variance')
                    ->state(fn ($record) => $record->variance)
                    ->formatStateUsing(function ($state) {
                        if (abs($state) <= 0.01) return '—';
                        $prefix = $state > 0 ? '+' : '';
                        return $prefix . 'AED ' . number_format($state, 2);
                    })
                    ->color(fn ($record) => match (true) {
                        abs($record->variance) <= 0.01 => 'success',
                        $record->variance < 0           => 'danger',
                        default                         => 'info',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'complete'     => 'success',
                        'short'        => 'danger',
                        'excess'       => 'info',
                        'auto_settled' => 'gray',
                        default        => 'warning',
                    })
                    ->formatStateUsing(fn ($state) => $state === 'auto_settled' ? 'Auto-Settled' : ucfirst($state)),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn ($record) => ($record->status === 'pending' && $record->isOverdue()) ? 'danger' : null)
                    ->description(fn ($record) => ($record->status === 'pending' && $record->isOverdue()) ? '⚠️ Overdue' : null),

                Tables\Columns\TextColumn::make('days_to_settle')
                    ->label('Days to Settle')
                    ->state(function ($record) {
                        if (!$record->liquidated_at || !$record->voucher || !$record->voucher->created_at) return '—';
                        $start = \Carbon\Carbon::parse($record->voucher->created_at)->startOfDay();
                        $end = \Carbon\Carbon::parse($record->liquidated_at)->startOfDay();
                        return (int) $start->diffInDays($end) . ' days';
                    }),

                Tables\Columns\TextColumn::make('custodian.name')
                    ->label('Filed By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'      => 'Pending',
                        'complete'     => 'Complete',
                        'short'        => 'Short',
                        'excess'       => 'Excess',
                        'auto_settled' => 'Auto-Settled',
                    ]),
                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue Only')
                    ->query(fn (Builder $query) => $query->overdue())
                    ->toggle(),
                Tables\Filters\Filter::make('due_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('due_from')->label('Due From'),
                        \Filament\Forms\Components\DatePicker::make('due_until')->label('Due Until'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['due_from'], fn ($q, $d) => $q->where('due_date', '>=', $d))
                        ->when($data['due_until'], fn ($q, $d) => $q->where('due_date', '<=', $d))
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_filtered')
                    ->label('Export to CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($livewire) {
                        $records = $livewire->getFilteredTableQuery()->with('voucher.user')->get();
                        
                        $exportData = $records->map(function ($r) {
                            $days = '—';
                            if ($r->liquidated_at && $r->voucher && $r->voucher->created_at) {
                                $start = \Carbon\Carbon::parse($r->voucher->created_at)->startOfDay();
                                $end = \Carbon\Carbon::parse($r->liquidated_at)->startOfDay();
                                $days = (int) $start->diffInDays($end) . ' days';
                            }
                            
                            return [
                                $r->voucher->voucher_number ?? '—',
                                $r->voucher->payee ?? '—',
                                $r->voucher->amount ?? 0,
                                $r->amount_spent ?? 0,
                                $r->amount_returned ?? 0,
                                $r->variance ?? 0,
                                ucfirst($r->status),
                                $r->due_date ? $r->due_date->format('Y-m-d') : '—',
                                $days,
                                $r->remarks ?? '—',
                                $r->liquidated_at ? $r->liquidated_at->format('Y-m-d H:i:s') : '—',
                                // Voucher attachments
                                collect($r->voucher->attachment_paths ?? [])
                                    ->map(fn ($p) => url('storage/' . implode('/', array_map('rawurlencode', explode('/', $p)))))
                                    ->join(', '),
                            ];
                        })->toArray();

                        array_unshift($exportData, ['Voucher #', 'Payee', 'Original Amount', 'Spent', 'Returned', 'Variance', 'Status', 'Due Date', 'Days to Settle', 'Remarks', 'Liquidated At', 'Attachments']);

                        return response()->streamDownload(function () use ($exportData) {
                            $file = fopen('php://output', 'w');
                            foreach ($exportData as $row) {
                                fputcsv($file, $row);
                            }
                            fclose($file);
                        }, 'liquidations_export_' . now()->format('Y-m-d_H-i') . '.csv');
                    })
            ])
            ->actions([
                Tables\Actions\Action::make('settle')
                    ->label('Settle')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending'
                        && ! auth()->user()->hasRole('Approver'))
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),

                Tables\Actions\Action::make('edit_settled')
                    ->hiddenLabel()
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->tooltip('Override a settled liquidation (fully audited)')
                    ->visible(function ($record) {
                        if (!in_array($record->status, ['complete', 'excess', 'short'])) {
                            return false;
                        }
                        if (!auth()->user()->can('liquidation.edit_settled')) {
                            return false;
                        }
                        
                        // Show only if the linked RV is collected (paid)
                        if ($record->amount_returned > 0 && $record->voucher) {
                            $existingRv = \App\Models\Voucher::where('type', 'receipt')
                                ->where('description', 'like', "%{$record->voucher->voucher_number}%")
                                ->first();
                            
                            if ($existingRv && $existingRv->status !== 'paid') {
                                return false;
                            }
                        }
                        
                        return true;
                    })
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),

                Tables\Actions\ViewAction::make(),
            ])
            ->paginated([10, 25, 50, 100])
            ->bulkActions([]);
    }

    public static function getRelationManagers(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLiquidations::route('/'),
            'create' => Pages\CreateLiquidation::route('/create'),
            'edit'   => Pages\EditLiquidation::route('/{record}/edit'),
            'view'   => Pages\ViewLiquidation::route('/{record}'),
        ];
    }
}
