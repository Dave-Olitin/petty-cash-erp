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
                        ->options(
                            Voucher::where('type', 'petty_cash')
                                ->where('status', 'paid')
                                ->whereIn('liquidation_status', ['pending', 'overdue'])
                                ->with('liquidation')
                                ->get()
                                ->mapWithKeys(fn ($v) => [
                                    $v->id => $v->voucher_number . ' — ' . $v->payee . ' (AED ' . number_format($v->amount, 2) . ')'
                                ])->toArray()
                        )
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
                ]),

            Forms\Components\Section::make('Liquidation Details')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('amount_spent')
                            ->label('Amount Spent (w/ receipts) AED')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                $original = (float) ($get('_voucher_amount') ?? 0);
                                $spent = (float) ($get('amount_spent') ?? 0);
                                $returned = (float) ($get('amount_returned') ?? 0);
                                $short = max(0, $original - $spent - $returned);
                                $set('amount_short', $short);
                            }),

                        Forms\Components\TextInput::make('amount_returned')
                            ->label('Cash Returned to Box (AED)')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                $original = (float) ($get('_voucher_amount') ?? 0);
                                $spent = (float) ($get('amount_spent') ?? 0);
                                $returned = (float) ($get('amount_returned') ?? 0);
                                $short = max(0, $original - $spent - $returned);
                                $set('amount_short', $short);
                            }),
                    ]),

                    Forms\Components\Placeholder::make('liquidation_summary')
                        ->label('Live Summary')
                        ->content(function (Forms\Get $get) {
                            $original = (float) ($get('_voucher_amount') ?? 0);
                            $spent = (float) ($get('amount_spent') ?? 0);
                            $returned = (float) ($get('amount_returned') ?? 0);
                            $accounted = $spent + $returned;
                            $diff = round($accounted - $original, 2);

                            $status = match (true) {
                                abs($diff) <= 0.01 => '✅ <strong style="color:green">Exact — Ready to Liquidate</strong>',
                                $diff < 0          => '🔴 <strong style="color:red">Short by AED ' . number_format(abs($diff), 2) . ' — Employee still owes cash or receipts</strong>',
                                $diff > 0          => '🔵 <strong style="color:blue">Excess by AED ' . number_format($diff, 2) . ' — More cash returned than original</strong>',
                                default            => '—',
                            };

                            return new \Illuminate\Support\HtmlString(
                                "<div style='line-height:2'>" .
                                "💰 Original Amount: <strong>AED " . number_format($original, 2) . "</strong><br>" .
                                "🧾 Amount Spent: <strong>AED " . number_format($spent, 2) . "</strong><br>" .
                                "💵 Cash Returned: <strong>AED " . number_format($returned, 2) . "</strong><br>" .
                                "📊 Status: {$status}" .
                                "</div>"
                            );
                        }),

                    Forms\Components\DatePicker::make('due_date')
                        ->label('Liquidation Deadline')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->helperText('Leave blank to use the system default (' . config('liquidation.deadline_days', 5) . ' days from payment).'),

                    Forms\Components\Textarea::make('remarks')
                        ->label('Remarks / Notes')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('liquidated_by')
                        ->label('Filed By (Custodian)')
                        ->relationship('custodian', 'name')
                        ->searchable()
                        ->preload()
                        ->default(fn () => auth()->id())
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                Liquidation::query()->with(['voucher.user', 'custodian'])
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('voucher.voucher_number')
                    ->label('Voucher #')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => $record->voucher_id
                        ? \App\Filament\Vouchers\Resources\VoucherResource::getUrl('view', ['record' => $record->voucher_id])
                        : null)
                    ->openUrlInNewTab(),

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

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'complete',
                        'danger'  => 'short',
                        'info'    => 'excess',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

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
                        'pending'  => 'Pending',
                        'complete' => 'Complete',
                        'short'    => 'Short',
                        'excess'   => 'Excess',
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
                    ->label('Edit Settlement')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->tooltip('Override a settled liquidation (fully audited)')
                    ->visible(fn ($record) => in_array($record->status, ['complete', 'excess', 'short'])
                        && auth()->user()->can('liquidation.edit_settled'))
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),

                Tables\Actions\ViewAction::make(),
            ])
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
