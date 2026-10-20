<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\ProCategoryResource\Pages;
use App\Models\ProCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;

class ProCategoryResource extends Resource
{
    protected static ?string $model = ProCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'PRO & Government Relations';

    protected static ?string $navigationLabel = 'PRO Categories & Fees';

    protected static ?string $modelLabel = 'PRO Category';

    protected static ?string $pluralModelLabel = 'PRO Categories';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['Admin', 'Super Admin', 'PRO Officer'])
            || $user->can('pro_category.view')
            || $user->can('pro_category.manage');
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['Admin', 'Super Admin', 'PRO Officer'])
            || $user->can('pro_category.manage');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['Admin', 'Super Admin', 'PRO Officer'])
            || $user->can('pro_category.manage');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['Admin', 'Super Admin', 'PRO Officer'])
            || $user->can('pro_category.manage');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Category Information')
                    ->description('Define company classification tiers (e.g. Category 1, Category 2, Free Zone, etc.)')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Category Name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g. Category 1, Category 2, Free Zone')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->label('Short Code')
                            ->placeholder('e.g. CAT1, CAT2, FZ')
                            ->maxLength(50),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->placeholder('Notes on this category tier, compliance criteria, or profession types.')
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('New Joiner Default Fees (AED)')
                    ->description('Baseline government and typing fees automatically populated when adding a new joiner.')
                    ->schema([
                        Forms\Components\TextInput::make('offer_letter_cost')
                            ->label('Offer Letter')
                            ->numeric()
                            ->default(50.00)
                            ->prefix('AED')
                            ->required(),

                        Forms\Components\TextInput::make('dubins_cost')
                            ->label('DUBINS (Insurance)')
                            ->numeric()
                            ->default(120.00)
                            ->prefix('AED')
                            ->required(),

                        Forms\Components\TextInput::make('wp_submission_cost')
                            ->label('WP Submission (Work Permit)')
                            ->helperText('Mainland work permit fee for this category tier.')
                            ->numeric()
                            ->default(1200.00)
                            ->prefix('AED')
                            ->required(),

                        Forms\Components\TextInput::make('evisa_cost')
                            ->label('EVISA (Entry Permit)')
                            ->numeric()
                            ->default(450.00)
                            ->prefix('AED')
                            ->required(),

                        Forms\Components\TextInput::make('change_status_cost')
                            ->label('Change Status (Inside UAE)')
                            ->numeric()
                            ->default(650.00)
                            ->prefix('AED')
                            ->required(),

                        Forms\Components\TextInput::make('iloe_cost')
                            ->label('ILOE Insurance')
                            ->numeric()
                            ->default(63.00)
                            ->prefix('AED')
                            ->required(),

                        Forms\Components\TextInput::make('contract_submission_cost')
                            ->label('Contract Submission')
                            ->numeric()
                            ->default(100.00)
                            ->prefix('AED')
                            ->required(),

                        Forms\Components\TextInput::make('emirates_id_cost')
                            ->label('Emirates ID & Medical')
                            ->numeric()
                            ->default(370.00)
                            ->prefix('AED')
                            ->required(),

                        Forms\Components\TextInput::make('residence_visa_cost')
                            ->label('Residence Visa Stamping')
                            ->numeric()
                            ->default(450.00)
                            ->prefix('AED')
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Renewal Default Fees (AED)')
                    ->description('Baseline government and typing fees automatically populated when renewing a visa.')
                    ->schema([
                        Forms\Components\TextInput::make('renewal_wp_cost')
                            ->label('Work Permit Renewal')
                            ->helperText('Renewal fee for this category tier.')
                            ->numeric()
                            ->default(1200.00)
                            ->prefix('AED')
                            ->required(),

                        Forms\Components\TextInput::make('renewal_dubins_cost')
                            ->label('DUBINS Renewal')
                            ->numeric()
                            ->default(120.00)
                            ->prefix('AED')
                            ->required(),

                        Forms\Components\TextInput::make('renewal_iloe_cost')
                            ->label('ILOE Renewal')
                            ->numeric()
                            ->default(63.00)
                            ->prefix('AED')
                            ->required(),

                        Forms\Components\TextInput::make('renewal_contract_cost')
                            ->label('Contract Renewal')
                            ->numeric()
                            ->default(100.00)
                            ->prefix('AED')
                            ->required(),

                        Forms\Components\TextInput::make('renewal_emirates_id_cost')
                            ->label('Emirates ID & Medical Renewal')
                            ->numeric()
                            ->default(370.00)
                            ->prefix('AED')
                            ->required(),

                        Forms\Components\TextInput::make('renewal_residence_visa_cost')
                            ->label('Residence Visa Renewal')
                            ->numeric()
                            ->default(450.00)
                            ->prefix('AED')
                            ->required(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Category Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('wp_submission_cost')
                    ->label('New WP Fee')
                    ->money('AED')
                    ->sortable(),

                Tables\Columns\TextColumn::make('renewal_wp_cost')
                    ->label('Renewal WP Fee')
                    ->money('AED')
                    ->sortable(),

                Tables\Columns\TextColumn::make('new_joiner_inside_total')
                    ->label('Total (New Joiner Inside)')
                    ->money('AED')
                    ->weight(FontWeight::Bold)
                    ->color('info'),

                Tables\Columns\TextColumn::make('renewal_total')
                    ->label('Total (Renewal)')
                    ->money('AED')
                    ->weight(FontWeight::Bold)
                    ->color('success'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProCategories::route('/'),
            'create' => Pages\CreateProCategory::route('/create'),
            'edit'   => Pages\EditProCategory::route('/{record}/edit'),
        ];
    }
}
