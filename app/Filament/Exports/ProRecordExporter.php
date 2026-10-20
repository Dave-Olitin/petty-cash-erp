<?php

namespace App\Filament\Exports;

use App\Models\ProRecord;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ProRecordExporter extends Exporter
{
    protected static ?string $model = ProRecord::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('employee_name')->label('Employee Name'),
            ExportColumn::make('ledgerBranch.name')->label('Sponsoring Branch'),
            ExportColumn::make('proCategory.name')->label('Category Tier'),
            ExportColumn::make('process_type')
                ->label('Process Type')
                ->formatStateUsing(fn ($state) => match ($state) {
                    'new_inside'   => 'New Joiner (Inside UAE)',
                    'new_outside'  => 'New Joiner (Outside UAE)',
                    'renewal'      => 'Visa Renewal',
                    'cancellation' => 'Visa Cancellation',
                    default        => ucfirst(str_replace('_', ' ', (string) $state)),
                }),
            ExportColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn ($state) => match ($state) {
                    'completed'   => 'Completed',
                    'in_progress' => 'In Progress',
                    'on_hold'     => 'On Hold',
                    'draft'       => 'Draft',
                    'cancelled'   => 'Cancelled',
                    default        => ucfirst(str_replace('_', ' ', (string) $state)),
                }),
            ExportColumn::make('nationality')->label('Nationality'),
            ExportColumn::make('job_description')->label('Designation / Job Description'),
            ExportColumn::make('passport_number')->label('Passport #'),
            ExportColumn::make('passport_expiry')->label('Passport Expiry'),
            ExportColumn::make('unified_id')->label('Unified ID #'),
            ExportColumn::make('person_code')->label('Person Code #'),
            ExportColumn::make('emirates_id_number')->label('Emirates ID #'),
            ExportColumn::make('emirates_id_expiry')->label('Emirates ID Expiry'),
            ExportColumn::make('residence_visa_number')->label('Residence Visa #'),
            ExportColumn::make('residence_visa_expiry')->label('Residence Visa Expiry'),
            ExportColumn::make('labour_card_number')->label('Labour Card #'),
            ExportColumn::make('labour_card_expiry')->label('Labour Card Expiry'),
            ExportColumn::make('labour_contract_expiry')->label('Labour Contract Expiry'),
            ExportColumn::make('license_expiry')->label('License Expiry'),
            ExportColumn::make('grace_period_days')->label('Grace Period (Days)'),
            ExportColumn::make('fines_start_date')->label('Fines Start Date'),
            ExportColumn::make('calculated_total_fines')
                ->label('Total Accrued Fines (AED)')
                ->state(fn (ProRecord $record): float => $record->calculated_total_fines),
            ExportColumn::make('total_cost')->label('Total Processing Cost (AED)'),
            ExportColumn::make('remarks')->label('Remarks'),
            ExportColumn::make('created_at')->label('Created Date'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'PRO Records export completed: ' . number_format($export->successful_rows) . ' ' . str('record')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed.';
        }

        return $body;
    }
}
