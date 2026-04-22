<?php

namespace App\Filament\Vouchers\Resources\AccountCodeResource\Pages;

use App\Filament\Vouchers\Resources\AccountCodeResource;
use App\Filament\Vouchers\Resources\AccountCodeResource\RelationManagers\VoucherItemsRelationManager;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAccountCode extends ViewRecord
{
    protected static string $resource = AccountCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $record      = $this->record;
        $excluded    = ['rejected', 'voided'];

        $totalDebit  = (float) $record->debitItems()->sum('debit');
        $totalCredit = (float) $record->creditItems()->sum('credit');
        $netBalance  = $totalDebit - $totalCredit;
        $totalItems  = $record->voucherItems()
                            ->whereHas('voucher', fn ($q) => $q->whereNotIn('status', $excluded))
                            ->count();

        return $infolist
            ->schema([
                // ── Account identity ─────────────────────────────────────────
                Section::make('Account Details')
                    ->icon('heroicon-o-bookmark')
                    ->schema([
                        TextEntry::make('code')
                            ->label('Account Code')
                            ->badge()
                            ->color('primary')
                            ->size(TextEntry\TextEntrySize::Large),
                        TextEntry::make('name')
                            ->label('Account Name')
                            ->size(TextEntry\TextEntrySize::Large),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime()
                            ->since(),
                    ])
                    ->columns(3),

                // ── Financial summary ────────────────────────────────────────
                Section::make('Summary')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        TextEntry::make('_total_dr')
                            ->label('Total DR')
                            ->state(fn () => 'AED ' . number_format($totalDebit, 2))
                            ->color('success')
                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                            ->size(TextEntry\TextEntrySize::Large),

                        TextEntry::make('_total_cr')
                            ->label('Total CR')
                            ->state(fn () => 'AED ' . number_format($totalCredit, 2))
                            ->color('danger')
                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                            ->size(TextEntry\TextEntrySize::Large),

                        TextEntry::make('_net_balance')
                            ->label('Net Balance (DR − CR)')
                            ->state(fn () => 'AED ' . number_format(abs($netBalance), 2) . ($netBalance < 0 ? ' CR' : ' DR'))
                            ->color($netBalance >= 0 ? 'success' : 'danger')
                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                            ->size(TextEntry\TextEntrySize::Large),

                        TextEntry::make('_total_lines')
                            ->label('Total Line Items')
                            ->state(fn () => $totalItems)
                            ->badge()
                            ->color('primary')
                            ->size(TextEntry\TextEntrySize::Large),
                    ])
                    ->columns(4),
            ]);
    }

    public function getRelationManagers(): array
    {
        return [
            VoucherItemsRelationManager::class,
        ];
    }
}
