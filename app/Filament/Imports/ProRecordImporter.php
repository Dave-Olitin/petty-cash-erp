<?php

namespace App\Filament\Imports;

use App\Models\LedgerBranch;
use App\Models\ProCategory;
use App\Models\ProRecord;
use Carbon\Carbon;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class ProRecordImporter extends Importer
{
    protected static ?string $model = ProRecord::class;

    /**
     * Use sync connection by default so imports execute immediately in production
     * without requiring an active queue worker daemon. Can be overridden in .env.
     */
    public function getJobConnection(): ?string
    {
        return env('IMPORT_QUEUE_CONNECTION', 'sync');
    }

    /**
     * Robust date parser for Excel dates, serial numbers, and multiple string formats.
     */
    protected static function parseDate(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $v = trim((string) $value);

        // Check for Excel numeric serial date (e.g., 40000 to 70000)
        if (is_numeric($v) && (int) $v > 30000 && (int) $v < 70000) {
            $unix = ((int) $v - 25569) * 86400;
            return gmdate('Y-m-d', $unix);
        }

        // Handle DD/MM/YYYY, DD.MM.YYYY, DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\.\-](\d{1,2})[\/\.\-](\d{4})$/', $v, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        try {
            return Carbon::parse($v)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Robust currency/money parser to strip currency codes (AED), symbols, and thousand separators.
     * Defaults to 0.00 so non-nullable decimal columns in the database never trigger 1048 Cannot be null.
     */
    protected static function parseMoney(mixed $value, float $default = 0.00): float
    {
        if (blank($value)) {
            return $default;
        }

        $cleaned = preg_replace('/[^0-9.-]/', '', (string) $value);
        return is_numeric($cleaned) ? (float) $cleaned : $default;
    }

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('employee_name')
                ->label('Employee Name')
                ->exampleHeader('Employee Name')
                ->guess(['employee_name', 'Employee Name', 'EMPLOYEE NAME', 'Employee Full Name', 'Employee', 'Name', 'Full Name'])
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->example('Juan Dela Cruz'),

            ImportColumn::make('branch')
                ->label('Sponsoring Branch')
                ->exampleHeader('Sponsoring Branch')
                ->guess(['branch', 'Sponsoring Branch', 'SPONSORING BRANCH', 'Branch', 'BRANCH', 'ledgerBranch.name', 'Ledger Branch', 'Company', 'Branch Name'])
                ->rules(['nullable', 'string', 'max:255'])
                ->example('SB MAIN')
                ->fillRecordUsing(function (ProRecord $record, ?string $state): void {
                    if (blank($state)) {
                        return;
                    }
                    $branchName = trim((string) $state);
                    $branch = LedgerBranch::where('name', $branchName)
                        ->orWhere('name', 'like', "%{$branchName}%")
                        ->first();
                    if ($branch) {
                        $record->ledger_branch_id = $branch->id;
                        if (empty($record->license_expiry) && $branch->license_expiry) {
                            $record->license_expiry = $branch->license_expiry;
                        }
                    }
                }),

            ImportColumn::make('category')
                ->label('Category Tier')
                ->exampleHeader('Category Tier')
                ->guess(['category', 'Category Tier', 'CATEGORY TIER', 'Category', 'CATEGORY', 'proCategory.name', 'Labour Category', 'Company Category', 'Tier'])
                ->rules(['nullable', 'string', 'max:255'])
                ->example('Category 1 (High Skilled)')
                ->fillRecordUsing(function (ProRecord $record, ?string $state): void {
                    if (blank($state)) {
                        return;
                    }
                    $categoryName = trim((string) $state);
                    $category = ProCategory::where('code', $categoryName)
                        ->orWhere('name', $categoryName)
                        ->orWhere('name', 'like', "%{$categoryName}%")
                        ->first();
                    if ($category) {
                        $record->pro_category_id = $category->id;
                    }
                }),

            ImportColumn::make('process_type')
                ->label('Process Type')
                ->exampleHeader('Process Type')
                ->guess(['process_type', 'Process Type', 'PROCESS TYPE', 'Process'])
                ->rules(['nullable', 'string'])
                ->example('New Joiner (Local)')
                ->castStateUsing(function (?string $state): string {
                    $s = strtolower(trim((string) $state));
                    return match ($s) {
                        'new joiner (local)', 'local', 'new local', 'new joiner (inside uae)', 'new joiner (inside)', 'inside', 'new inside', 'new_inside' => 'new_inside',
                        'new joiner (ph)', 'ph', 'new ph', 'new joiner (outside uae)', 'new joiner (outside)', 'outside', 'new outside', 'new_outside' => 'new_outside',
                        'visa renewal', 'renewal' => 'renewal',
                        'visa cancellation', 'cancellation' => 'cancellation',
                        default => in_array($s, ['new_inside', 'new_outside', 'renewal', 'cancellation']) ? $s : 'new_inside',
                    };
                }),

            ImportColumn::make('status')
                ->label('Application Status')
                ->exampleHeader('Application Status')
                ->guess(['status', 'Application Status', 'APPLICATION STATUS', 'Status', 'STATUS'])
                ->rules(['nullable', 'string'])
                ->example('In Progress')
                ->castStateUsing(function (?string $state): string {
                    $s = strtolower(str_replace([' ', '-'], '_', trim((string) $state)));
                    return in_array($s, ['draft', 'in_progress', 'completed', 'on_hold', 'cancelled']) ? $s : 'draft';
                }),

            ImportColumn::make('nationality')
                ->label('Nationality')
                ->exampleHeader('Nationality')
                ->guess(['nationality', 'Nationality', 'NATIONALITY'])
                ->rules(['nullable', 'string', 'max:100'])
                ->example('Philippines'),

            ImportColumn::make('job_description')
                ->label('Designation')
                ->exampleHeader('Designation')
                ->guess(['job_description', 'Designation', 'DESIGNATION / JOB TITLE', 'Job Description', 'Designation / Job Title', 'JOB DESCRIPTION'])
                ->rules(['nullable', 'string', 'max:255'])
                ->example('Senior Accountant'),

            ImportColumn::make('passport_number')
                ->label('Passport #')
                ->exampleHeader('Passport #')
                ->guess(['passport_number', 'Passport #', 'PASSPORT #', 'Passport Number', 'Passport No'])
                ->rules(['nullable', 'string', 'max:50'])
                ->example('P1234567A'),

            ImportColumn::make('passport_expiry')
                ->label('Passport Expiry')
                ->exampleHeader('Passport Expiry')
                ->guess(['passport_expiry', 'Passport Expiry', 'PASSPORT EXPIRY'])
                ->rules(['nullable', 'date'])
                ->castStateUsing(fn (?string $state) => static::parseDate($state))
                ->example('2028-12-31'),

            ImportColumn::make('residence_visa_number')
                ->label('Residence Visa #')
                ->exampleHeader('Residence Visa #')
                ->guess(['residence_visa_number', 'Residence Visa #', 'RESIDENCE VISA #', 'Visa #', 'Visa Number'])
                ->rules(['nullable', 'string', 'max:50'])
                ->example('201/2024/1234567'),

            ImportColumn::make('residence_visa_expiry')
                ->label('Residence Visa Expiry')
                ->exampleHeader('Residence Visa Expiry')
                ->guess(['residence_visa_expiry', 'Residence Visa Expiry', 'RESIDENCE VISA EXPIRY', 'Visa Expiry'])
                ->rules(['nullable', 'date'])
                ->castStateUsing(fn (?string $state) => static::parseDate($state))
                ->example('2026-11-30'),

            ImportColumn::make('unified_id')
                ->label('Unified ID #')
                ->exampleHeader('Unified ID #')
                ->guess(['unified_id', 'Unified ID #', 'UNIFIED ID #', 'Unified Number', 'UID'])
                ->rules(['nullable', 'string', 'max:50'])
                ->example('123456789'),

            ImportColumn::make('person_code')
                ->label('Person Code #')
                ->exampleHeader('Person Code #')
                ->guess(['person_code', 'Person Code #', 'PERSON CODE #', 'Person Code'])
                ->rules(['nullable', 'string', 'max:50'])
                ->example('987654321'),

            ImportColumn::make('emirates_id_number')
                ->label('Emirates ID #')
                ->exampleHeader('Emirates ID #')
                ->guess(['emirates_id_number', 'Emirates ID #', 'EMIRATES ID #', 'Emirates ID Number', 'EID'])
                ->rules(['nullable', 'string', 'max:50'])
                ->example('784-1990-1234567-1'),

            ImportColumn::make('emirates_id_expiry')
                ->label('Emirates ID Expiry')
                ->exampleHeader('Emirates ID Expiry')
                ->guess(['emirates_id_expiry', 'Emirates ID Expiry', 'EMIRATES ID EXPIRY', 'EID Expiry'])
                ->rules(['nullable', 'date'])
                ->castStateUsing(fn (?string $state) => static::parseDate($state))
                ->example('2026-11-30'),

            ImportColumn::make('labour_card_number')
                ->label('Labour Card #')
                ->exampleHeader('Labour Card #')
                ->guess(['labour_card_number', 'Labour Card #', 'LABOUR CARD #', 'Labour Card Number'])
                ->rules(['nullable', 'string', 'max:50'])
                ->example('12345678'),

            ImportColumn::make('labour_card_expiry')
                ->label('Labour Card Expiry')
                ->exampleHeader('Labour Card Expiry')
                ->guess(['labour_card_expiry', 'Labour Card Expiry', 'LABOUR CARD EXPIRY'])
                ->rules(['nullable', 'date'])
                ->castStateUsing(fn (?string $state) => static::parseDate($state)),

            ImportColumn::make('labour_contract_expiry')
                ->label('Labour Contract Expiry')
                ->exampleHeader('Labour Contract Expiry')
                ->guess(['labour_contract_expiry', 'Labour Contract Expiry', 'LABOUR CONTRACT EXPIRY'])
                ->rules(['nullable', 'date'])
                ->castStateUsing(fn (?string $state) => static::parseDate($state)),

            ImportColumn::make('license_expiry')
                ->label('Branch License Expiry')
                ->exampleHeader('Branch License Expiry')
                ->guess(['license_expiry', 'Branch License Expiry', 'BRANCH LICENSE EXPIRY', 'License Expiry'])
                ->rules(['nullable', 'date'])
                ->castStateUsing(fn (?string $state) => static::parseDate($state)),

            ImportColumn::make('grace_period_days')
                ->label('Grace Period (Days)')
                ->exampleHeader('Grace Period (Days)')
                ->guess(['grace_period_days', 'Grace Period (Days)', 'GRACE PERIOD (DAYS)', 'Grace Period'])
                ->numeric()
                ->rules(['nullable', 'integer', 'min:0'])
                ->castStateUsing(function ($state) {
                    if (blank($state)) return 30;
                    $cleaned = preg_replace('/[^0-9]/', '', (string) $state);
                    return is_numeric($cleaned) ? (int) $cleaned : 30;
                })
                ->example('30'),

            ImportColumn::make('fines_start_date')
                ->label('Fines Start Date')
                ->exampleHeader('Fines Start Date')
                ->guess(['fines_start_date', 'Fines Start Date', 'FINES START DATE', 'Fines Will Start Date'])
                ->rules(['nullable', 'date'])
                ->castStateUsing(fn (?string $state) => static::parseDate($state)),

            ImportColumn::make('fine_eid_20')
                ->label('Daily Fine EID-20')
                ->exampleHeader('Daily Fine EID-20')
                ->guess(['fine_eid_20', 'Daily Fine EID-20', 'DAILY FINE EID-20 (AED)', 'FINE (EID-20)'])
                ->numeric()
                ->rules(['nullable', 'numeric'])
                ->castStateUsing(fn ($state) => static::parseMoney($state)),

            ImportColumn::make('fine_pr_50')
                ->label('Daily Fine PR-50')
                ->exampleHeader('Daily Fine PR-50')
                ->guess(['fine_pr_50', 'Daily Fine PR-50', 'DAILY FINE PR-50 (AED)', 'FINE (PR-50)'])
                ->numeric()
                ->rules(['nullable', 'numeric'])
                ->castStateUsing(fn ($state) => static::parseMoney($state)),

            // Stage Costs
            ImportColumn::make('offer_letter_cost')
                ->label('Offer Letter Cost')
                ->exampleHeader('Offer Letter Cost')
                ->guess(['offer_letter_cost', 'Offer Letter Cost', 'OFFER LETTER COST (AED)', 'OFFER LETTER'])
                ->numeric()
                ->rules(['nullable', 'numeric'])
                ->castStateUsing(fn ($state) => static::parseMoney($state)),

            ImportColumn::make('dubins_cost')
                ->label('Dubins Cost')
                ->exampleHeader('Dubins Cost')
                ->guess(['dubins_cost', 'Dubins Cost', 'DUBINS COST (AED)', 'DUBINS'])
                ->numeric()
                ->rules(['nullable', 'numeric'])
                ->castStateUsing(fn ($state) => static::parseMoney($state)),

            ImportColumn::make('wp_submission_cost')
                ->label('Work Permit Cost')
                ->exampleHeader('Work Permit Cost')
                ->guess(['wp_submission_cost', 'Work Permit Cost', 'WORK PERMIT COST (AED)', 'WP SUBMISSION'])
                ->numeric()
                ->rules(['nullable', 'numeric'])
                ->castStateUsing(fn ($state) => static::parseMoney($state)),

            ImportColumn::make('evisa_cost')
                ->label('eVisa Cost')
                ->exampleHeader('eVisa Cost')
                ->guess(['evisa_cost', 'eVisa Cost', 'EVISA COST (AED)', 'EVISA'])
                ->numeric()
                ->rules(['nullable', 'numeric'])
                ->castStateUsing(fn ($state) => static::parseMoney($state)),

            ImportColumn::make('change_status_cost')
                ->label('Change Status Cost')
                ->exampleHeader('Change Status Cost')
                ->guess(['change_status_cost', 'Change Status Cost', 'CHANGE STATUS COST (AED)', 'CHANGE STATUS'])
                ->numeric()
                ->rules(['nullable', 'numeric'])
                ->castStateUsing(fn ($state) => static::parseMoney($state)),

            ImportColumn::make('iloe_cost')
                ->label('ILOE Cost')
                ->exampleHeader('ILOE Cost')
                ->guess(['iloe_cost', 'ILOE Cost', 'ILOE COST (AED)', 'ILOE'])
                ->numeric()
                ->rules(['nullable', 'numeric'])
                ->castStateUsing(fn ($state) => static::parseMoney($state)),

            ImportColumn::make('contract_submission_cost')
                ->label('Labour Contract Cost')
                ->exampleHeader('Labour Contract Cost')
                ->guess(['contract_submission_cost', 'Labour Contract Cost', 'LABOUR CONTRACT COST (AED)', 'CONTRACT SUBMISSION'])
                ->numeric()
                ->rules(['nullable', 'numeric'])
                ->castStateUsing(fn ($state) => static::parseMoney($state)),

            ImportColumn::make('emirates_id_stage_cost')
                ->label('Emirates ID Stage Cost')
                ->exampleHeader('Emirates ID Stage Cost')
                ->guess(['emirates_id_stage_cost', 'Emirates ID Stage Cost', 'EMIRATES ID STAGE COST (AED)', 'EMIRATES ID'])
                ->numeric()
                ->rules(['nullable', 'numeric'])
                ->castStateUsing(fn ($state) => static::parseMoney($state)),

            ImportColumn::make('residence_visa_stage_cost')
                ->label('Residence Visa Stage Cost')
                ->exampleHeader('Residence Visa Stage Cost')
                ->guess(['residence_visa_stage_cost', 'Residence Visa Stage Cost', 'RESIDENCE VISA STAGE COST (AED)', 'RESIDENCE VISA'])
                ->numeric()
                ->rules(['nullable', 'numeric'])
                ->castStateUsing(fn ($state) => static::parseMoney($state)),

            ImportColumn::make('total_cost')
                ->label('Total Processing Cost')
                ->exampleHeader('Total Processing Cost')
                ->guess(['total_cost', 'Total Processing Cost', 'TOTAL PROCESSING COST (AED)', 'TOTAL', 'Total Cost'])
                ->numeric()
                ->rules(['nullable', 'numeric'])
                ->castStateUsing(fn ($state) => static::parseMoney($state)),

            ImportColumn::make('remarks')
                ->label('Remarks')
                ->exampleHeader('Remarks')
                ->guess(['remarks', 'Remarks', 'REMARKS / NOTES', 'Remarks / Notes', 'Notes'])
                ->rules(['nullable', 'string']),
        ];
    }

    public function resolveRecord(): ?ProRecord
    {
        $passport = trim((string) ($this->data['passport_number'] ?? ''));
        $eid = trim((string) ($this->data['emirates_id_number'] ?? ''));
        $unified = trim((string) ($this->data['unified_id'] ?? ''));

        $record = null;

        if (! empty($passport)) {
            $record = ProRecord::where('passport_number', $passport)->first();
        }

        if (! $record && ! empty($eid)) {
            $record = ProRecord::where('emirates_id_number', $eid)->first();
        }

        if (! $record && ! empty($unified)) {
            $record = ProRecord::where('unified_id', $unified)->first();
        }

        if (! $record) {
            $record = new ProRecord();
            $record->user_id = $this->import->user_id ?? auth()->id();
            $record->status = 'draft';
        }

        // Branch Resolution
        $branchName = trim((string) ($this->data['branch'] ?? ''));
        if (! empty($branchName)) {
            $branch = LedgerBranch::where('name', $branchName)
                ->orWhere('name', 'like', "%{$branchName}%")
                ->first();
            if ($branch) {
                $record->ledger_branch_id = $branch->id;
                if (empty($record->license_expiry) && $branch->license_expiry) {
                    $record->license_expiry = $branch->license_expiry;
                }
            }
        }

        // Category Resolution
        $categoryName = trim((string) ($this->data['category'] ?? ''));
        if (! empty($categoryName)) {
            $category = ProCategory::where('code', $categoryName)
                ->orWhere('name', $categoryName)
                ->orWhere('name', 'like', "%{$categoryName}%")
                ->first();
            if ($category) {
                $record->pro_category_id = $category->id;
            }
        }

        return $record;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'PRO Records import completed: ' . number_format($import->successful_rows) . ' ' . str('record')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed.';
        }

        return $body;
    }
}
