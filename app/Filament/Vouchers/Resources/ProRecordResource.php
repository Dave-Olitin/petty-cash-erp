<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\ProRecordResource\Pages;
use App\Models\Branch;
use App\Models\ProRecord;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;

class ProRecordResource extends Resource
{
    protected static ?string $model = ProRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'PRO & Government Relations';
    protected static ?string $navigationLabel = 'PRO Tracking';
    protected static ?string $modelLabel = 'PRO Record';
    protected static ?string $pluralModelLabel = 'PRO Records';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ── LEFT MAIN WORKSPACE (2 Columns on Desktop) ──────────────
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Wizard::make([
                            // ── STEP 1: Employee Identity & Government IDs ───────────
                            Forms\Components\Wizard\Step::make('Employee & Government IDs')
                                ->icon('heroicon-o-user')
                                ->description('Personal & Identifiers')
                                ->schema([
                                        Forms\Components\Section::make('Core Employee Identity')
                                            ->schema([
                                                Forms\Components\TextInput::make('employee_name')
                                                    ->label('Employee Full Name')
                                                    ->placeholder('As shown in passport')
                                                    ->required()
                                                    ->maxLength(255),

                                                Forms\Components\TextInput::make('nationality')
                                                    ->label('Nationality')
                                                    ->placeholder('e.g. Philippines, India, Egypt')
                                                    ->maxLength(100),

                                                Forms\Components\TextInput::make('job_description')
                                                    ->label('Job Description / Designation')
                                                    ->placeholder('e.g. Sales Executive, Cashier, Driver')
                                                    ->maxLength(255),

                                                Forms\Components\Select::make('user_id')
                                                    ->label('Linked ERP User (Optional)')
                                                    ->relationship('user', 'name')
                                                    ->searchable()
                                                    ->preload()
                                                    ->nullable(),
                                            ])->columns(2),

                                        Forms\Components\Section::make('Passports & Residence Visa')
                                            ->schema([
                                                Forms\Components\TextInput::make('passport_number')
                                                    ->label('Passport #')
                                                    ->placeholder('Passport Number')
                                                    ->maxLength(50),

                                                Forms\Components\DatePicker::make('passport_expiry')
                                                    ->label('Passport Expiry')
                                                    ->helperText('Flagged if less than 6 months validity remains.')
                                                    ->native(false),

                                                Forms\Components\TextInput::make('residence_visa_number')
                                                    ->label('Residence Visa #')
                                                    ->placeholder('File / Visa Number')
                                                    ->maxLength(50),

                                                Forms\Components\DatePicker::make('residence_visa_expiry')
                                                    ->label('Residence Visa Expiry')
                                                    ->helperText('Used to calculate grace period and fines start date.')
                                                    ->native(false)
                                                    ->reactive()
                                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                        if ($state) {
                                                            $grace = (int) ($get('grace_period_days') ?: 30);
                                                            $start = Carbon::parse($state)->addDays($grace)->format('Y-m-d');
                                                            $set('fines_start_date', $start);
                                                        }
                                                    }),
                                            ])->columns(2),

                                        Forms\Components\Section::make('MOHRE, Labour & Emirates ID Numbers')
                                            ->schema([
                                                Forms\Components\TextInput::make('person_code')
                                                    ->label('Person Code # (MOHRE)')
                                                    ->placeholder('MOHRE Worker Person Code')
                                                    ->maxLength(50),

                                                Forms\Components\TextInput::make('unified_id')
                                                    ->label('Unified ID # (UID)')
                                                    ->placeholder('ICP/GDRFA Unified Number')
                                                    ->maxLength(50),

                                                Forms\Components\TextInput::make('emirates_id_number')
                                                    ->label('Emirates ID #')
                                                    ->placeholder('784-YYYY-XXXXXXX-X')
                                                    ->maxLength(50),

                                                Forms\Components\DatePicker::make('emirates_id_expiry')
                                                    ->label('Emirates ID Expiry')
                                                    ->native(false),

                                                Forms\Components\TextInput::make('labour_card_number')
                                                    ->label('Labour Card # (Work Permit)')
                                                    ->placeholder('MOHRE Work Permit #')
                                                    ->maxLength(50),

                                                Forms\Components\DatePicker::make('labour_card_expiry')
                                                    ->label('Labour Card Expiry')
                                                    ->native(false),

                                                Forms\Components\DatePicker::make('labour_contract_expiry')
                                                    ->label('Labour Contract Expiry')
                                                    ->native(false)
                                                    ->columnSpanFull(),
                                            ])->columns(2),
                                    ]),

                                // ── STEP 2: 9-Stage PRO Pipeline & Dynamic Costs ────────
                                Forms\Components\Wizard\Step::make('9-Stage Pipeline & Fees')
                                    ->icon('heroicon-o-clipboard-document-check')
                                    ->description('Government Stages')
                                    ->schema([
                                        Forms\Components\Section::make('Visa Application Stages & Cost Tracking')
                                            ->description('Track progress status, completion dates, and government/typing fees for each pipeline step.')
                                            ->schema([
                                                // 1. Offer Letter (Hidden for Renewals)
                                                Forms\Components\Fieldset::make('1. OFFER LETTER')
                                                    ->schema([
                                                        Forms\Components\Select::make('offer_letter_status')
                                                            ->label('Status')
                                                            ->options(['Pending' => 'Pending', 'In Progress' => 'In Progress', 'Completed' => 'Completed', 'N/A' => 'N/A'])
                                                            ->default('Pending'),
                                                        Forms\Components\DatePicker::make('offer_letter_date')->label('Date')->native(false),
                                                        Forms\Components\TextInput::make('offer_letter_cost')
                                                            ->label('Fee (AED)')
                                                            ->numeric()
                                                            ->default(0.00)
                                                            ->prefix('AED')
                                                            ->reactive()
                                                            ->afterStateUpdated(fn (callable $get, callable $set) => self::recalculateLiveTotal($get, $set)),
                                                    ])->columns(3)
                                                    ->visible(fn (callable $get) => in_array($get('process_type'), ['new_inside', 'new_outside'])),

                                                // 2. DUBINS
                                                Forms\Components\Fieldset::make('2. DUBINS (Dubai Insurance / Protection)')
                                                    ->schema([
                                                        Forms\Components\Select::make('dubins_status')
                                                            ->label('Status')
                                                            ->options(['Pending' => 'Pending', 'In Progress' => 'In Progress', 'Completed' => 'Completed', 'N/A' => 'N/A'])
                                                            ->default('Pending'),
                                                        Forms\Components\DatePicker::make('dubins_date')->label('Date')->native(false),
                                                        Forms\Components\TextInput::make('dubins_cost')
                                                            ->label('Fee (AED)')
                                                            ->numeric()
                                                            ->default(0.00)
                                                            ->prefix('AED')
                                                            ->reactive()
                                                            ->afterStateUpdated(fn (callable $get, callable $set) => self::recalculateLiveTotal($get, $set)),
                                                    ])->columns(3),

                                                // 3. WP Submission
                                                Forms\Components\Fieldset::make('3. WP SUBMISSION (Work Permit)')
                                                    ->schema([
                                                        Forms\Components\Select::make('wp_submission_status')
                                                            ->label('Status')
                                                            ->options(['Pending' => 'Pending', 'In Progress' => 'In Progress', 'Completed' => 'Completed', 'N/A' => 'N/A'])
                                                            ->default('Pending'),
                                                        Forms\Components\DatePicker::make('wp_submission_date')->label('Date')->native(false),
                                                        Forms\Components\TextInput::make('wp_submission_cost')
                                                            ->label('Fee (AED)')
                                                            ->numeric()
                                                            ->default(0.00)
                                                            ->prefix('AED')
                                                            ->reactive()
                                                            ->afterStateUpdated(fn (callable $get, callable $set) => self::recalculateLiveTotal($get, $set)),
                                                    ])->columns(3),

                                                // 4. EVISA (Hidden for Renewals)
                                                Forms\Components\Fieldset::make('4. EVISA (Entry Permit)')
                                                    ->schema([
                                                        Forms\Components\Select::make('evisa_status')
                                                            ->label('Status')
                                                            ->options(['Pending' => 'Pending', 'In Progress' => 'In Progress', 'Completed' => 'Completed', 'N/A' => 'N/A'])
                                                            ->default('Pending'),
                                                        Forms\Components\DatePicker::make('evisa_date')->label('Date')->native(false),
                                                        Forms\Components\TextInput::make('evisa_cost')
                                                            ->label('Fee (AED)')
                                                            ->numeric()
                                                            ->default(0.00)
                                                            ->prefix('AED')
                                                            ->reactive()
                                                            ->afterStateUpdated(fn (callable $get, callable $set) => self::recalculateLiveTotal($get, $set)),
                                                    ])->columns(3)
                                                    ->visible(fn (callable $get) => in_array($get('process_type'), ['new_inside', 'new_outside'])),

                                                // 5. CHANGE STATUS (Only New Joiner Inside UAE)
                                                Forms\Components\Fieldset::make('5. CHANGE STATUS (Inside UAE Amendment)')
                                                    ->schema([
                                                        Forms\Components\Select::make('change_status_status')
                                                            ->label('Status')
                                                            ->options(['Pending' => 'Pending', 'In Progress' => 'In Progress', 'Completed' => 'Completed', 'N/A' => 'N/A'])
                                                            ->default('Pending'),
                                                        Forms\Components\DatePicker::make('change_status_date')->label('Date')->native(false),
                                                        Forms\Components\TextInput::make('change_status_cost')
                                                            ->label('Fee (AED)')
                                                            ->numeric()
                                                            ->default(0.00)
                                                            ->prefix('AED')
                                                            ->reactive()
                                                            ->afterStateUpdated(fn (callable $get, callable $set) => self::recalculateLiveTotal($get, $set)),
                                                    ])->columns(3)
                                                    ->visible(fn (callable $get) => $get('process_type') === 'new_inside'),

                                                // 6. ILOE
                                                Forms\Components\Fieldset::make('6. ILOE (Loss of Employment Scheme)')
                                                    ->schema([
                                                        Forms\Components\Select::make('iloe_status')
                                                            ->label('Status')
                                                            ->options(['Pending' => 'Pending', 'In Progress' => 'In Progress', 'Completed' => 'Completed', 'N/A' => 'N/A'])
                                                            ->default('Pending'),
                                                        Forms\Components\DatePicker::make('iloe_date')->label('Date')->native(false),
                                                        Forms\Components\TextInput::make('iloe_cost')
                                                            ->label('Fee (AED)')
                                                            ->numeric()
                                                            ->default(0.00)
                                                            ->prefix('AED')
                                                            ->reactive()
                                                            ->afterStateUpdated(fn (callable $get, callable $set) => self::recalculateLiveTotal($get, $set)),
                                                    ])->columns(3),

                                                // 7. CONTRACT SUBMISSION
                                                Forms\Components\Fieldset::make('7. CONTRACT SUBMISSION (Signed MOHRE Contract)')
                                                    ->schema([
                                                        Forms\Components\Select::make('contract_submission_status')
                                                            ->label('Status')
                                                            ->options(['Pending' => 'Pending', 'In Progress' => 'In Progress', 'Completed' => 'Completed', 'N/A' => 'N/A'])
                                                            ->default('Pending'),
                                                        Forms\Components\DatePicker::make('contract_submission_date')->label('Date')->native(false),
                                                        Forms\Components\TextInput::make('contract_submission_cost')
                                                            ->label('Fee (AED)')
                                                            ->numeric()
                                                            ->default(0.00)
                                                            ->prefix('AED')
                                                            ->reactive()
                                                            ->afterStateUpdated(fn (callable $get, callable $set) => self::recalculateLiveTotal($get, $set)),
                                                    ])->columns(3),

                                                // 8. EMIRATES ID & MEDICAL
                                                Forms\Components\Fieldset::make('8. EMIRATES ID (Medical Fitness & Biometrics)')
                                                    ->schema([
                                                        Forms\Components\Select::make('emirates_id_stage_status')
                                                            ->label('Status')
                                                            ->options(['Pending' => 'Pending', 'In Progress' => 'In Progress', 'Completed' => 'Completed', 'N/A' => 'N/A'])
                                                            ->default('Pending'),
                                                        Forms\Components\DatePicker::make('emirates_id_stage_date')->label('Date')->native(false),
                                                        Forms\Components\TextInput::make('emirates_id_stage_cost')
                                                            ->label('Fee (AED)')
                                                            ->numeric()
                                                            ->default(0.00)
                                                            ->prefix('AED')
                                                            ->reactive()
                                                            ->afterStateUpdated(fn (callable $get, callable $set) => self::recalculateLiveTotal($get, $set)),
                                                    ])->columns(3),

                                                // 9. RESIDENCE VISA
                                                Forms\Components\Fieldset::make('9. RESIDENCE VISA (Digital / Stamping)')
                                                    ->schema([
                                                        Forms\Components\Select::make('residence_visa_stage_status')
                                                            ->label('Status')
                                                            ->options(['Pending' => 'Pending', 'In Progress' => 'In Progress', 'Completed' => 'Completed', 'N/A' => 'N/A'])
                                                            ->default('Pending'),
                                                        Forms\Components\DatePicker::make('residence_visa_stage_date')->label('Date')->native(false),
                                                        Forms\Components\TextInput::make('residence_visa_stage_cost')
                                                            ->label('Fee (AED)')
                                                            ->numeric()
                                                            ->default(0.00)
                                                            ->prefix('AED')
                                                            ->reactive()
                                                            ->afterStateUpdated(fn (callable $get, callable $set) => self::recalculateLiveTotal($get, $set)),
                                                    ])->columns(3),
                                            ]),
                                    ]),

                                // ── STEP 3: Grace Period & Overstay Fines ───────────────
                                Forms\Components\Wizard\Step::make('Grace Period & Fines')
                                    ->icon('heroicon-o-clock')
                                    ->description('UAE Immigration Rules')
                                    ->schema([
                                        Forms\Components\Section::make('UAE Grace Period & Live Overstay Fines Calculator')
                                            ->description('Overstay fines start accruing automatically after the grace period ends.')
                                            ->schema([
                                                Forms\Components\TextInput::make('grace_period_days')
                                                    ->label('Grace Period (Days)')
                                                    ->numeric()
                                                    ->default(30)
                                                    ->helperText('Standard UAE grace period: 30 or 60 days.')
                                                    ->reactive()
                                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                        $visaExp = $get('residence_visa_expiry');
                                                        if ($visaExp) {
                                                            $set('fines_start_date', Carbon::parse($visaExp)->addDays((int) $state)->format('Y-m-d'));
                                                        }
                                                    }),

                                                Forms\Components\DatePicker::make('fines_start_date')
                                                    ->label('Fines Will Start Date')
                                                    ->native(false)
                                                    ->helperText('Overstay penalties start accumulating from this date.')
                                                    ->reactive(),

                                                Forms\Components\Toggle::make('manual_fine_override')
                                                    ->label('Manual Fine Override')
                                                    ->helperText('Enable to manually override penalties (e.g. if immigration granted a fine discount).')
                                                    ->reactive()
                                                    ->columnSpanFull(),

                                                Forms\Components\TextInput::make('fine_pr_50')
                                                    ->label('FINE (PR-50) - Residency Overstay')
                                                    ->prefix('AED')
                                                    ->numeric()
                                                    ->default(0.00)
                                                    ->helperText('Standard GDRFA overstay fine: AED 50 per day.')
                                                    ->disabled(fn (callable $get) => ! $get('manual_fine_override')),

                                                Forms\Components\TextInput::make('fine_eid_20')
                                                    ->label('FINE (EID-20) - Emirates ID Late Renewal')
                                                    ->prefix('AED')
                                                    ->numeric()
                                                    ->default(0.00)
                                                    ->helperText('Standard ICP EID delay fine: AED 20 per day (Max AED 1,000).')
                                                    ->disabled(fn (callable $get) => ! $get('manual_fine_override')),

                                                Forms\Components\Placeholder::make('live_fines_preview')
                                                    ->label('Live Fines Preview')
                                                    ->content(function (callable $get) {
                                                        $startDate = $get('fines_start_date');
                                                        if (! $startDate) {
                                                            return 'Set "Fines Will Start Date" to calculate live overstay penalties.';
                                                        }

                                                        $start = Carbon::parse($startDate);
                                                        $today = Carbon::today();

                                                        if ($today->lte($start)) {
                                                            $days = (int) $today->diffInDays($start);
                                                            return "✅ Safe: Within grace period ({$days} days remaining). No fines accruing.";
                                                        }

                                                        $overdue = (int) $start->diffInDays($today);
                                                        $pr50 = $overdue * 50;
                                                        $eid20 = min(1000, $overdue * 20);
                                                        $total = $pr50 + $eid20;

                                                        return "🔴 OVERDUE: {$overdue} days | PR-50 Fine: AED " . number_format($pr50, 2) . " | EID-20 Fine: AED " . number_format($eid20, 2) . " | Total Fines: AED " . number_format($total, 2);
                                                    })->columnSpanFull(),
                                            ])->columns(2),
                                    ]),

                                // ── STEP 4: Attachments & Notes ─────────────────────────
                                Forms\Components\Wizard\Step::make('Attachments & Remarks')
                                    ->icon('heroicon-o-paper-clip')
                                    ->description('Scanned Docs & Notes')
                                    ->schema([
                                        Forms\Components\Section::make('Scanned Documents & Notes')
                                            ->schema([
                                                Forms\Components\FileUpload::make('attachments')
                                                    ->label('Upload Documents (Passports, Visas, EID, Work Permits)')
                                                    ->multiple()
                                                    ->directory('pro-documents')
                                                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                                                    ->maxSize(10240)
                                                    ->downloadable()
                                                    ->openable()
                                                    ->columnSpanFull(),

                                                Forms\Components\Textarea::make('remarks')
                                                    ->label('PRO Operational Remarks')
                                                    ->rows(4)
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                        ])
                        ->skippable()
                        ->persistStepInQueryString(),
                    ])
                    ->columnSpan(['lg' => 2]),

                // ── RIGHT SIDEBAR: Sponsoring Entity & Real-Time Financials ──
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Sponsoring & Workflow')
                            ->description('Branch allocation & visa classification')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Forms\Components\Select::make('ledger_branch_id')
                                    ->label('Sponsoring Branch')
                                    ->relationship('ledgerBranch', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->helperText('Select from active Ledger Branches.')
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $branch = \App\Models\LedgerBranch::find($state);
                                            if ($branch && $branch->license_expiry) {
                                                $set('license_expiry', $branch->license_expiry->format('Y-m-d'));
                                            }
                                        }
                                    }),

                                Forms\Components\DatePicker::make('license_expiry')
                                    ->label('Branch License Expiry')
                                    ->helperText('Auto-filled from Ledger Branch.')
                                    ->native(false),

                                Forms\Components\Select::make('process_type')
                                    ->label('Process Type')
                                    ->options([
                                        'new_inside'   => 'New Joiner (Inside UAE)',
                                        'new_outside'  => 'New Joiner (Outside UAE)',
                                        'renewal'      => 'Visa Renewal',
                                        'cancellation' => 'Visa Cancellation',
                                    ])
                                    ->default('new_inside')
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if ($categoryId = $get('pro_category_id')) {
                                            self::applyCategoryFeesFromDatabase($categoryId, $get, $set);
                                        } else {
                                            if ($state === 'renewal') {
                                                $set('evisa_cost', 0.00);
                                                $set('change_status_cost', 0.00);
                                                $set('offer_letter_cost', 0.00);
                                            } elseif ($state === 'new_outside') {
                                                $set('change_status_cost', 0.00);
                                            }
                                            self::recalculateLiveTotal($get, $set);
                                        }
                                    }),

                                Forms\Components\Select::make('pro_category_id')
                                    ->label('Labour Category')
                                    ->relationship('proCategory', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->helperText('Determines government fee baseline presets.')
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        self::applyCategoryFeesFromDatabase($state, $get, $set);
                                    })
                                    ->hintActions([
                                        Forms\Components\Actions\Action::make('reloadPreset')
                                            ->label('Reload')
                                            ->icon('heroicon-m-arrow-path')
                                            ->tooltip('Reload default fees for this category')
                                            ->action(fn (callable $get, callable $set) => self::applyCategoryFeesFromDatabase($get('pro_category_id'), $get, $set)),
                                        Forms\Components\Actions\Action::make('manageCategories')
                                            ->label('Settings')
                                            ->icon('heroicon-m-cog-6-tooth')
                                            ->tooltip('Manage category fees in Settings')
                                            ->url(url('/vouchers/settings/pro-categories'))
                                            ->openUrlInNewTab(),
                                    ])
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')->required()->placeholder('e.g. Category 2, Free Zone'),
                                        Forms\Components\TextInput::make('code')->placeholder('e.g. CAT2'),
                                        Forms\Components\TextInput::make('wp_submission_cost')->label('WP New Fee (AED)')->numeric()->default(1200.00),
                                        Forms\Components\TextInput::make('renewal_wp_cost')->label('WP Renewal Fee (AED)')->numeric()->default(1200.00),
                                    ]),

                                Forms\Components\Select::make('status')
                                    ->label('Application Status')
                                    ->options([
                                        'draft'       => 'Draft',
                                        'in_progress' => 'In Progress',
                                        'completed'   => 'Completed',
                                        'on_hold'     => 'On Hold',
                                        'cancelled'   => 'Cancelled',
                                    ])
                                    ->default('in_progress')
                                    ->required(),
                            ]),

                        Forms\Components\Section::make('Total & Financial Overview')
                            ->description('Live accumulated processing fee')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Forms\Components\TextInput::make('total_cost')
                                    ->label('TOTAL PROCESSING FEE')
                                    ->numeric()
                                    ->default(0.00)
                                    ->prefix('AED')
                                    ->readOnly()
                                    ->extraInputAttributes([
                                        'style' => 'font-weight: 700; font-size: 1.35rem; color: #16a34a; background-color: rgba(22, 163, 74, 0.05); border-color: rgba(22, 163, 74, 0.2);',
                                    ])
                                    ->helperText('Automatically calculated in real-time from active pipeline steps.'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(['lg' => 3]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                // 1. Employee Name & Designation
                Tables\Columns\TextColumn::make('employee_name')
                    ->label('EMPLOYEE')
                    ->searchable(['employee_name', 'nationality', 'job_description'])
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->description(function (ProRecord $record): string {
                        $parts = [];
                        if ($record->nationality) {
                            $parts[] = $record->nationality;
                        }
                        if ($record->job_description) {
                            $parts[] = $record->job_description;
                        }
                        return implode(' • ', $parts) ?: 'No Job Title';
                    }),

                // 2. Sponsoring Branch
                Tables\Columns\TextColumn::make('ledgerBranch.name')
                    ->label('SPONSORING BRANCH')
                    ->badge()
                    ->placeholder('—')
                    ->color('gray')
                    ->sortable()
                    ->searchable(),

                // 3. Process Type & Category Tier
                Tables\Columns\TextColumn::make('process_type')
                    ->label('PROCESS & TIER')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'new_inside'   => 'New Joiner (Inside)',
                        'new_outside'  => 'New Joiner (Outside)',
                        'renewal'      => 'Renewal',
                        'cancellation' => 'Cancellation',
                        default        => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'new_inside'   => 'primary',
                        'new_outside'  => 'info',
                        'renewal'      => 'success',
                        'cancellation' => 'danger',
                        default        => 'gray',
                    })
                    ->description(fn (ProRecord $record): ?string => $record->proCategory?->name)
                    ->sortable(),

                // 4. Status
                Tables\Columns\TextColumn::make('status')
                    ->label('STATUS')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft'       => 'Draft',
                        'in_progress' => 'In Progress',
                        'completed'   => 'Completed',
                        'on_hold'     => 'On Hold',
                        'cancelled'   => 'Cancelled',
                        default        => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'completed'   => 'success',
                        'in_progress' => 'warning',
                        'on_hold'     => 'danger',
                        'cancelled'   => 'gray',
                        default        => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'completed'   => 'heroicon-m-check-circle',
                        'in_progress' => 'heroicon-m-arrow-path',
                        'on_hold'     => 'heroicon-m-pause-circle',
                        'cancelled'   => 'heroicon-m-x-circle',
                        default       => 'heroicon-m-document',
                    })
                    ->sortable(),

                // 5. Intelligent Consolidated Visa, Grace Period & Fines Status
                Tables\Columns\TextColumn::make('compliance_badge')
                    ->label('VISA & COMPLIANCE')
                    ->badge()
                    ->color(fn (ProRecord $record): string => $record->compliance_color)
                    ->description(fn (ProRecord $record): ?string => $record->compliance_description),

                // 6. Total Processing Cost
                Tables\Columns\TextColumn::make('total_cost')
                    ->label('TOTAL')
                    ->money('AED')
                    ->sortable()
                    ->weight(FontWeight::Bold),

                // ── Toggleable Detail Columns (Off by default for easy viewing, toggle on when needed) ──
                Tables\Columns\TextColumn::make('proCategory.name')
                    ->label('CATEGORY (STANDALONE)')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('passport_expiry')
                    ->label('PASSPORT EXPIRY')
                    ->date('d M Y')
                    ->sortable()
                    ->description(fn (ProRecord $record): ?string => $record->passport_status['label'] ?? null)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('residence_visa_expiry')
                    ->label('RESIDENCE VISA EXPIRY')
                    ->date('d M Y')
                    ->sortable()
                    ->description(fn (ProRecord $record): ?string => $record->visa_status['label'] ?? null)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('grace_period_days')
                    ->label('GRACE PERIOD')
                    ->suffix(' days')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fines_start_date')
                    ->label('FINES WILL START DATE')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fine_pr_50')
                    ->label('FINE (PR-50)')
                    ->money('AED')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fine_eid_20')
                    ->label('FINE (EID-20)')
                    ->money('AED')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('license_expiry')
                    ->label('LICENSE EXPIRY')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('labour_contract_expiry')
                    ->label('LABOUR CONTRACT EXPIRY')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('labour_card_number')
                    ->label('LABOUR CARD #')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('labour_card_expiry')
                    ->label('LABOUR CARD EXPIRY')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('emirates_id_number')
                    ->label('EMIRATES ID #')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('emirates_id_expiry')
                    ->label('EMIRATES ID EXPIRY')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('passport_number')
                    ->label('PASSPORT #')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('unified_id')
                    ->label('UNIFIED ID #')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('person_code')
                    ->label('PERSON CODE #')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // 9 Stage Pipeline Costs (Toggleable)
                Tables\Columns\TextColumn::make('offer_letter_cost')
                    ->label('OFFER LETTER')
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('dubins_cost')
                    ->label('DUBINS')
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('wp_submission_cost')
                    ->label('WP SUBMISSION')
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('evisa_cost')
                    ->label('EVISA')
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('change_status_cost')
                    ->label('CHANGE STATUS')
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('iloe_cost')
                    ->label('ILOE')
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('contract_submission_cost')
                    ->label('CONTRACT SUBMISSION')
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('emirates_id_stage_cost')
                    ->label('EMIRATES ID')
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('residence_visa_stage_cost')
                    ->label('RESIDENCE VISA')
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ledger_branch_id')
                    ->label('Sponsoring Branch')
                    ->relationship('ledgerBranch', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('pro_category_id')
                    ->label('Category')
                    ->relationship('proCategory', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('process_type')
                    ->label('Process Type')
                    ->options([
                        'new_inside'   => 'New Joiner (Inside)',
                        'new_outside'  => 'New Joiner (Outside)',
                        'renewal'      => 'Visa Renewal',
                        'cancellation' => 'Cancellation',
                    ]),

                Tables\Filters\Filter::make('overstay_fines')
                    ->label('🔴 Active Overstay Fines')
                    ->query(fn ($query) => $query->whereNotNull('fines_start_date')->where('fines_start_date', '<', Carbon::today())),

                Tables\Filters\Filter::make('passport_expiring_6mo')
                    ->label('⚠️ Passports Expiring (< 6 Mo)')
                    ->query(fn ($query) => $query->whereNotNull('passport_expiry')->whereBetween('passport_expiry', [Carbon::today(), Carbon::today()->addMonths(6)])),

                Tables\Filters\Filter::make('visa_expiring_30d')
                    ->label('⚠️ Visas Expiring (< 30 Days)')
                    ->query(fn ($query) => $query->whereNotNull('residence_visa_expiry')->whereBetween('residence_visa_expiry', [Carbon::today(), Carbon::today()->addDays(30)])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export to Excel')
                    ->visible(fn () => auth()->user()?->hasAnyRole(['Admin', 'Super Admin']) || (bool) auth()->user()?->can('pro_record.export'))
                    ->exports([
                        ExcelExport::make('PRO_Records_Report')
                            ->withFilename('PRO_Tracking_Sheet_' . date('Y-m-d'))
                            ->modifyQueryUsing(fn ($query) => $query->with(['ledgerBranch', 'proCategory', 'user']))
                            ->withColumns([
                                Column::make('ledgerBranch.name')->heading('SPONSORING BRANCH'),
                                Column::make('license_expiry')->heading('LICENSE EXPIRY'),
                                Column::make('employee_name')->heading('EMPLOYEE NAME'),
                                Column::make('process_type')->heading('PROCESS TYPE')->formatStateUsing(fn ($state) => match ($state) {
                                    'new_inside'   => 'New Joiner (Inside UAE)',
                                    'new_outside'  => 'New Joiner (Outside UAE)',
                                    'renewal'      => 'Visa Renewal',
                                    'cancellation' => 'Visa Cancellation',
                                    default        => ucfirst((string) $state),
                                }),
                                Column::make('proCategory.name')->heading('CATEGORY'),
                                Column::make('status')->heading('STATUS')->formatStateUsing(fn ($state) => match ($state) {
                                    'completed'   => 'Completed',
                                    'in_progress' => 'In Progress',
                                    'on_hold'     => 'On Hold',
                                    'draft'       => 'Draft',
                                    'cancelled'   => 'Cancelled',
                                    default        => ucfirst((string) $state),
                                }),
                                Column::make('labour_contract_expiry')->heading('LABOUR CONTRACT EXPIRY'),
                                Column::make('nationality')->heading('NATIONALITY'),
                                Column::make('job_description')->heading('JOB DESCRIPTION'),
                                Column::make('labour_card_number')->heading('LABOUR CARD #'),
                                Column::make('labour_card_expiry')->heading('LABOUR CARD EXPIRY'),
                                Column::make('emirates_id_expiry')->heading('EMIRATES ID EXPIRY'),
                                Column::make('grace_period_days')->heading('GRACE PERIOD'),
                                Column::make('fine_eid_20')->heading('FINE (EID-20)'),
                                Column::make('fine_pr_50')->heading('FINE (PR-50)'),
                                Column::make('offer_letter_cost')->heading('OFFER LETTER'),
                                Column::make('dubins_cost')->heading('DUBINS'),
                                Column::make('wp_submission_cost')->heading('WP SUBMISSION'),
                                Column::make('evisa_cost')->heading('EVISA'),
                                Column::make('change_status_cost')->heading('CHANGE STATUS'),
                                Column::make('iloe_cost')->heading('ILOE'),
                                Column::make('contract_submission_cost')->heading('CONTRACT SUBMISSION'),
                                Column::make('emirates_id_stage_cost')->heading('EMIRATES ID'),
                                Column::make('residence_visa_stage_cost')->heading('RESIDENCE VISA'),
                                Column::make('total_cost')->heading('TOTAL'),
                                Column::make('fines_start_date')->heading('FINES WILL START DATE'),
                                Column::make('unified_id')->heading('UNIFIED ID #'),
                                Column::make('emirates_id_number')->heading('EMIRATES ID #'),
                                Column::make('passport_number')->heading('PASSPORT #'),
                                Column::make('passport_expiry')->heading('PASSPORT EXPIRY'),
                                Column::make('person_code')->heading('PERSON CODE #'),
                            ]),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Sponsoring Branch & License')
                    ->schema([
                        Infolists\Components\TextEntry::make('ledgerBranch.name')->label('SPONSORING BRANCH')->badge()->placeholder('—')->color('gray'),
                        Infolists\Components\TextEntry::make('license_expiry')->label('LICENSE EXPIRY')->date('d M Y')->placeholder('—'),
                        Infolists\Components\TextEntry::make('process_type')
                            ->label('PROCESS TYPE')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'new_inside'   => 'New Joiner (Inside UAE)',
                                'new_outside'  => 'New Joiner (Outside UAE)',
                                'renewal'      => 'Visa Renewal',
                                'cancellation' => 'Visa Cancellation',
                                default        => ucfirst(str_replace('_', ' ', (string) $state)),
                            })
                            ->color(fn ($state) => match ($state) {
                                'new_inside'   => 'primary',
                                'new_outside'  => 'info',
                                'renewal'      => 'success',
                                'cancellation' => 'danger',
                                default        => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('proCategory.name')->label('CATEGORY')->badge()->placeholder('—')->color('info'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('APPLICATION STATUS')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'draft'       => 'Draft',
                                'in_progress' => 'In Progress',
                                'completed'   => 'Completed',
                                'on_hold'     => 'On Hold',
                                'cancelled'   => 'Cancelled',
                                default        => ucfirst(str_replace('_', ' ', (string) $state)),
                            })
                            ->color(fn ($state) => match ($state) {
                                'completed'   => 'success',
                                'in_progress' => 'warning',
                                'on_hold'     => 'danger',
                                'cancelled'   => 'gray',
                                default        => 'gray',
                            }),
                    ])->columns(5),

                Infolists\Components\Section::make('Employee Identity & Government Identifiers')
                    ->schema([
                        Infolists\Components\TextEntry::make('employee_name')->label('EMPLOYEE NAME')->weight(FontWeight::Bold),
                        Infolists\Components\TextEntry::make('nationality')->label('NATIONALITY')->placeholder('—'),
                        Infolists\Components\TextEntry::make('job_description')->label('JOB DESCRIPTION')->placeholder('—'),
                        Infolists\Components\TextEntry::make('person_code')->label('PERSON CODE #')->placeholder('—'),
                        Infolists\Components\TextEntry::make('unified_id')->label('UNIFIED ID #')->placeholder('—'),
                        Infolists\Components\TextEntry::make('passport_number')->label('PASSPORT #')->placeholder('—'),
                        Infolists\Components\TextEntry::make('passport_expiry')->label('PASSPORT EXPIRY')->date('d M Y')->placeholder('—'),
                        Infolists\Components\TextEntry::make('emirates_id_number')->label('EMIRATES ID #')->placeholder('—'),
                        Infolists\Components\TextEntry::make('emirates_id_expiry')->label('EMIRATES ID EXPIRY')->date('d M Y')->placeholder('—'),
                        Infolists\Components\TextEntry::make('labour_card_number')->label('LABOUR CARD #')->placeholder('—'),
                        Infolists\Components\TextEntry::make('labour_card_expiry')->label('LABOUR CARD EXPIRY')->date('d M Y')->placeholder('—'),
                        Infolists\Components\TextEntry::make('labour_contract_expiry')->label('LABOUR CONTRACT EXPIRY')->date('d M Y')->placeholder('—'),
                        Infolists\Components\TextEntry::make('residence_visa_number')->label('RESIDENCE VISA #')->placeholder('—'),
                        Infolists\Components\TextEntry::make('residence_visa_expiry')->label('RESIDENCE VISA EXPIRY')->date('d M Y')->placeholder('—'),
                    ])->columns(4),

                Infolists\Components\Section::make('Grace Period & Overstay Penalties')
                    ->schema([
                        Infolists\Components\TextEntry::make('grace_period_days')->label('GRACE PERIOD')->suffix(' days'),
                        Infolists\Components\TextEntry::make('fines_start_date')->label('FINES WILL START DATE')->date('d M Y')->placeholder('—'),
                        Infolists\Components\TextEntry::make('fine_eid_20')->label('FINE (EID-20)')->money('AED'),
                        Infolists\Components\TextEntry::make('fine_pr_50')->label('FINE (PR-50)')->money('AED'),
                        Infolists\Components\TextEntry::make('compliance_badge')
                            ->label('COMPLIANCE / OVERSTAY STATUS')
                            ->badge()
                            ->color(fn (ProRecord $record) => $record->compliance_color),
                    ])->columns(5),

                Infolists\Components\Section::make('9-Stage Pipeline Fees & Totals')
                    ->schema([
                        Infolists\Components\TextEntry::make('offer_letter_cost')->label('OFFER LETTER')->money('AED'),
                        Infolists\Components\TextEntry::make('dubins_cost')->label('DUBINS')->money('AED'),
                        Infolists\Components\TextEntry::make('wp_submission_cost')->label('WP SUBMISSION')->money('AED'),
                        Infolists\Components\TextEntry::make('evisa_cost')->label('EVISA')->money('AED'),
                        Infolists\Components\TextEntry::make('change_status_cost')->label('CHANGE STATUS')->money('AED'),
                        Infolists\Components\TextEntry::make('iloe_cost')->label('ILOE')->money('AED'),
                        Infolists\Components\TextEntry::make('contract_submission_cost')->label('CONTRACT SUBMISSION')->money('AED'),
                        Infolists\Components\TextEntry::make('emirates_id_stage_cost')->label('EMIRATES ID')->money('AED'),
                        Infolists\Components\TextEntry::make('residence_visa_stage_cost')->label('RESIDENCE VISA')->money('AED'),
                        Infolists\Components\TextEntry::make('total_cost')
                            ->label('TOTAL PROCESSING COST')
                            ->money('AED')
                            ->weight(FontWeight::Bold)
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->color('success'),
                    ])->columns(5),

                Infolists\Components\Section::make('Remarks & Documents')
                    ->schema([
                        Infolists\Components\TextEntry::make('remarks')->label('REMARKS')->placeholder('No remarks recorded.')->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['ledgerBranch', 'proCategory', 'user']);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProRecords::route('/'),
            'create' => Pages\CreateProRecord::route('/create'),
            'view'   => Pages\ViewProRecord::route('/{record}'),
            'edit'   => Pages\EditProRecord::route('/{record}/edit'),
        ];
    }

    // ── Helper Logic: Dynamic Recalculation & Category Presets ──────────────

    public static function recalculateLiveTotal(callable $get, callable $set): void
    {
        $processType = $get('process_type');

        $costs = [
            (float) ($get('dubins_cost') ?: 0),
            (float) ($get('wp_submission_cost') ?: 0),
            (float) ($get('iloe_cost') ?: 0),
            (float) ($get('contract_submission_cost') ?: 0),
            (float) ($get('emirates_id_stage_cost') ?: 0),
            (float) ($get('residence_visa_stage_cost') ?: 0),
        ];

        if (in_array($processType, ['new_inside', 'new_outside'])) {
            $costs[] = (float) ($get('offer_letter_cost') ?: 0);
            $costs[] = (float) ($get('evisa_cost') ?: 0);
        }

        if ($processType === 'new_inside') {
            $costs[] = (float) ($get('change_status_cost') ?: 0);
        }

        $set('total_cost', number_format(array_sum($costs), 2, '.', ''));
    }

    public static function applyCategoryFeesFromDatabase(?int $categoryId, callable $get, callable $set): void
    {
        if (! $categoryId) {
            return;
        }

        $category = \App\Models\ProCategory::find($categoryId);
        if (! $category) {
            return;
        }

        $processType = $get('process_type') ?: 'new_inside';

        if ($processType === 'renewal') {
            $set('dubins_cost', (float) $category->renewal_dubins_cost);
            $set('wp_submission_cost', (float) $category->renewal_wp_cost);
            $set('iloe_cost', (float) $category->renewal_iloe_cost);
            $set('contract_submission_cost', (float) $category->renewal_contract_cost);
            $set('emirates_id_stage_cost', (float) $category->renewal_emirates_id_cost);
            $set('residence_visa_stage_cost', (float) $category->renewal_residence_visa_cost);
            $set('offer_letter_cost', 0.00);
            $set('evisa_cost', 0.00);
            $set('change_status_cost', 0.00);
        } else {
            $set('offer_letter_cost', (float) $category->offer_letter_cost);
            $set('dubins_cost', (float) $category->dubins_cost);
            $set('wp_submission_cost', (float) $category->wp_submission_cost);
            $set('evisa_cost', (float) $category->evisa_cost);
            $set('change_status_cost', $processType === 'new_inside' ? (float) $category->change_status_cost : 0.00);
            $set('iloe_cost', (float) $category->iloe_cost);
            $set('contract_submission_cost', (float) $category->contract_submission_cost);
            $set('emirates_id_stage_cost', (float) $category->emirates_id_cost);
            $set('residence_visa_stage_cost', (float) $category->residence_visa_cost);
        }

        self::recalculateLiveTotal($get, $set);

        \Filament\Notifications\Notification::make()
            ->title('Dynamic Fees Applied')
            ->body("Loaded fees from {$category->name} for {$processType}.")
            ->success()
            ->send();
    }
}
