<?php

namespace App\Filament\Vouchers\Resources\JournalEntryResource\Pages;

use App\Filament\Vouchers\Resources\JournalEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateJournalEntry extends CreateRecord
{
    protected static string $resource = JournalEntryResource::class;
}
