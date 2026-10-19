<?php

namespace App\Filament\Vouchers\Resources\ProCategoryResource\Pages;

use App\Filament\Vouchers\Resources\ProCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProCategories extends ListRecords
{
    protected static string $resource = ProCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New PRO Category'),
        ];
    }
}
