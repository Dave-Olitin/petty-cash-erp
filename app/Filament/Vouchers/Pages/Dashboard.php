<?php

namespace App\Filament\Vouchers\Pages;

use Filament\Actions\CreateAction;
use App\Models\FloatReplenishment;
use App\Filament\Vouchers\Resources\VoucherResource;
use Filament\Forms;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static ?string $navigationLabel = 'Overview';
    protected static ?string $title = 'Overview';

    public static function canAccess(): bool
    {
        return auth()->user()->can('access_vouchers_panel');
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('create_voucher')
                ->label('Create Voucher')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()->can('voucher.create'))
                ->url(fn (): string => VoucherResource::getUrl('create')),

            CreateAction::make('fund_voucher')
                ->model(FloatReplenishment::class)
                ->label('Fund Voucher')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => auth()->user()->can('voucher.create'))
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->required()
                        ->numeric()
                        ->prefix('AED')
                        ->live(),
                    Forms\Components\DatePicker::make('date')
                        ->required()
                        ->default(now()),
                    Forms\Components\TextInput::make('reference')
                        ->label('Reference (Auto-Generated)')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Will be generated upon save'),
                    Forms\Components\Textarea::make('remarks')
                        ->columnSpanFull(),
                    Forms\Components\Hidden::make('created_by')
                        ->default(fn () => auth()->id()),

                    // ── DENOMINATION BREAKDOWN ──────────────────────────
                    Forms\Components\Section::make('Cash Denomination Breakdown')
                        ->icon('heroicon-o-banknotes')
                        ->description('Required: total must equal the replenishment amount above.')
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('bill_1000')->label('1000 Bills')->numeric()->default(0)->minValue(0),
                                Forms\Components\TextInput::make('bill_500')->label('500 Bills')->numeric()->default(0)->minValue(0),
                                Forms\Components\TextInput::make('bill_200')->label('200 Bills')->numeric()->default(0)->minValue(0),
                                Forms\Components\TextInput::make('bill_100')->label('100 Bills')->numeric()->default(0)->minValue(0),
                                Forms\Components\TextInput::make('bill_50')->label('50 Bills')->numeric()->default(0)->minValue(0),
                                Forms\Components\TextInput::make('bill_20')->label('20 Bills')->numeric()->default(0)->minValue(0),
                                Forms\Components\TextInput::make('bill_10')->label('10 Bills')->numeric()->default(0)->minValue(0),
                                Forms\Components\TextInput::make('bill_5')->label('5 Bills')->numeric()->default(0)->minValue(0),
                                Forms\Components\TextInput::make('coin_1')->label('1 Coins')->numeric()->default(0)->minValue(0),
                                Forms\Components\TextInput::make('coin_0_50')->label('0.50 Coins')->numeric()->default(0)->step('1')->minValue(0),
                                Forms\Components\TextInput::make('coin_0_25')->label('0.25 Coins')->numeric()->default(0)->step('1')->minValue(0),
                            ]),
                        ]),
                ])
                ->mutateFormDataUsing(function (array $data): array {
                    $denomKeys = ['bill_1000', 'bill_500', 'bill_200', 'bill_100', 'bill_50',
                                  'bill_20', 'bill_10', 'bill_5', 'coin_1', 'coin_0_50', 'coin_0_25'];

                    // Always validate — denomination total must equal the replenishment amount
                    $total = ((int) ($data['bill_1000'] ?? 0) * 1000) + ((int) ($data['bill_500'] ?? 0) * 500) + ((int) ($data['bill_200'] ?? 0) * 200)
                        + ((int) ($data['bill_100'] ?? 0) * 100) + ((int) ($data['bill_50'] ?? 0) * 50) + ((int) ($data['bill_20'] ?? 0) * 20)
                        + ((int) ($data['bill_10'] ?? 0) * 10) + ((int) ($data['bill_5'] ?? 0) * 5) + ((int) ($data['coin_1'] ?? 0) * 1)
                        + ((int) ($data['coin_0_50'] ?? 0) * 0.50) + ((int) ($data['coin_0_25'] ?? 0) * 0.25);

                    if (round((float) $total, 2) !== round((float) $data['amount'], 2)) {
                        \Filament\Notifications\Notification::make()
                            ->title('Denomination Mismatch')
                            ->danger()
                            ->body('The cash breakdown (AED ' . number_format($total, 2) . ') must exactly match the replenishment amount (AED ' . number_format((float) $data['amount'], 2) . ').')
                            ->send();
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'bill_1000' => 'Breakdown total AED ' . number_format($total, 2) . ' does not match replenishment amount AED ' . number_format((float) $data['amount'], 2) . '.',
                        ]);
                    }

                    $hasAny = collect($denomKeys)->contains(fn ($f) => (int) ($data[$f] ?? 0) > 0);

                    session()->put('_pending_denom', array_intersect_key($data, array_flip($denomKeys)));
                    foreach ($denomKeys as $key) {
                        unset($data[$key]);
                    }

                    $lock = \Illuminate\Support\Facades\Cache::lock('replenishment_ref_generation', 5);

                    try {
                        $lock->block(5, function () use (&$data) {
                            $count = \App\Models\FloatReplenishment::count() + 1;
                            $data['reference'] = 'CI_' . str_pad($count, 4, '0', STR_PAD_LEFT);
                        });
                    } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'amount' => 'Could not generate reference due to system load. Please try again.',
                        ]);
                    }
                    
                    return $data;
                })
                ->after(function (\App\Models\FloatReplenishment $record) {
                    // Pull denomination data that was stashed in session by mutateFormDataUsing
                    $denom = session()->pull('_pending_denom', []);

                    $denomFields = ['bill_1000', 'bill_500', 'bill_200', 'bill_100', 'bill_50',
                                    'bill_20', 'bill_10', 'bill_5', 'coin_1', 'coin_0_50', 'coin_0_25'];

                    $hasAny = collect($denomFields)->contains(fn ($f) => (int) ($denom[$f] ?? 0) > 0);

                    // The FloatReplenishmentObserver already created a placeholder denomination on 'created'.
                    // If the user entered a detailed cash breakdown, UPDATE that existing record
                    // instead of creating a second one (which would cause duplicate entries in the Cash Log).
                    if ($hasAny) {
                        $total = ((int) ($denom['bill_1000'] ?? 0) * 1000) + ((int) ($denom['bill_500'] ?? 0) * 500) + ((int) ($denom['bill_200'] ?? 0) * 200)
                            + ((int) ($denom['bill_100'] ?? 0) * 100) + ((int) ($denom['bill_50'] ?? 0) * 50) + ((int) ($denom['bill_20'] ?? 0) * 20)
                            + ((int) ($denom['bill_10'] ?? 0) * 10) + ((int) ($denom['bill_5'] ?? 0) * 5) + ((int) ($denom['coin_1'] ?? 0) * 1)
                            + ((int) ($denom['coin_0_50'] ?? 0) * 0.50) + ((int) ($denom['coin_0_25'] ?? 0) * 0.25);

                        $record->denominations()->updateOrCreate(
                            [], // match on the morphable FK (already scoped)
                            [
                                'bill_1000'    => $denom['bill_1000'] ?: 0,
                                'bill_500'     => $denom['bill_500'] ?: 0,
                                'bill_200'     => $denom['bill_200'] ?: 0,
                                'bill_100'     => $denom['bill_100'] ?: 0,
                                'bill_50'      => $denom['bill_50'] ?: 0,
                                'bill_20'      => $denom['bill_20'] ?: 0,
                                'bill_10'      => $denom['bill_10'] ?: 0,
                                'bill_5'       => $denom['bill_5'] ?: 0,
                                'coin_1'       => $denom['coin_1'] ?: 0,
                                'coin_0_50'    => $denom['coin_0_50'] ?: 0,
                                'coin_0_25'    => $denom['coin_0_25'] ?: 0,
                                'total_amount' => $total,
                                'change_given' => 0,
                            ]
                        );
                    }
                })
                ->successNotificationTitle('Fund Voucher recorded successfully'),

        ];
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Vouchers\Widgets\VoucherStatsOverview::class,
            \App\Filament\Vouchers\Widgets\DenominationStatsOverview::class,
            \App\Filament\Vouchers\Widgets\LiquidationSummaryWidget::class,
            \App\Filament\Vouchers\Widgets\FloatReplenishmentsTable::class,
            \App\Filament\Vouchers\Widgets\DenominationsTableWidget::class,
        ];
    }
}
