<?php

namespace App\Filament\Vouchers\Resources;

use App\Enums\AccountType;
use App\Filament\Vouchers\Resources\AccountCodeResource\Pages;
use App\Filament\Vouchers\Resources\AccountCodeResource\RelationManagers;
use App\Models\AccountCode;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountCodeResource extends Resource
{
    protected static ?string $model = AccountCode::class;

    protected static ?string $navigationIcon    = 'heroicon-o-list-bullet';
    protected static ?string $navigationGroup   = 'Accounting';
    protected static ?string $navigationLabel   = 'Chart of Accounts';
    protected static ?int    $navigationSort    = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('manage_settings') ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_settings') ?? false;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Form
    // ──────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Account Identity')
                    ->description('Basic identifiers for this account in the chart of accounts.')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Account Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('e.g. 1001')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if (!$state) return;
                                
                                $firstDigit = substr($state, 0, 1);
                                $type = match($firstDigit) {
                                    '1' => AccountType::Asset,
                                    '2' => AccountType::Liability,
                                    '3' => AccountType::Equity,
                                    '4' => AccountType::Revenue,
                                    '5' => AccountType::Expense,
                                    default => null,
                                };

                                if ($type) {
                                    $set('type', $type->value);
                                    $set('normal_balance', $type->normalBalance());
                                }
                            })
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('name')
                            ->label('Account Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Petty Cash')
                            ->columnSpan(2),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Account Classification')
                    ->description('The account type determines its normal balance and how it appears in financial reports.')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Account Type')
                            ->options(AccountType::options())
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                // Auto-fill normal_balance whenever the type changes
                                if ($state && $type = AccountType::tryFrom($state)) {
                                    $set('normal_balance', $type->normalBalance());
                                }
                            })
                            ->columnSpan(1),

                        Forms\Components\Select::make('normal_balance')
                            ->label('Normal Balance')
                            ->options([
                                'debit'  => 'Debit (DR)',
                                'credit' => 'Credit (CR)',
                            ])
                            ->required()
                            ->native(false)
                            ->helperText('Auto-filled from Account Type. Override only if needed.')
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('description')
                            ->label('Description / Notes')
                            ->placeholder('Optional: describe what transactions belong here.')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('entity')
                            ->label('Entity')
                            ->multiple()
                            ->options(\App\Models\VoucherTemplate::pluck('company_name', 'id'))
                            ->placeholder('Select Entities')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Enabled')
                            ->default(true)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Table
    // ──────────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->orderByRaw("
                    CASE type
                        WHEN 'asset' THEN 1
                        WHEN 'liability' THEN 2
                        WHEN 'equity' THEN 3
                        WHEN 'revenue' THEN 4
                        WHEN 'expense' THEN 5
                        ELSE 6
                    END ASC
                ")
                ->orderBy('code', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::Medium),

                Tables\Columns\TextColumn::make('name')
                    ->label('Account Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof AccountType ? $state->label() : ucfirst($state))
                    ->color(fn ($state) => $state instanceof AccountType ? $state->color() : 'gray')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw("
                        CASE type
                            WHEN 'asset' THEN 1
                            WHEN 'liability' THEN 2
                            WHEN 'equity' THEN 3
                            WHEN 'revenue' THEN 4
                            WHEN 'expense' THEN 5
                            ELSE 6
                        END $direction
                    ")->orderBy('code', 'asc')),

                Tables\Columns\TextColumn::make('normal_balance')
                    ->label('Normal Balance')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => strtoupper($state))
                    ->color(fn (string $state) => $state === 'debit' ? 'info' : 'warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('entity')
                    ->label('Entity')
                    ->formatStateUsing(function ($state) {
                        if (empty($state) || !is_array($state)) return '-';
                        return \App\Models\VoucherTemplate::whereIn('id', $state)->pluck('company_name')->join(', ');
                    })
                    ->toggleable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Enabled')
                    ->sortable(),

                Tables\Columns\TextColumn::make('voucher_items_count')
                    ->counts('voucherItems')
                    ->label('Vouchers')
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Account Type')
                    ->options(AccountType::options())
                    ->native(false),

                Tables\Filters\SelectFilter::make('normal_balance')
                    ->label('Normal Balance')
                    ->options([
                        'debit'  => 'Debit (DR)',
                        'credit' => 'Credit (CR)',
                    ])
                    ->native(false),
            ])
            ->headerActions([
                Tables\Actions\Action::make('autoClassifyAll')
                    ->label('Auto-Classify All')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Auto-Classify All Accounts')
                    ->modalDescription('This will update the Type and Normal Balance of ALL accounts based on the first digit of their code (1=Asset, 2=Liability, etc.). Are you sure?')
                    ->action(function () {
                        $accounts = AccountCode::all();
                        $count = 0;

                        foreach ($accounts as $account) {
                            $firstDigit = substr($account->code, 0, 1);
                            $type = match($firstDigit) {
                                '1' => AccountType::Asset,
                                '2' => AccountType::Liability,
                                '3' => AccountType::Equity,
                                '4' => AccountType::Revenue,
                                '5' => AccountType::Expense,
                                default => null,
                            };

                            if ($type) {
                                $account->update([
                                    'type' => $type,
                                    'normal_balance' => $type->normalBalance(),
                                ]);
                                $count++;
                            }
                        }

                        \Filament\Notifications\Notification::make()
                            ->title("Successfully re-classified {$count} accounts.")
                            ->success()
                            ->send();
                    }),
                \Filament\Tables\Actions\ImportAction::make()
                    ->importer(\App\Filament\Imports\AccountCodeImporter::class),
            ])
            ->recordUrl(fn ($record) => AccountCodeResource::getUrl('view', ['record' => $record]))
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Relations & Pages
    // ──────────────────────────────────────────────────────────────────────

    public static function getRelations(): array
    {
        return [
            RelationManagers\VoucherItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAccountCodes::route('/'),
            'create' => Pages\CreateAccountCode::route('/create'),
            'view'   => Pages\ViewAccountCode::route('/{record}'),
            'edit'   => Pages\EditAccountCode::route('/{record}/edit'),
        ];
    }
}
