<?php

namespace App\Filament\Vouchers\Resources\ProRecordResource\Pages;

use App\Filament\Vouchers\Resources\ProRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProRecord extends ViewRecord
{
    protected static string $resource = ProRecordResource::class;

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return ($this->record->employee_name ?? 'PRO Record') . ' — PRO Profile';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to Tracking')
                ->url(ProRecordResource::getUrl('index'))
                ->icon('heroicon-m-arrow-left')
                ->color('gray'),

            Actions\EditAction::make()
                ->icon('heroicon-m-pencil-square'),
        ];
    }
}
