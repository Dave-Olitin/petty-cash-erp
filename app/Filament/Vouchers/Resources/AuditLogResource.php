<?php

namespace App\Filament\Vouchers\Resources;

use App\Filament\Vouchers\Resources\AuditLogResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class AuditLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = 'Audit Logs';

    protected static ?string $modelLabel = 'Audit Log';

    protected static ?string $pluralModelLabel = 'Audit Logs';

    protected static ?int $navigationSort = 11;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('manage_settings') ?? false;
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
        return parent::getEloquentQuery()->with(['causer', 'subject']);
    }

    public static function form(Form $form): Form
    {
        // Audit log is read-only, we do not require a separate form layout.
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

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Performed By')
                    ->placeholder('System')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        'submitted' => 'info',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Module')
                    ->formatStateUsing(fn ($state) => $state ? class_basename($state) : '—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('subject_id')
                    ->label('Subject / ID')
                    ->formatStateUsing(function ($record) {
                        $subject = $record->subject;
                        if (!$subject) {
                            return $record->subject_id ? "ID: {$record->subject_id}" : '—';
                        }
                        if (isset($subject->voucher_number)) {
                            return $subject->voucher_number;
                        }
                        if (isset($subject->name)) {
                            return $subject->name;
                        }
                        if (isset($subject->label)) {
                            return $subject->label;
                        }
                        return "ID: {$record->subject_id}";
                    })
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('description')
                    ->label('Action')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),

                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Module')
                    ->options(function () {
                        return Activity::query()
                            ->distinct()
                            ->whereNotNull('subject_type')
                            ->pluck('subject_type', 'subject_type')
                            ->mapWithKeys(fn ($type) => [$type => class_basename($type)])
                            ->toArray();
                    })
                    ->searchable(),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->label('From Date'),
                        Forms\Components\DatePicker::make('created_until')->label('To Date'),
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
                Tables\Actions\Action::make('view_details')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Activity Log Details')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('created_at_formatted')
                                    ->label('Timestamp')
                                    ->disabled()
                                    ->afterStateHydrated(fn ($component, $record) => $component->state($record->created_at?->format('d-M-Y H:i:s') ?? '—')),
                                Forms\Components\TextInput::make('causer_name')
                                    ->label('Performed By')
                                    ->disabled()
                                    ->afterStateHydrated(fn ($component, $record) => $component->state($record->causer?->name ?? 'System')),
                                Forms\Components\TextInput::make('action_type')
                                    ->label('Action')
                                    ->disabled()
                                    ->afterStateHydrated(fn ($component, $record) => $component->state(ucfirst($record->description))),
                                Forms\Components\TextInput::make('subject_info')
                                    ->label('Subject')
                                    ->disabled()
                                    ->afterStateHydrated(fn ($component, $record) => $component->state(($record->subject_type ? class_basename($record->subject_type) : '—') . ' (ID: ' . ($record->subject_id ?? '—') . ')')),
                            ]),
                        Forms\Components\Section::make('Changes')
                            ->schema([
                                Forms\Components\KeyValue::make('old_values')
                                    ->label('Old Values')
                                    ->valuePlaceholder('—')
                                    ->afterStateHydrated(fn ($component, $record) => $component->state($record->properties['old'] ?? [])),
                                Forms\Components\KeyValue::make('new_values')
                                    ->label('New Values')
                                    ->valuePlaceholder('—')
                                    ->afterStateHydrated(fn ($component, $record) => $component->state($record->properties['attributes'] ?? [])),
                            ])
                            ->visible(fn ($record) => isset($record->properties['attributes']) || isset($record->properties['old']))
                            ->columns(2),
                        Forms\Components\Section::make('Raw Properties')
                            ->schema([
                                Forms\Components\Textarea::make('raw_props')
                                    ->hiddenLabel()
                                    ->rows(6)
                                    ->afterStateHydrated(fn ($component, $record) => $component->state(json_encode($record->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)))
                                    ->disabled(),
                            ])
                            ->visible(fn ($record) => !isset($record->properties['attributes']) && !isset($record->properties['old']) && !empty($record->properties))
                            ->collapsible(),
                    ])
            ])
            ->paginated([10, 25, 50, 100])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
