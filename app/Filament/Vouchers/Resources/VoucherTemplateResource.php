<?php

namespace App\Filament\Vouchers\Resources;

use App\Models\VoucherTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VoucherTemplateResource extends Resource
{
    protected static ?string $model = VoucherTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Company Templates';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_settings') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Company Information')->schema([
                    Forms\Components\TextInput::make('company_name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('tel_no')
                        ->label('Telephone')
                        ->maxLength(50),

                    Forms\Components\Textarea::make('address')
                        ->rows(3),

                    Forms\Components\TextInput::make('trn')
                        ->label('TRN')
                        ->maxLength(50),
                ])->columns(2),

                Forms\Components\Section::make('Voucher Settings')->schema([
                    Forms\Components\TextInput::make('prefix')
                        ->required()
                        ->maxLength(10)
                        ->unique(ignoreRecord: true)
                        ->helperText('e.g. "ETC", "TG", "IC". Used for voucher numbering (ETC-0001).'),

                    Forms\Components\TextInput::make('branch_code')
                        ->maxLength(10)
                        ->helperText('Default branch code for ledger rows (e.g. "ET", "TG").'),

                    Forms\Components\FileUpload::make('logo_path')
                        ->label('Company Logo')
                        ->image()
                        ->directory('template-logos')
                        ->disk('public')
                        ->maxSize(512)
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('prefix')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('branch_code'),
                Tables\Columns\TextColumn::make('tel_no')
                    ->label('Telephone'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('vouchers_count')
                    ->counts('vouchers')
                    ->label('Vouchers'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Vouchers\Resources\VoucherTemplateResource\Pages\ListVoucherTemplates::route('/'),
            'create' => \App\Filament\Vouchers\Resources\VoucherTemplateResource\Pages\CreateVoucherTemplate::route('/create'),
            'edit' => \App\Filament\Vouchers\Resources\VoucherTemplateResource\Pages\EditVoucherTemplate::route('/edit/{record}'),
        ];
    }
}
