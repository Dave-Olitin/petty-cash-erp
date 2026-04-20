<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\PeriodCloseResource\Pages;
use App\Models\PeriodClose;
use App\Services\PeriodCloseService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PeriodCloseResource extends Resource
{
    protected static ?string $model = PeriodClose::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';
    protected static ?string $navigationGroup = 'Accounting';
    protected static ?string $navigationLabel = 'Period Closes';
    protected static ?string $modelLabel = 'Period Close';
    protected static ?int $navigationSort = 6;
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user?->hasAnyRole(['Super Admin', 'Admin', 'Accountant']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Period Definition')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Period Name')
                            ->placeholder('e.g. April 2026 or Q1 FY2026')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\Placeholder::make('status_display')
                            ->label('Status')
                            ->content(fn (?PeriodClose $record) => $record?->status ? strtoupper($record->status) : 'DRAFT'),

                        Forms\Components\DatePicker::make('period_start')
                            ->label('Period Start')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),

                        Forms\Components\DatePicker::make('period_end')
                            ->label('Period End')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),

                        Forms\Components\Textarea::make('closing_notes')
                            ->label('Accountant Notes')
                            ->columnSpanFull()
                            ->rows(3)
                            ->placeholder('Any remarks for this period close...'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('period_start', 'desc')
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Period')
                    ->searchable()
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('period_start')
                    ->label('From')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_end')
                    ->label('To')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => $state === 'closed' ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state) => strtoupper($state)),

                Tables\Columns\TextColumn::make('total_vouchers_paid')
                    ->label('Vouchers Paid')
                    ->formatStateUsing(fn ($state) => 'AED ' . number_format((float) $state, 2))
                    ->alignRight()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('total_ap_billed')
                    ->label('AP Billed')
                    ->formatStateUsing(fn ($state) => 'AED ' . number_format((float) $state, 2))
                    ->alignRight()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('total_ap_balance')
                    ->label('AP Balance')
                    ->formatStateUsing(fn ($state) => 'AED ' . number_format((float) $state, 2))
                    ->alignRight()
                    ->color(fn ($state) => (float) $state > 0.01 ? 'danger' : 'success')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),

                Tables\Columns\TextColumn::make('voucher_count')
                    ->label('Vouchers')
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('closed_at')
                    ->label('Closed At')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Open')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('closer.name')
                    ->label('Closed By')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['draft' => 'Draft', 'closed' => 'Closed']),
            ])
            ->actions([
                // Calculate / re-aggregate
                Tables\Actions\Action::make('calculate')
                    ->label('Calculate')
                    ->icon('heroicon-o-calculator')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Re-aggregate figures for this period')
                    ->visible(fn (PeriodClose $record) => !$record->isClosed())
                    ->action(function (PeriodClose $record) {
                        app(PeriodCloseService::class)->aggregate($record);
                        Notification::make()->title('Period figures calculated.')->success()->send();
                    }),

                // Close the period
                Tables\Actions\Action::make('close_period')
                    ->label('Close Period')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->iconButton()
                    ->tooltip('Formally close this period')
                    ->visible(fn (PeriodClose $record) => !$record->isClosed())
                    ->requiresConfirmation()
                    ->modalHeading('Close Period')
                    ->modalDescription('This will calculate final figures and mark the period as closed. The underlying data is NOT locked — this is a reporting snapshot.')
                    ->modalSubmitActionLabel('Yes, Close Period')
                    ->action(function (PeriodClose $record) {
                        app(PeriodCloseService::class)->close($record, auth()->user());
                        Notification::make()->title('Period closed successfully.')->success()->send();
                    }),

                // Re-open (Super Admin only)
                Tables\Actions\Action::make('reopen')
                    ->label('Re-open')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Re-open this closed period')
                    ->visible(fn (PeriodClose $record) => $record->isClosed() && auth()->user()?->hasRole('Super Admin'))
                    ->requiresConfirmation()
                    ->action(function (PeriodClose $record) {
                        app(PeriodCloseService::class)->reopen($record);
                        Notification::make()->title('Period re-opened.')->warning()->send();
                    }),

                Tables\Actions\ViewAction::make()->iconButton(),
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->visible(fn (PeriodClose $record) => !$record->isClosed()),
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (PeriodClose $record) => !$record->isClosed()),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPeriodCloses::route('/'),
            'create' => Pages\CreatePeriodClose::route('/create'),
            'view'   => Pages\ViewPeriodClose::route('/{record}'),
            'edit'   => Pages\EditPeriodClose::route('/{record}/edit'),
        ];
    }
}
