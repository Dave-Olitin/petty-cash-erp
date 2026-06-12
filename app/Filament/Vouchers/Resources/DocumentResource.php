<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\DocumentResource\Pages;
use App\Models\Document;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-clip';

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = 'Transactions';

    protected static ?string $modelLabel = 'Transaction';

    protected static ?string $pluralModelLabel = 'Transactions';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user() && auth()->user()->hasAnyRole(['Admin', 'Super Admin']);
    }

    public static function canViewAny(): bool
    {
        return auth()->user() && auth()->user()->hasAnyRole(['Admin', 'Super Admin']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        // Load documents that either belong to a voucher (not soft-deleted) or a float replenishment
        return parent::getEloquentQuery()
            ->where(function (Builder $query) {
                $query->whereHas('voucher')
                      ->orWhereHas('floatReplenishment');
            })
            ->with(['voucher', 'floatReplenishment', 'uploader']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('d-M-Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('voucher_number_or_ref')
                    ->label('Voucher / Ref #')
                    ->state(fn ($record) => $record->voucher?->voucher_number ?? $record->floatReplenishment?->reference ?? 'Float Replenishment')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(fn ($q) => 
                            $q->whereHas('voucher', fn ($v) => $v->where('voucher_number', 'like', "%{$search}%"))
                              ->orWhereHas('floatReplenishment', fn ($f) => $f->where('reference', 'like', "%{$search}%"))
                        );
                    })
                    ->url(fn ($record) => $record->voucher_id 
                        ? \App\Filament\Vouchers\Resources\VoucherResource::getUrl('view', ['record' => $record->voucher_id]) 
                        : ($record->float_replenishment_id ? route('replenishment.pdf', ['replenishment' => $record->float_replenishment_id]) : null)
                    )
                    ->color('warning')
                    ->weight('bold')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('file_name')
                    ->label('File Name')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('file_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'pdf' => 'danger',
                        'png', 'jpg', 'jpeg', 'webp' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Uploaded By')
                    ->placeholder('System')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('payee')
                    ->label('Payee / Remarks')
                    ->state(fn ($record) => $record->voucher?->payee ?? $record->floatReplenishment?->remarks ?? '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(fn ($q) =>
                            $q->whereHas('voucher', fn ($v) => $v->where('payee', 'like', "%{$search}%"))
                              ->orWhereHas('floatReplenishment', fn ($f) => $f->where('remarks', 'like', "%{$search}%"))
                        );
                    })
                    ->limit(55)
                    ->tooltip(fn ($state) => $state && strlen($state) > 55 ? $state : null)
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->state(fn ($record) => $record->voucher?->amount ?? $record->floatReplenishment?->amount ?? 0)
                    ->money('AED')
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('file_type')
                    ->label('File Type')
                    ->options(function () {
                        return Document::query()
                            ->distinct()
                            ->whereNotNull('file_type')
                            ->pluck('file_type', 'file_type')
                            ->mapWithKeys(fn ($type) => [$type => strtoupper($type)])
                            ->toArray();
                    }),

                Tables\Filters\SelectFilter::make('source_type')
                    ->label('Document Source')
                    ->options([
                        'voucher' => 'Voucher Attachments',
                        'float_replenishment' => 'Fund Voucher (Float Replenishment)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['value'] === 'voucher',
                                fn (Builder $query) => $query->whereNotNull('voucher_id')
                            )
                            ->when(
                                $data['value'] === 'float_replenishment',
                                fn (Builder $query) => $query->whereNotNull('float_replenishment_id')
                            );
                    }),

                Tables\Filters\SelectFilter::make('voucher_type')
                    ->label('Voucher Type')
                    ->options([
                        'petty_cash' => 'Petty Cash Request',
                        'payment' => 'Payment Voucher',
                        'receipt' => 'Receipt Voucher',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $type) => $query->whereHas('voucher', fn ($q) => $q->where('type', $type))
                        );
                    }),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->label('Uploaded From'),
                        Forms\Components\DatePicker::make('created_until')->label('Uploaded To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('preview')
                        ->label('View / Preview')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->url(fn ($record) => asset('storage/' . $record->file_path))
                        ->openUrlInNewTab(),

                    Tables\Actions\Action::make('download')
                        ->label('Download')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->url(fn ($record) => asset('storage/' . $record->file_path))
                        ->openUrlInNewTab()
                        ->extraAttributes(['download' => '']),

                    Tables\Actions\Action::make('view_details')
                        ->label('Document Details')
                        ->icon('heroicon-o-information-circle')
                        ->color('gray')
                        ->modalHeading('Document Details')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close')
                        ->form([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('created_at_formatted')
                                        ->label('Uploaded At')
                                        ->disabled()
                                        ->afterStateHydrated(fn ($component, $record) => $component->state($record->created_at?->format('d-M-Y H:i:s') ?? '—')),

                                    Forms\Components\TextInput::make('uploader_name')
                                        ->label('Uploaded By')
                                        ->disabled()
                                        ->afterStateHydrated(fn ($component, $record) => $component->state($record->uploader ? $record->uploader->name : 'System')),

                                    Forms\Components\TextInput::make('file_name')
                                        ->label('File Name')
                                        ->disabled()
                                        ->afterStateHydrated(fn ($component, $record) => $component->state($record->file_name)),

                                    Forms\Components\TextInput::make('file_type')
                                        ->label('File Type')
                                        ->disabled()
                                        ->afterStateHydrated(fn ($component, $record) => $component->state(strtoupper($record->file_type))),

                                    Forms\Components\TextInput::make('voucher_number')
                                        ->label('Voucher / Ref #')
                                        ->disabled()
                                        ->afterStateHydrated(fn ($component, $record) => $component->state($record->voucher?->voucher_number ?? $record->floatReplenishment?->reference ?? '—')),

                                    Forms\Components\TextInput::make('voucher_type')
                                        ->label('Source Type')
                                        ->disabled()
                                        ->afterStateHydrated(fn ($component, $record) => $component->state(
                                            $record->voucher 
                                                ? match ($record->voucher->type) {
                                                    'petty_cash' => 'Petty Cash Request',
                                                    'payment' => 'Payment Voucher',
                                                    'receipt' => 'Receipt Voucher',
                                                    default => $record->voucher->type
                                                }
                                                : ($record->floatReplenishment ? 'Float Replenishment (Fund Voucher)' : '—')
                                        )),

                                    Forms\Components\TextInput::make('voucher_payee')
                                        ->label('Payee / Remarks')
                                        ->disabled()
                                        ->afterStateHydrated(fn ($component, $record) => $component->state($record->voucher?->payee ?? $record->floatReplenishment?->remarks ?? '—')),

                                    Forms\Components\TextInput::make('voucher_amount')
                                        ->label('Amount')
                                        ->disabled()
                                        ->afterStateHydrated(fn ($component, $record) => $component->state(
                                            $record->voucher 
                                                ? 'AED ' . number_format($record->voucher->amount, 2)
                                                : ($record->floatReplenishment ? 'AED ' . number_format($record->floatReplenishment->amount, 2) : '—')
                                        )),
                                ]),
                        ]),
                ])
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->headerActions([
                \pxlrbt\FilamentExcel\Actions\Tables\ExportAction::make()
                    ->label('Export to Excel')
                    ->exports([
                        \pxlrbt\FilamentExcel\Exports\ExcelExport::make('table')
                            ->fromTable()
                            ->withColumns([
                                \pxlrbt\FilamentExcel\Columns\Column::make('file_path')
                                    ->heading('File Link')
                                    ->getStateUsing(fn ($record) => asset('storage/' . $record->file_path)),
                            ]),
                    ]),
            ])
            ->bulkActions([
                \pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction::make()
                    ->exports([
                        \pxlrbt\FilamentExcel\Exports\ExcelExport::make('table')
                            ->fromTable()
                            ->withColumns([
                                \pxlrbt\FilamentExcel\Columns\Column::make('file_path')
                                    ->heading('File Link')
                                    ->getStateUsing(fn ($record) => asset('storage/' . $record->file_path)),
                            ]),
                    ])
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
        ];
    }
}
