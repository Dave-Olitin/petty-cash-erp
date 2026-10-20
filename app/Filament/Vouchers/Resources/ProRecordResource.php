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
                                            ->url(fn () => \App\Filament\Vouchers\Resources\ProCategoryResource::getUrl('index'))
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
            ->defaultSort('action_priority', 'asc')
            ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query) {
                if (! request()->has('tableSortColumn')) {
                    $query->orderByRaw("COALESCE(fines_start_date, residence_visa_expiry, '2099-12-31') ASC");
                }
            })
            ->recordUrl(fn (ProRecord $record): string => Pages\ViewProRecord::getUrl([$record]))
            ->columns([
                // 1. Employee Name (Clean single line)
                Tables\Columns\TextColumn::make('employee_name')
                    ->label('Employee')
                    ->searchable(['employee_name', 'nationality', 'job_description'])
                    ->sortable()
                    ->weight(FontWeight::SemiBold),

                // 2. Sponsoring Branch (Clean single line)
                Tables\Columns\TextColumn::make('ledgerBranch.name')
                    ->label('Branch')
                    ->placeholder('—')
                    ->weight(FontWeight::Medium)
                    ->color('gray')
                    ->limit(20)
                    ->tooltip(fn (ProRecord $record): ?string => $record->ledgerBranch?->name)
                    ->sortable()
                    ->searchable(),

                // 3. Category Tier Column (Unique name prevents toggle override)
                Tables\Columns\TextColumn::make('category_tier')
                    ->label('Category Tier')
                    ->state(fn (ProRecord $record): ?string => $record->proCategory?->name)
                    ->placeholder('—')
                    ->color('gray')
                    ->weight(FontWeight::Medium)
                    ->limit(24)
                    ->tooltip(fn (ProRecord $record): ?string => $record->proCategory?->name)
                    ->sortable(query: fn ($query, $direction) => $query->orderBy(
                        \App\Models\ProCategory::select('name')->whereColumn('pro_categories.id', 'pro_records.pro_category_id'),
                        $direction
                    )),

                // 4. Process Type (Clean plain text to avoid rainbow colored badges)
                Tables\Columns\TextColumn::make('process_type')
                    ->label('Process')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'new_inside'   => 'New (Inside)',
                        'new_outside'  => 'New (Outside)',
                        'renewal'      => 'Renewal',
                        'cancellation' => 'Cancellation',
                        default        => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->color('gray')
                    ->weight(FontWeight::Medium)
                    ->sortable(),

                // 5. Visual Milestone Gantt Chart Column
                Tables\Columns\ViewColumn::make('pipeline_gantt')
                    ->label('Progress')
                    ->view('filament.tables.columns.pipeline-gantt'),

                // 6. Status (Subtle clean badge)
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
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
                    ->sortable(),

                // 7. Intelligent Consolidated Visa & Fines Status (High-signal color coding)
                Tables\Columns\TextColumn::make('compliance_badge')
                    ->label('Compliance')
                    ->badge()
                    ->color(fn (ProRecord $record): string => $record->compliance_color),

                // 8. Total Processing Cost (Right aligned, single line)
                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total Fee')
                    ->money('AED')
                    ->alignEnd()
                    ->sortable()
                    ->weight(FontWeight::SemiBold),

                // ── Toggleable Detail Columns (Off by default, clean title casing) ──
                Tables\Columns\TextColumn::make('passport_expiry')
                    ->label('Passport Expiry')
                    ->date('d M Y')
                    ->sortable()
                    ->description(fn (ProRecord $record): ?string => $record->passport_status['label'] ?? null)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('residence_visa_expiry')
                    ->label('Residence Visa Expiry')
                    ->date('d M Y')
                    ->sortable()
                    ->description(fn (ProRecord $record): ?string => $record->visa_status['label'] ?? null)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('grace_period_days')
                    ->label('Grace Period')
                    ->suffix(' days')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fines_start_date')
                    ->label('Fines Start Date')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fine_pr_50')
                    ->label('Fine (PR-50)')
                    ->money('AED')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fine_eid_20')
                    ->label('Fine (EID-20)')
                    ->money('AED')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('license_expiry')
                    ->label('License Expiry')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('labour_contract_expiry')
                    ->label('Labour Contract Expiry')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('labour_card_number')
                    ->label('Labour Card #')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('labour_card_expiry')
                    ->label('Labour Card Expiry')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('emirates_id_number')
                    ->label('Emirates ID #')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('emirates_id_expiry')
                    ->label('Emirates ID Expiry')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('passport_number')
                    ->label('Passport #')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('unified_id')
                    ->label('Unified ID #')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('person_code')
                    ->label('Person Code #')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // 9 Stage Pipeline Costs (Toggleable)
                Tables\Columns\TextColumn::make('offer_letter_cost')
                    ->label('Offer Letter')
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('dubins_cost')
                    ->label('Dubins Insurance')
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('wp_submission_cost')
                    ->label('Work Permit')
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('evisa_cost')
                    ->label('eVisa')
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('change_status_cost')
                    ->label('Change Status')
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('iloe_cost')
                    ->label('ILOE Insurance')
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('contract_submission_cost')
                    ->label('Contract Submission')
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('emirates_id_stage_cost')
                    ->label('Emirates ID Stage')
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('residence_visa_stage_cost')
                    ->label('Residence Visa Stage')
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
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
                ->icon('heroicon-m-ellipsis-horizontal')
                ->tooltip('Actions'),
            ])
            ->headerActions([
                Tables\Actions\ImportAction::make()
                    ->label('Import Records')
                    ->importer(\App\Filament\Imports\ProRecordImporter::class)
                    ->color('gray')
                    ->icon('heroicon-m-arrow-up-tray'),

                ExportAction::make()
                    ->label('Export to Excel')
                    ->exports([
                        ExcelExport::make('PRO_Records_Report')
                            ->withFilename('PRO_Tracking_Sheet_' . date('Y-m-d'))
                            ->modifyQueryUsing(fn ($query) => $query->with(['ledgerBranch', 'proCategory', 'user']))
                            ->withColumns([
                                // ── Employee & Company Overview ─────────────────────────
                                Column::make('employee_name')->heading('EMPLOYEE NAME'),
                                Column::make('nationality')->heading('NATIONALITY'),
                                Column::make('job_description')->heading('DESIGNATION / JOB TITLE'),
                                Column::make('ledgerBranch.name')->heading('SPONSORING BRANCH'),
                                Column::make('proCategory.name')->heading('CATEGORY TIER'),
                                Column::make('process_type')
                                    ->heading('PROCESS TYPE')
                                    ->formatStateUsing(fn ($state) => match ($state) {
                                        'new_inside'   => 'New Joiner (Inside UAE)',
                                        'new_outside'  => 'New Joiner (Outside UAE)',
                                        'renewal'      => 'Visa Renewal',
                                        'cancellation' => 'Visa Cancellation',
                                        default        => ucfirst((string) $state),
                                    }),
                                Column::make('status')
                                    ->heading('APPLICATION STATUS')
                                    ->formatStateUsing(fn ($state) => match ($state) {
                                        'completed'   => 'Completed',
                                        'in_progress' => 'In Progress',
                                        'on_hold'     => 'On Hold',
                                        'draft'       => 'Draft',
                                        'cancelled'   => 'Cancelled',
                                        default        => ucfirst((string) $state),
                                    }),

                                // ── Compliance, Grace & Overstay Penalties ───────────────
                                Column::make('compliance_badge')->heading('COMPLIANCE & FINES STATUS'),
                                Column::make('calculated_total_fines')
                                    ->heading('ACCRUED FINES (AED)')
                                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2)),
                                Column::make('days_overdue')->heading('OVERSTAY DAYS'),
                                Column::make('fines_start_date')
                                    ->heading('FINES START DATE')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : ''),
                                Column::make('grace_period_days')->heading('GRACE PERIOD (DAYS)'),
                                Column::make('fine_pr_50')->heading('DAILY FINE PR-50 (AED)'),
                                Column::make('fine_eid_20')->heading('DAILY FINE EID-20 (AED)'),

                                // ── Government Identifiers & Expirations ────────────────
                                Column::make('passport_number')->heading('PASSPORT #'),
                                Column::make('passport_expiry')
                                    ->heading('PASSPORT EXPIRY')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : ''),
                                Column::make('residence_visa_number')->heading('RESIDENCE VISA #'),
                                Column::make('residence_visa_expiry')
                                    ->heading('RESIDENCE VISA EXPIRY')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : ''),
                                Column::make('unified_id')->heading('UNIFIED ID #'),
                                Column::make('person_code')->heading('PERSON CODE #'),
                                Column::make('emirates_id_number')->heading('EMIRATES ID #'),
                                Column::make('emirates_id_expiry')
                                    ->heading('EMIRATES ID EXPIRY')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : ''),
                                Column::make('labour_card_number')->heading('LABOUR CARD #'),
                                Column::make('labour_card_expiry')
                                    ->heading('LABOUR CARD EXPIRY')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : ''),
                                Column::make('labour_contract_expiry')
                                    ->heading('LABOUR CONTRACT EXPIRY')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : ''),
                                Column::make('license_expiry')
                                    ->heading('BRANCH LICENSE EXPIRY')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : ''),

                                // ── Pipeline Progress & 9 Stages ────────────────────────
                                Column::make('pipeline_stages')
                                    ->heading('PIPELINE PROGRESS (%)')
                                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state['percentage'] ?? 0) . '%' : '0%'),

                                Column::make('offer_letter_status')->heading('OFFER LETTER STATUS'),
                                Column::make('offer_letter_cost')->heading('OFFER LETTER COST (AED)'),
                                Column::make('offer_letter_date')
                                    ->heading('OFFER LETTER DATE')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : ''),

                                Column::make('dubins_status')->heading('DUBINS STATUS'),
                                Column::make('dubins_cost')->heading('DUBINS COST (AED)'),
                                Column::make('dubins_date')
                                    ->heading('DUBINS DATE')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : ''),

                                Column::make('wp_submission_status')->heading('WORK PERMIT STATUS'),
                                Column::make('wp_submission_cost')->heading('WORK PERMIT COST (AED)'),
                                Column::make('wp_submission_date')
                                    ->heading('WORK PERMIT DATE')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : ''),

                                Column::make('evisa_status')->heading('EVISA STATUS'),
                                Column::make('evisa_cost')->heading('EVISA COST (AED)'),
                                Column::make('evisa_date')
                                    ->heading('EVISA DATE')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : ''),

                                Column::make('change_status_status')->heading('CHANGE STATUS STATUS'),
                                Column::make('change_status_cost')->heading('CHANGE STATUS COST (AED)'),
                                Column::make('change_status_date')
                                    ->heading('CHANGE STATUS DATE')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : ''),

                                Column::make('iloe_status')->heading('ILOE STATUS'),
                                Column::make('iloe_cost')->heading('ILOE COST (AED)'),
                                Column::make('iloe_date')
                                    ->heading('ILOE DATE')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : ''),

                                Column::make('contract_submission_status')->heading('LABOUR CONTRACT STATUS'),
                                Column::make('contract_submission_cost')->heading('LABOUR CONTRACT COST (AED)'),
                                Column::make('contract_submission_date')
                                    ->heading('LABOUR CONTRACT DATE')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : ''),

                                Column::make('emirates_id_stage_status')->heading('EMIRATES ID STAGE STATUS'),
                                Column::make('emirates_id_stage_cost')->heading('EMIRATES ID STAGE COST (AED)'),
                                Column::make('emirates_id_stage_date')
                                    ->heading('EMIRATES ID STAGE DATE')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : ''),

                                Column::make('residence_visa_stage_status')->heading('RESIDENCE VISA STAGE STATUS'),
                                Column::make('residence_visa_stage_cost')->heading('RESIDENCE VISA STAGE COST (AED)'),
                                Column::make('residence_visa_stage_date')
                                    ->heading('RESIDENCE VISA STAGE DATE')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : ''),

                                // ── Financial Totals, Remarks & Created Date ────────────
                                Column::make('total_cost')
                                    ->heading('TOTAL PROCESSING COST (AED)')
                                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2)),
                                Column::make('remarks')->heading('REMARKS / NOTES'),
                                Column::make('created_at')
                                    ->heading('RECORD CREATED DATE')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y H:i') : ''),
                            ]),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        $getDateColor = function (?Carbon $date, int $warningDays = 90, int $criticalDays = 30): string {
            if (! $date) return 'gray';
            $today = Carbon::today();
            if ($today->gt($date)) return 'danger';
            $days = (int) $today->diffInDays($date);
            if ($days <= $criticalDays) return 'danger';
            if ($days <= $warningDays) return 'warning';
            return 'success';
        };

        $formatDateStatus = function (?Carbon $date, int $warningDays = 90, int $criticalDays = 30, string $warningPrefix = 'Expiring in'): string {
            if (! $date) return 'Not Set';
            $today = Carbon::today();
            $formatted = $date->format('d M Y');
            if ($today->gt($date)) {
                $days = (int) $date->diffInDays($today);
                return "{$formatted} (Expired {$days}d ago)";
            }
            $days = (int) $today->diffInDays($date);
            if ($days === 0) return "{$formatted} (Expires Today)";
            if ($days <= $warningDays) return "{$formatted} ({$warningPrefix} {$days}d)";
            return "{$formatted} (Valid · {$days}d left)";
        };

        return $infolist
            ->schema([
                // ── 1. Hero Overview Card (Clean typography, no decorative icon clutter) ──
                Infolists\Components\Section::make('Overview & Application Status')
                    ->icon('heroicon-m-identification')
                    ->schema([
                        Infolists\Components\TextEntry::make('employee_name')
                            ->label('Employee Name')
                            ->weight(FontWeight::Bold)
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('job_description')
                            ->label('Designation / Job Title')
                            ->placeholder('No Job Title Recorded'),

                        Infolists\Components\TextEntry::make('nationality')
                            ->label('Nationality')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('ledgerBranch.name')
                            ->label('Sponsoring Branch')
                            ->placeholder('—')
                            ->weight(FontWeight::SemiBold),

                        Infolists\Components\TextEntry::make('process_type')
                            ->label('Process Type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'new_inside'   => 'New Joiner (Inside UAE)',
                                'new_outside'  => 'New Joiner (Outside UAE)',
                                'renewal'      => 'Visa Renewal',
                                'cancellation' => 'Visa Cancellation',
                                default        => ucfirst(str_replace('_', ' ', (string) $state)),
                            })
                            ->color('gray'),

                        Infolists\Components\TextEntry::make('proCategory.name')
                            ->label('Category Tier')
                            ->badge()
                            ->placeholder('—')
                            ->color('gray'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Application Status')
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

                        Infolists\Components\TextEntry::make('total_cost')
                            ->label('Total Processing Cost')
                            ->money('AED')
                            ->weight(FontWeight::Bold)
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->color('success'),
                    ])->columns(4),

                // ── 2. Progress Bar UI: 9-Stage Visa Pipeline Tracker ─────────
                Infolists\Components\Section::make('Visa Processing Pipeline Progress')
                    ->icon('heroicon-m-chart-bar')
                    ->schema([
                        Infolists\Components\ViewEntry::make('pipeline_progress')
                            ->view('filament.infolists.pipeline-progress')
                            ->columnSpanFull(),
                    ]),

                // ── 3. Color-Coded Expirations & UAE Compliance ───────────────
                Infolists\Components\Section::make('Document Expirations & UAE Compliance Status')
                    ->description('Real-time validity indicators with dynamic color codes (Red: Expired / Critical, Amber: Expiring Soon, Green: Valid).')
                    ->icon('heroicon-m-shield-check')
                    ->schema([
                        // Residence Visa Expiry
                        Infolists\Components\TextEntry::make('residence_visa_expiry')
                            ->label('Residence Visa Expiry')
                            ->badge()
                            ->color(fn (?Carbon $state) => $getDateColor($state, warningDays: 90, criticalDays: 30))
                            ->formatStateUsing(fn (?Carbon $state) => $formatDateStatus($state, warningDays: 90, criticalDays: 30, warningPrefix: 'Expiring in')),

                        // Passport Expiry (UAE 6-Month Rule)
                        Infolists\Components\TextEntry::make('passport_expiry')
                            ->label('Passport Expiry (6-Mo Rule)')
                            ->badge()
                            ->color(fn (?Carbon $state) => $getDateColor($state, warningDays: 180, criticalDays: 30))
                            ->formatStateUsing(fn (?Carbon $state) => $formatDateStatus($state, warningDays: 180, criticalDays: 30, warningPrefix: 'Critical < 6 Mo:')),

                        // Emirates ID Expiry
                        Infolists\Components\TextEntry::make('emirates_id_expiry')
                            ->label('Emirates ID Expiry')
                            ->badge()
                            ->color(fn (?Carbon $state) => $getDateColor($state, warningDays: 90, criticalDays: 30))
                            ->formatStateUsing(fn (?Carbon $state) => $formatDateStatus($state, warningDays: 90, criticalDays: 30, warningPrefix: 'Expiring in')),

                        // Labour Card Expiry
                        Infolists\Components\TextEntry::make('labour_card_expiry')
                            ->label('Labour Card Expiry')
                            ->badge()
                            ->color(fn (?Carbon $state) => $getDateColor($state, warningDays: 90, criticalDays: 30))
                            ->formatStateUsing(fn (?Carbon $state) => $formatDateStatus($state, warningDays: 90, criticalDays: 30, warningPrefix: 'Expiring in')),

                        // Labour Contract Expiry
                        Infolists\Components\TextEntry::make('labour_contract_expiry')
                            ->label('Labour Contract Expiry')
                            ->badge()
                            ->color(fn (?Carbon $state) => $getDateColor($state, warningDays: 90, criticalDays: 30))
                            ->formatStateUsing(fn (?Carbon $state) => $formatDateStatus($state, warningDays: 90, criticalDays: 30, warningPrefix: 'Expiring in')),

                        // Company Trade License Expiry
                        Infolists\Components\TextEntry::make('license_expiry')
                            ->label('Branch Trade License Expiry')
                            ->badge()
                            ->color(fn (?Carbon $state) => $getDateColor($state, warningDays: 60, criticalDays: 15))
                            ->formatStateUsing(fn (?Carbon $state) => $formatDateStatus($state, warningDays: 60, criticalDays: 15, warningPrefix: 'Expiring in')),

                        // Overall Compliance Badge
                        Infolists\Components\TextEntry::make('compliance_badge')
                            ->label('Consolidated Overstay Status')
                            ->badge()
                            ->color(fn (ProRecord $record) => $record->compliance_color)
                            ->helperText(fn (ProRecord $record) => $record->compliance_description ?: 'Compliant with UAE immigration rules'),

                        // Grace Period Days
                        Infolists\Components\TextEntry::make('grace_period_days')
                            ->label('Grace Period Allowance')
                            ->suffix(' days')
                            ->weight(FontWeight::Medium),

                        // Fines Start Date
                        Infolists\Components\TextEntry::make('fines_start_date')
                            ->label('Fines Trigger Date')
                            ->date('d M Y')
                            ->placeholder('No Fine Date Set'),

                        // Fine PR-50
                        Infolists\Components\TextEntry::make('fine_pr_50')
                            ->label('PR-50 Overstay Fine')
                            ->money('AED')
                            ->color(fn ($state) => (float) $state > 0 ? 'danger' : 'gray')
                            ->weight(FontWeight::SemiBold),

                        // Fine EID-20
                        Infolists\Components\TextEntry::make('fine_eid_20')
                            ->label('EID-20 Delay Fine')
                            ->money('AED')
                            ->color(fn ($state) => (float) $state > 0 ? 'danger' : 'gray')
                            ->weight(FontWeight::SemiBold),

                        // Combined Fines
                        Infolists\Components\TextEntry::make('calculated_total_fines')
                            ->label('Total Accumulated Fines')
                            ->money('AED')
                            ->color(fn ($state) => (float) $state > 0 ? 'danger' : 'success')
                            ->weight(FontWeight::Bold),
                    ])->columns(3),

                // ── 4. Government & Identity Numbers ─────────────────────────
                Infolists\Components\Section::make('Official Government Identifiers')
                    ->description('Click any identifier to copy.')
                    ->icon('heroicon-m-identification')
                    ->schema([
                        Infolists\Components\TextEntry::make('residence_visa_number')
                            ->label('Residence Visa #')
                            ->placeholder('Not Recorded')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('passport_number')
                            ->label('Passport #')
                            ->placeholder('Not Recorded')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('emirates_id_number')
                            ->label('Emirates ID #')
                            ->placeholder('784-XXXX-XXXXXXX-X')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('labour_card_number')
                            ->label('Labour Card #')
                            ->placeholder('Not Recorded')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('unified_id')
                            ->label('Unified ID (UID) #')
                            ->placeholder('Not Recorded')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('person_code')
                            ->label('MOHRE Person Code #')
                            ->placeholder('Not Recorded')
                            ->copyable(),
                    ])->columns(3),

                // ── 5. Remarks & System History ──────────────────────────────
                Infolists\Components\Section::make('Remarks & Audit Information')
                    ->icon('heroicon-m-chat-bubble-left-ellipsis')
                    ->schema([
                        Infolists\Components\TextEntry::make('remarks')
                            ->label('Administrative Notes / Remarks')
                            ->placeholder('No remarks recorded.')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Recorded By')
                            ->placeholder('System'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created Date')
                            ->dateTime('d M Y, h:i A'),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Synchronized')
                            ->dateTime('d M Y, h:i A'),
                    ])->columns(3),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $today = Carbon::today()->toDateString();
        $in30Days = Carbon::today()->addDays(30)->toDateString();
        $in180Days = Carbon::today()->addDays(180)->toDateString();

        return parent::getEloquentQuery()
            ->with(['ledgerBranch', 'proCategory', 'user'])
            ->select('pro_records.*')
            ->selectRaw("
                CASE
                    -- 1. Active overstay fines past fines_start_date (Critical action)
                    WHEN fines_start_date IS NOT NULL AND fines_start_date < '{$today}' THEN 1
                    -- 2. Within grace period (visa expired, fine start date imminent)
                    WHEN fines_start_date IS NOT NULL AND fines_start_date >= '{$today}' AND residence_visa_expiry IS NOT NULL AND residence_visa_expiry < '{$today}' THEN 2
                    -- 3. Visa expiring within 30 days
                    WHEN residence_visa_expiry IS NOT NULL AND residence_visa_expiry BETWEEN '{$today}' AND '{$in30Days}' THEN 3
                    -- 4. Passport expiring within 180 days (< 6 months)
                    WHEN passport_expiry IS NOT NULL AND passport_expiry BETWEEN '{$today}' AND '{$in180Days}' THEN 4
                    -- 5. In-progress active pipeline
                    WHEN status = 'in_progress' THEN 5
                    -- 6. Draft records
                    WHEN status = 'draft' THEN 6
                    -- 7. On hold
                    WHEN status = 'on_hold' THEN 7
                    -- 8. Cancelled / Completed
                    ELSE 8
                END as action_priority
            ");
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
        } elseif ($processType === 'cancellation') {
            $set('offer_letter_cost', 0.00);
            $set('dubins_cost', 0.00);
            $set('wp_submission_cost', 0.00);
            $set('evisa_cost', 0.00);
            $set('change_status_cost', 0.00);
            $set('iloe_cost', 0.00);
            $set('contract_submission_cost', 0.00);
            $set('emirates_id_stage_cost', 0.00);
            $set('residence_visa_stage_cost', 0.00);
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
