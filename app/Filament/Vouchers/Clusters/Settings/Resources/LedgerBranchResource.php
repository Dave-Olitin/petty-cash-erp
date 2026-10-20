<?php

namespace App\Filament\Vouchers\Clusters\Settings\Resources;

use App\Filament\Vouchers\Clusters\Settings;
use App\Filament\Vouchers\Clusters\Settings\Resources\LedgerBranchResource\Pages;
use App\Filament\Vouchers\Clusters\Settings\Resources\LedgerBranchResource\RelationManagers;
use App\Models\LedgerBranch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LedgerBranchResource extends Resource
{
    protected static ?string $model = LedgerBranch::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $cluster = Settings::class;

    protected static ?string $navigationLabel = 'Ledger Branches';

    protected static ?int $navigationSort = 11;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_settings') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Branch & Trade License Details')
                    ->description('Company registration, trade license and issuing authority')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Branch / Entity Name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('e.g. ETC, TG, SB MAIN, Trimmers LLC'),

                        Forms\Components\TextInput::make('license_number')
                            ->label('Trade License #')
                            ->placeholder('e.g. CN-1029384')
                            ->maxLength(100),

                        Forms\Components\Select::make('issuing_authority')
                            ->label('Issuing Authority')
                            ->options([
                                'Dubai DED'             => 'Dubai Economy & Tourism (DET / DED)',
                                'Sharjah DED'           => 'Sharjah Economic Development (SEDD)',
                                'Abu Dhabi DED'         => 'Abu Dhabi DED',
                                'Ajman DED'             => 'Ajman DED',
                                'DMCC'                  => 'DMCC Free Zone',
                                'JAFZA'                 => 'JAFZA Free Zone',
                                'SAIF Zone'             => 'SAIF Zone (Sharjah)',
                                'DAFZA'                 => 'Dubai Airport Freezone (DAFZA)',
                                'Other'                 => 'Other UAE Licensing Authority',
                            ])
                            ->searchable(),

                        Forms\Components\DatePicker::make('license_expiry')
                            ->label('Trade License Expiry')
                            ->native(false)
                            ->helperText('Trade license expiration date for corporate compliance.'),
                    ])->columns(2),

                Forms\Components\Section::make('Establishment Cards')
                    ->description('MOHRE Labor and Immigration / ICP establishment card numbers and expiration dates')
                    ->schema([
                        Forms\Components\TextInput::make('mohre_est_card_number')
                            ->label('MOHRE Establishment Card #')
                            ->placeholder('e.g. 70012345')
                            ->maxLength(100),

                        Forms\Components\DatePicker::make('mohre_est_card_expiry')
                            ->label('MOHRE Card Expiry')
                            ->native(false),

                        Forms\Components\TextInput::make('immigration_est_card_number')
                            ->label('Immigration / ICP Card #')
                            ->placeholder('e.g. 201/2020/12345')
                            ->maxLength(100),

                        Forms\Components\DatePicker::make('immigration_est_card_expiry')
                            ->label('Immigration Card Expiry')
                            ->native(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Branch / Entity')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('issuing_authority')
                    ->label('Authority')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('license_expiry')
                    ->label('License Expiry')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('mohre_est_card_expiry')
                    ->label('MOHRE Card Expiry')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('immigration_est_card_expiry')
                    ->label('Immigration Card Expiry')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('voucher_items_count')
                    ->counts('voucherItems')
                    ->label('Total Vouchers')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLedgerBranches::route('/'),
            'create' => Pages\CreateLedgerBranch::route('/create'),
            'edit' => Pages\EditLedgerBranch::route('/{record}/edit'),
        ];
    }
}
