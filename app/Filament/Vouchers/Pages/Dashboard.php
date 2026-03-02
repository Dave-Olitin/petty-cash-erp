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
                        ->prefix('AED'),
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
                ])
                ->mutateFormDataUsing(function (array $data): array {
                    $lock = \Illuminate\Support\Facades\Cache::lock('replenishment_ref_generation', 5);

                    try {
                        $lock->block(5, function () use (&$data) {
                            $latest = \App\Models\FloatReplenishment::where('reference', 'like', 'REF-%')
                                ->orderBy('id', 'desc')
                                ->first();

                            $number = $latest ? intval(substr($latest->reference, 4)) + 1 : 1;
                            $data['reference'] = 'REF-' . str_pad($number, 4, '0', STR_PAD_LEFT);
                        });
                    } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'amount' => 'Could not generate reference due to system load. Please try again.',
                        ]);
                    }
                    
                    return $data;
                })
                ->successNotificationTitle('Fund Voucher recorded successfully'),
        ];
    }
}
