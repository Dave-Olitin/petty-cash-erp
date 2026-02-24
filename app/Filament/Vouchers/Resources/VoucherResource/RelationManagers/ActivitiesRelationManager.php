<?php

namespace App\Filament\Vouchers\Resources\VoucherResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Arr;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';
    protected static ?string $title = 'Edit History';

    public function form(Form $form): Form
    {
        return $form->schema([]); // Read-only
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->width('160px'),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('By')
                    ->default('System')
                    ->badge()
                    ->color('gray')
                    ->width('140px'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default   => 'gray',
                    })
                    ->width('100px'),

                Tables\Columns\TextColumn::make('properties')
                    ->label('Changes')
                    ->formatStateUsing(function ($state, $record): string {
                        // Spatie stores properties as a Illuminate\Support\Collection or array
                        // It can also arrive as a raw string — be defensive
                        $props = $record->properties;

                        if ($props instanceof \Spatie\Activitylog\Contracts\Activity) {
                            $props = $props->properties->toArray();
                        } elseif (is_object($props) && method_exists($props, 'toArray')) {
                            $props = $props->toArray();
                        } elseif (is_string($props)) {
                            $props = json_decode($props, true) ?? [];
                        } elseif (!is_array($props)) {
                            return '—';
                        }

                        $old = Arr::get($props, 'old', []);
                        $new = Arr::get($props, 'attributes', []);

                        // Fallback: if neither key exists, treat entire props as "new"
                        if (empty($old) && empty($new)) {
                            $new = $props;
                        }

                        if (!is_array($new) || empty($new)) return '—';

                        if (empty($old)) {
                            // Created: list fields set
                            $lines = [];
                            foreach ($new as $key => $val) {
                                if (in_array($key, ['created_at', 'updated_at', 'deleted_at'])) continue;
                                $label   = ucwords(str_replace('_', ' ', $key));
                                $lines[] = "{$label}: " . (is_null($val) ? '—' : (is_array($val) ? json_encode($val) : $val));
                            }
                            return implode("\n", $lines) ?: '—';
                        }

                        // Updated: show changed fields only
                        $lines = [];
                        foreach ($new as $key => $newVal) {
                            if (in_array($key, ['created_at', 'updated_at', 'deleted_at'])) continue;
                            $oldVal = Arr::get($old, $key, '—');
                            if ($oldVal == $newVal) continue;
                            $label   = ucwords(str_replace('_', ' ', $key));
                            $lines[] = "{$label}: {$oldVal} → {$newVal}";
                        }
                        return empty($lines) ? 'No field changes.' : implode("\n", $lines);
                    })
                    ->wrap()
                    ->html(false)
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->color('gray'),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('view_diff')
                    ->label('Full Diff')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->modalHeading('Change Details')
                    ->modalContent(function ($record): \Illuminate\Contracts\View\View {
                        $props = is_array($record->properties)
                            ? $record->properties
                            : json_decode(json_encode($record->properties), true);

                        $old = \Illuminate\Support\Arr::get($props, 'old', []);
                        $new = \Illuminate\Support\Arr::get($props, 'attributes', $props);

                        return view('filament.vouchers.modals.activity-diff', compact('old', 'new', 'record'));
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->bulkActions([]);
    }
}
