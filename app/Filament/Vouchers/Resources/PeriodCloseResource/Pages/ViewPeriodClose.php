<?php

namespace App\Filament\Vouchers\Resources\PeriodCloseResource\Pages;

use App\Filament\Vouchers\Resources\PeriodCloseResource;
use App\Services\PeriodCloseService;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;

class ViewPeriodClose extends ViewRecord
{
    protected static string $resource = PeriodCloseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('calculate')
                ->label('Calculate')
                ->icon('heroicon-o-calculator')
                ->color('info')
                ->visible(fn () => !$this->record->isClosed())
                ->action(function () {
                    app(PeriodCloseService::class)->aggregate($this->record);
                    $this->refreshFormData(['status']);
                    Notification::make()->title('Period figures calculated.')->success()->send();
                }),

            Actions\Action::make('close_period')
                ->label('Close Period')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->visible(fn () => !$this->record->isClosed())
                ->requiresConfirmation()
                ->modalHeading('Close Period')
                ->modalDescription('This will calculate final figures and mark the period as closed.')
                ->action(function () {
                    app(PeriodCloseService::class)->close($this->record, auth()->user());
                    $this->refreshFormData(['status', 'closed_at', 'closed_by']);
                    Notification::make()->title('Period closed successfully.')->success()->send();
                }),

            Actions\Action::make('reopen')
                ->label('Re-open')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->visible(fn () => $this->record->isClosed() && auth()->user()?->hasRole('Super Admin'))
                ->requiresConfirmation()
                ->action(function () {
                    app(PeriodCloseService::class)->reopen($this->record);
                    $this->refreshFormData(['status', 'closed_at', 'closed_by']);
                    Notification::make()->title('Period re-opened.')->warning()->send();
                }),

            Actions\EditAction::make()
                ->visible(fn () => !$this->record->isClosed()),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Period Overview')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Period name')
                            ->weight(FontWeight::Bold),
                        Infolists\Components\TextEntry::make('period_start')
                            ->label('Start date')
                            ->date('d M Y'),
                        Infolists\Components\TextEntry::make('period_end')
                            ->label('End date')
                            ->date('d M Y'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state) => $state === 'closed' ? 'success' : 'warning')
                            ->formatStateUsing(fn ($state) => strtoupper($state)),
                        Infolists\Components\TextEntry::make('closer.name')
                            ->label('Closed by')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('closed_at')
                            ->label('Closed at')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Open'),
                    ]),

                Infolists\Components\Grid::make(2)
                    ->schema([
                        Infolists\Components\Section::make('Petty Cash & Vouchers Summary')
                            ->schema([
                                Infolists\Components\TextEntry::make('voucher_count')
                                    ->label('Total Vouchers')
                                    ->badge()
                                    ->color('gray'),
                                Infolists\Components\TextEntry::make('total_vouchers_paid')
                                    ->label('Total Vouchers (Amount)')
                                    ->money('aed')
                                    ->weight(FontWeight::Bold)
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->color('primary'),
                                Infolists\Components\TextEntry::make('total_petty_cash_disbursed')
                                    ->label('Petty Cash Disbursed')
                                    ->money('aed'),
                            ]),

                        Infolists\Components\Section::make('Accounts Payable Summary')
                            ->schema([
                                Infolists\Components\TextEntry::make('purchase_entry_count')
                                    ->label('Total Entries')
                                    ->badge()
                                    ->color('gray'),
                                Infolists\Components\TextEntry::make('total_ap_billed')
                                    ->label('Total Billed')
                                    ->money('aed')
                                    ->color('warning'),
                                Infolists\Components\TextEntry::make('total_ap_paid')
                                    ->label('Total Paid')
                                    ->money('aed')
                                    ->color('success'),
                                Infolists\Components\TextEntry::make('total_ap_balance')
                                    ->label('Net AP Balance')
                                    ->money('aed')
                                    ->weight(FontWeight::Bold)
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->color(fn ($state) => (float)$state > 0.01 ? 'danger' : 'success'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Journal Summary')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('journal_entry_count')
                            ->label('Journal Entries')
                            ->badge()
                            ->color('gray'),
                        Infolists\Components\TextEntry::make('total_journal_dr')
                            ->label('Total Debit')
                            ->money('aed'),
                        Infolists\Components\TextEntry::make('total_journal_cr')
                            ->label('Total Credit')
                            ->money('aed'),
                    ]),

                Infolists\Components\Section::make('Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('closing_notes')
                            ->label('')
                            ->prose()
                            ->placeholder('No notes'),
                    ]),
            ]);
    }
}
