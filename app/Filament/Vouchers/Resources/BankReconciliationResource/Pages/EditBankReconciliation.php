<?php

namespace App\Filament\Vouchers\Resources\BankReconciliationResource\Pages;

use App\Filament\Vouchers\Resources\BankReconciliationResource;
use App\Models\AccountCode;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationLine;
use App\Models\Voucher;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;

class EditBankReconciliation extends EditRecord
{
    protected static string $resource = BankReconciliationResource::class;

    // ─────────────────────────────────────────────────────────────────────
    // Header actions
    // ─────────────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        /** @var BankReconciliation $record */
        $record = $this->getRecord();

        return [
            // ── Auto-Match ────────────────────────────────────────────────
            Actions\Action::make('auto_match')
                ->label('Auto-Match')
                ->icon('heroicon-m-sparkles')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Auto-Match Statement Lines')
                ->modalDescription('This will attempt to match unmatched statement lines to paid payment vouchers by cheque reference or amount+date. Existing matches will not be changed.')
                ->modalSubmitActionLabel('Run Auto-Match')
                ->action(function () use ($record) {
                    $count = $record->autoMatch();
                    Notification::make()
                        ->title("Auto-Match Complete: {$count} new match(es) found")
                        ->success()
                        ->send();
                    $this->refreshFormData([]);
                })
                ->visible(fn () => $record->status === 'draft'),

            // ── Add Statement Lines ───────────────────────────────────────
            Actions\Action::make('add_lines')
                ->label('Add Statement Lines')
                ->icon('heroicon-m-plus-circle')
                ->color('gray')
                ->slideOver()
                ->modalHeading('📄 Add Bank Statement Lines')
                ->modalDescription('Enter transactions exactly as they appear on your bank statement. Debit = money OUT of your bank account. Credit = money IN to your bank account. These are NOT accounting entries — they are just reference data for matching.')
                ->modalWidth('5xl')
                ->form([
                    Forms\Components\Repeater::make('new_lines')
                        ->label('Statement Lines')
                        ->schema(BankReconciliationResource::statementLinesRepeaterSchema())
                        ->minItems(1)
                        ->addActionLabel('+ Add Line')
                        ->reorderable(false),
                ])
                ->action(function (array $data) use ($record) {
                    $lines = $data['new_lines'] ?? [];
                    foreach ($lines as $line) {
                        BankReconciliationLine::create([
                            'bank_reconciliation_id' => $record->id,
                            'line_date'   => $line['line_date'],
                            'description' => $line['description'],
                            'reference'   => $line['reference'] ?? null,
                            'debit'       => (float) ($line['debit'] ?? 0),
                            'credit'      => (float) ($line['credit'] ?? 0),
                            'match_status' => 'unmatched',
                        ]);
                    }
                    Notification::make()
                        ->title(count($lines) . ' statement line(s) added successfully')
                        ->success()
                        ->send();
                    $this->refreshFormData([]);
                })
                ->visible(fn () => $record->status === 'draft'),

            // ── Mark Complete ─────────────────────────────────────────────
            Actions\Action::make('mark_complete')
                ->label('Mark Complete')
                ->icon('heroicon-m-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Complete This Reconciliation?')
                ->modalDescription(function () use ($record) {
                    $diff = $record->difference;
                    if (abs($diff) < 0.01) {
                        return 'The reconciliation is balanced. Mark it as completed? Once completed, only Admins can re-open it.';
                    }
                    return new HtmlString(
                        '<span class="text-danger-600 font-bold">⚠️ Warning: There is an unresolved difference of AED ' .
                        number_format(abs($diff), 2) .
                        '. Are you sure you want to mark this as completed?</span>'
                    );
                })
                ->action(function () use ($record) {
                    $record->update([
                        'status'       => 'completed',
                        'completed_by' => auth()->id(),
                        'completed_at' => now(),
                    ]);
                    Notification::make()->title('Reconciliation marked as completed')->success()->send();
                    $this->refreshFormData(['status']);
                })
                ->visible(fn () => $record->status === 'draft'),

            // ── Re-Open (Admin only) ──────────────────────────────────────
            Actions\Action::make('reopen')
                ->label('Re-Open')
                ->icon('heroicon-m-lock-open')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('This will re-open the completed reconciliation for editing. Only Admins can perform this action.')
                ->action(function () use ($record) {
                    $record->update([
                        'status'       => 'draft',
                        'completed_by' => null,
                        'completed_at' => null,
                    ]);
                    Notification::make()->title('Reconciliation re-opened for editing')->warning()->send();
                    $this->refreshFormData(['status']);
                })
                ->visible(fn () => $record->status === 'completed' && (auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Admin'))),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Form — Setup section only (header info, balances)
    // ─────────────────────────────────────────────────────────────────────

    protected function getFormSchema(): array
    {
        /** @var BankReconciliation $record */
        $record = $this->getRecord();

        $isLocked = $record->status === 'completed'
            && !(auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Admin'));

        return [
            // ── Summary Banner ────────────────────────────────────────────
            Forms\Components\Section::make('')
                ->schema([
                    Forms\Components\Placeholder::make('summary_banner')
                        ->label('')
                        ->content(function () use ($record) {
                            $record->refresh();

                            $openBal   = (float) $record->opening_balance;
                            $credits   = $record->total_credits;
                            $debits    = $record->total_debits;
                            $bookClose = $record->book_closing_balance;
                            $bankClose = (float) $record->closing_balance_per_bank;
                            $diff      = $record->difference;
                            $balanced  = $record->is_balanced;

                            $totalLines    = $record->lines()->count();
                            $matchedLines  = $record->matched_count;
                            $unmatchedLines = $record->unmatched_count;

                            $diffColor = $balanced ? '#15803d' : '#b91c1c';
                            $diffBg    = $balanced ? '#dcfce7' : '#fee2e2';
                            $diffLabel = $balanced ? '✓ BALANCED' : '⚠ DIFFERENCE: AED ' . number_format(abs($diff), 2);
                            $statusBadge = $record->status === 'completed'
                                ? "<span style='padding:3px 10px;border-radius:20px;background:#dcfce7;color:#15803d;font-size:11px;font-weight:700;'>✅ COMPLETED</span>"
                                : "<span style='padding:3px 10px;border-radius:20px;background:#fef9c3;color:#854d0e;font-size:11px;font-weight:700;'>✏ DRAFT</span>";

                            return new HtmlString("
                                <div style='display:grid;grid-template-columns:1fr 1fr;gap:16px;'>
                                    <!-- Left: Balances -->
                                    <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;'>
                                        <div style='font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;'>
                                            Balance Summary &nbsp; {$statusBadge}
                                        </div>
                                        <div style='display:grid;grid-template-columns:1fr 1fr;gap:4px 0;font-size:13px;'>
                                            <div style='color:#6b7280;'>Opening Balance:</div>
                                            <div style='text-align:right;font-family:monospace;'>AED " . number_format($openBal, 2) . "</div>
                                            <div style='color:#15803d;'>+ Total Credits (In):</div>
                                            <div style='text-align:right;font-family:monospace;color:#15803d;'>+ AED " . number_format($credits, 2) . "</div>
                                            <div style='color:#b91c1c;'>− Total Debits (Out):</div>
                                            <div style='text-align:right;font-family:monospace;color:#b91c1c;'>− AED " . number_format($debits, 2) . "</div>
                                            <div style='grid-column:span 2;margin:4px 0;border-top:1px dashed #cbd5e1;'></div>
                                            <div style='font-weight:700;'>Book Closing Balance:</div>
                                            <div style='text-align:right;font-family:monospace;font-weight:700;font-size:14px;'>AED " . number_format($bookClose, 2) . "</div>
                                            <div style='font-weight:700;'>Bank Statement Closing:</div>
                                            <div style='text-align:right;font-family:monospace;font-weight:700;font-size:14px;'>AED " . number_format($bankClose, 2) . "</div>
                                            <div style='grid-column:span 2;margin:4px 0;border-top:1px solid #cbd5e1;'></div>
                                            <div style='font-weight:700;'>Difference:</div>
                                            <div style='text-align:right;'>
                                                <span style='padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;background:{$diffBg};color:{$diffColor};'>{$diffLabel}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Right: Matching Progress -->
                                    <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;'>
                                        <div style='font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;'>Matching Progress</div>
                                        <div style='font-size:28px;font-weight:800;color:#0f172a;'>{$matchedLines} / {$totalLines}</div>
                                        <div style='color:#64748b;font-size:13px;margin-bottom:10px;'>statement lines matched</div>
                                        <div style='background:#e2e8f0;border-radius:100px;height:8px;overflow:hidden;'>
                                            <div style='background:#22c55e;height:100%;width:" . ($totalLines > 0 ? round($matchedLines / $totalLines * 100) : 0) . "%;border-radius:100px;transition:width 0.3s;'></div>
                                        </div>
                                        <div style='margin-top:8px;font-size:12px;color:#ef4444;'>{$unmatchedLines} unmatched line(s) remaining</div>
                                    </div>
                                </div>
                            ");
                        }),
                ])
                ->columnSpanFull(),

            // ── Account & Period Info ──────────────────────────────────────
            Forms\Components\Section::make('Reconciliation Details')
                ->columns(3)
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('account_code')
                        ->label('Bank / Account')
                        ->required()
                        ->searchable()
                        ->allowHtml()
                        ->getSearchResultsUsing(function (string $search) {
                            return AccountCode::where('is_active', true)
                                ->where(function ($q) use ($search) {
                                    $q->where('code', 'like', "%{$search}%")
                                      ->orWhere('name', 'like', "%{$search}%");
                                })
                                ->where('code', 'like', '1%')
                                ->limit(30)
                                ->get()
                                ->mapWithKeys(fn ($ac) => [
                                    $ac->code => "<span class='font-mono text-xs text-gray-500'>{$ac->code}</span> &nbsp; {$ac->name}"
                                ])
                                ->toArray();
                        })
                        ->getOptionLabelUsing(fn (?string $value) => $value
                            ? ($ac = AccountCode::where('code', $value)->first())
                                ? "{$ac->code} — {$ac->name}"
                                : $value
                            : null
                        )
                        ->disabled($isLocked),

                    Forms\Components\Hidden::make('account_name'),

                    Forms\Components\DatePicker::make('period_from')
                        ->label('Period From')
                        ->required()
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->disabled($isLocked),

                    Forms\Components\DatePicker::make('period_to')
                        ->label('Period To')
                        ->required()
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->disabled($isLocked),

                    Forms\Components\TextInput::make('opening_balance')
                        ->label('Opening Balance (Bank)')
                        ->numeric()
                        ->prefix('AED')
                        ->required()
                        ->disabled($isLocked),

                    Forms\Components\TextInput::make('closing_balance_per_bank')
                        ->label('Closing Balance (Bank)')
                        ->numeric()
                        ->prefix('AED')
                        ->required()
                        ->disabled($isLocked),

                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->columnSpanFull()
                        ->disabled($isLocked),
                ]),

            // ── Statement Lines Workspace ─────────────────────────────────
            Forms\Components\Section::make('📄 Bank Statement Side — Enter lines from your bank statement')
                ->description('These are transactions from your physical bank statement (what the BANK recorded). Enter each line manually. Match each one to a Payment Voucher from the Books side below. No accounting entries are created here — this is reference only.')
                ->schema([
                    Forms\Components\Placeholder::make('lines_table')
                        ->label('')
                        ->content(function () use ($record) {
                            $record->refresh();
                            $lines = $record->lines()->with('voucher')->get();

                            if ($lines->isEmpty()) {
                                return new HtmlString("
                                    <div style='text-align:center;padding:40px;color:#94a3b8;'>
                                        <div style='font-size:40px;margin-bottom:8px;'>📄</div>
                                        <div style='font-size:15px;font-weight:600;'>No bank statement lines yet</div>
                                        <div style='font-size:13px;margin-top:4px;'>Click <strong>Add Statement Lines</strong> above and enter transactions from your physical bank statement PDF/printout.</div>
                                        <div style='font-size:12px;color:#c084fc;margin-top:8px;'>💡 Tip: Then click <strong>Auto-Match</strong> to automatically link them to your payment vouchers below.</div>
                                    </div>
                                ");
                            }

                            $rows = '';
                            foreach ($lines as $line) {
                                $matchBg = match($line->match_status) {
                                    'matched' => '#f0fdf4',
                                    'manual'  => '#eff6ff',
                                    default   => '#fff',
                                };
                                $matchBorder = match($line->match_status) {
                                    'matched' => '2px solid #86efac',
                                    'manual'  => '2px solid #93c5fd',
                                    default   => '1px solid #e2e8f0',
                                };
                                $matchIcon = match($line->match_status) {
                                    'matched' => '✅',
                                    'manual'  => '🔧',
                                    default   => '⚠️',
                                };

                                $voucherInfo = '';
                                if ($line->voucher) {
                                    $voucherInfo = "<div style='font-size:11px;color:#6366f1;margin-top:2px;'>→ #{$line->voucher->voucher_number} ({$line->voucher->payee})</div>";
                                }

                                $debitStr  = $line->debit  > 0 ? '<span style="color:#b91c1c;">AED ' . number_format($line->debit, 2) . '</span>' : '—';
                                $creditStr = $line->credit > 0 ? '<span style="color:#15803d;">AED ' . number_format($line->credit, 2) . '</span>' : '—';

                                $rows .= "
                                    <tr style='background:{$matchBg};border-left:{$matchBorder};'>
                                        <td style='padding:8px 12px;font-size:13px;white-space:nowrap;'>{$line->line_date->format('d M Y')}</td>
                                        <td style='padding:8px 12px;font-size:13px;'>
                                            <div style='font-weight:600;'>" . e($line->description) . "</div>
                                            {$voucherInfo}
                                        </td>
                                        <td style='padding:8px 12px;font-size:12px;font-family:monospace;color:#475569;'>" . e($line->reference ?? '—') . "</td>
                                        <td style='padding:8px 12px;font-size:13px;text-align:right;'>{$debitStr}</td>
                                        <td style='padding:8px 12px;font-size:13px;text-align:right;'>{$creditStr}</td>
                                        <td style='padding:8px 12px;font-size:12px;text-align:center;'>{$matchIcon} " . ucfirst($line->match_status) . "</td>
                                    </tr>
                                ";
                            }

                            $totalDebit  = $lines->sum('debit');
                            $totalCredit = $lines->sum('credit');

                            return new HtmlString("
                                <div style='overflow-x:auto;'>
                                    <table style='width:100%;border-collapse:separate;border-spacing:0 4px;font-family:inherit;'>
                                        <thead>
                                            <tr style='background:#f1f5f9;'>
                                                <th style='padding:8px 12px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;'>Date</th>
                                                <th style='padding:8px 12px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;'>Description / Matched Voucher</th>
                                                <th style='padding:8px 12px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;'>Ref / Cheque #</th>
                                                <th style='padding:8px 12px;text-align:right;font-size:11px;font-weight:700;color:#b91c1c;text-transform:uppercase;'>Debit (Out)</th>
                                                <th style='padding:8px 12px;text-align:right;font-size:11px;font-weight:700;color:#15803d;text-transform:uppercase;'>Credit (In)</th>
                                                <th style='padding:8px 12px;text-align:center;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;'>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {$rows}
                                        </tbody>
                                        <tfoot>
                                            <tr style='background:#f8fafc;font-weight:700;border-top:2px solid #cbd5e1;'>
                                                <td colspan='3' style='padding:10px 12px;font-size:13px;'>TOTALS</td>
                                                <td style='padding:10px 12px;text-align:right;color:#b91c1c;font-family:monospace;'>AED " . number_format($totalDebit, 2) . "</td>
                                                <td style='padding:10px 12px;text-align:right;color:#15803d;font-family:monospace;'>AED " . number_format($totalCredit, 2) . "</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            ");
                        }),
                ]),

            // ── Unreconciled Vouchers Panel ───────────────────────────────
            Forms\Components\Section::make('📒 Books Side — Payment Vouchers in your System (Reference Only)')
                ->description(function () use ($record) {
                    return "These are paid Payment Vouchers already recorded in your books for account {$record->account_code} within this period. "
                         . "This is READ-ONLY reference data — no entries are created here. "
                         . "Match each bank statement line above to the corresponding voucher below using Auto-Match or Manage Lines.";
                })
                ->collapsed()
                ->schema([
                    Forms\Components\Placeholder::make('unreconciled_vouchers')
                        ->label('')
                        ->content(function () use ($record) {
                            $record->refresh();

                            // IDs already matched
                            $matchedVoucherIds = $record->lines()
                                ->whereNotNull('voucher_id')
                                ->pluck('voucher_id')
                                ->toArray();

                            // Get paid payment vouchers for this account in period
                            $vouchers = Voucher::whereIn('type', ['payment', 'bank_encashment'])
                                ->where('status', 'paid')
                                ->whereNotIn('id', $matchedVoucherIds)
                                ->where(function ($q) use ($record) {
                                    $q->whereRaw(
                                        "JSON_SEARCH(multiple_payments, 'one', ?) IS NOT NULL",
                                        [$record->account_code]
                                    )->orWhere('bank', $record->account_code);
                                })
                                ->whereBetween(\Illuminate\Support\Facades\DB::raw('DATE(updated_at)'), [
                                    $record->period_from,
                                    $record->period_to,
                                ])
                                ->get();

                            if ($vouchers->isEmpty()) {
                                return new HtmlString("
                                    <div style='text-align:center;padding:20px;color:#94a3b8;font-size:13px;'>
                                        ✅ All payment vouchers for this account and period have been matched.
                                    </div>
                                ");
                            }

                            $rows = '';
                            foreach ($vouchers as $v) {
                                $payments = $v->multiple_payments ?? [['cheque_no' => $v->cheque_no, 'amount' => $v->amount, 'bank' => $v->bank]];
                                foreach ($payments as $p) {
                                    if (($p['bank'] ?? '') !== $record->account_code) continue;
                                    $rows .= "
                                        <tr style='border-bottom:1px solid #f1f5f9;'>
                                            <td style='padding:7px 10px;font-size:12px;font-family:monospace;font-weight:700;color:#6366f1;'>#{$v->voucher_number}</td>
                                            <td style='padding:7px 10px;font-size:12px;'>" . e($v->payee) . "</td>
                                            <td style='padding:7px 10px;font-size:12px;font-family:monospace;'>" . e($p['cheque_no'] ?? '—') . "</td>
                                            <td style='padding:7px 10px;font-size:12px;'>" . ($p['cheque_date'] ? \Carbon\Carbon::parse($p['cheque_date'])->format('d M Y') : '—') . "</td>
                                            <td style='padding:7px 10px;font-size:13px;font-weight:700;text-align:right;color:#b91c1c;'>AED " . number_format((float)($p['amount'] ?? $v->amount), 2) . "</td>
                                        </tr>
                                    ";
                                }
                            }

                            return new HtmlString("
                                <div style='overflow-x:auto;'>
                                    <table style='width:100%;border-collapse:collapse;font-family:inherit;'>
                                        <thead style='background:#fef9c3;'>
                                            <tr>
                                                <th style='padding:8px 10px;text-align:left;font-size:11px;color:#92400e;text-transform:uppercase;'>Voucher #</th>
                                                <th style='padding:8px 10px;text-align:left;font-size:11px;color:#92400e;text-transform:uppercase;'>Payee</th>
                                                <th style='padding:8px 10px;text-align:left;font-size:11px;color:#92400e;text-transform:uppercase;'>Cheque #</th>
                                                <th style='padding:8px 10px;text-align:left;font-size:11px;color:#92400e;text-transform:uppercase;'>Date</th>
                                                <th style='padding:8px 10px;text-align:right;font-size:11px;color:#92400e;text-transform:uppercase;'>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>{$rows}</tbody>
                                    </table>
                                </div>
                                <p style='font-size:12px;color:#94a3b8;margin-top:8px;'>These vouchers are in your books but have no matching bank statement line. Use <strong>Add Statement Lines</strong> + <strong>Auto-Match</strong> or match them manually.</p>
                            ");
                        }),
                ]),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Save / Mutation hooks
    // ─────────────────────────────────────────────────────────────────────

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // If account_code changed, refresh cached account_name
        if (!empty($data['account_code'])) {
            $ac = AccountCode::where('code', $data['account_code'])->first();
            if ($ac) {
                $data['account_name'] = $ac->name;
            }
        }
        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Per-line actions (match, unmatch, delete) rendered as table row actions
    // These are exposed via separate Action widgets on the lines table
    // ─────────────────────────────────────────────────────────────────────

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    /**
     * We override the form method so we can also inject the line-level
     * actions (match/unmatch/delete/edit) on the page as additional header actions.
     */
    protected function getFooterActions(): array
    {
        /** @var BankReconciliation $record */
        $record = $this->getRecord();

        if ($record->status === 'completed' &&
            !(auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Admin'))) {
            return [];
        }

        return [
            Actions\Action::make('manage_lines')
                ->label('Manage Lines')
                ->icon('heroicon-m-list-bullet')
                ->color('gray')
                ->slideOver()
                ->modalHeading('Manage Statement Lines')
                ->modalWidth('6xl')
                ->form(function () use ($record) {
                    $record->refresh();
                    $lines = $record->lines()->with('voucher')->get();

                    // Build a form with per-line match actions
                    $schema = [];

                    foreach ($lines as $line) {
                        $matchStatus = $line->match_status;
                        $voucherLabel = $line->voucher
                            ? "→ #{$line->voucher->voucher_number}"
                            : '';

                        $schema[] = Forms\Components\Section::make(
                            $line->line_date->format('d M Y') . ' | ' .
                            $line->description .
                            ($line->reference ? ' [' . $line->reference . ']' : '') .
                            ' | ' . ($line->debit > 0 ? 'Debit AED ' . number_format($line->debit, 2) : 'Credit AED ' . number_format($line->credit, 2)) .
                            ' | ' . ucfirst($matchStatus) . $voucherLabel
                        )
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make("line_{$line->id}_description")
                                    ->label('Description')
                                    ->default($line->description)
                                    ->disabled(),

                                Forms\Components\TextInput::make("line_{$line->id}_reference")
                                    ->label('Reference')
                                    ->default($line->reference ?? ''),

                                Forms\Components\Select::make("line_{$line->id}_match_status")
                                    ->label('Match Status')
                                    ->options([
                                        'unmatched' => '⚠️ Unmatched',
                                        'matched'   => '✅ Matched',
                                        'manual'    => '🔧 Manual',
                                    ])
                                    ->default($matchStatus),

                                Forms\Components\Select::make("line_{$line->id}_voucher_id")
                                    ->label('Linked Voucher')
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search) use ($record) {
                                        return Voucher::whereIn('type', ['payment', 'bank_encashment'])
                                            ->where('status', 'paid')
                                            ->where(function ($q) use ($record) {
                                                $q->whereRaw("JSON_SEARCH(multiple_payments, 'one', ?) IS NOT NULL", [$record->account_code])
                                                  ->orWhere('bank', $record->account_code);
                                            })
                                            ->where(function ($q) use ($search) {
                                                $q->where('voucher_number', 'like', "%{$search}%")
                                                  ->orWhere('payee', 'like', "%{$search}%")
                                                  ->orWhere('cheque_no', 'like', "%{$search}%");
                                            })
                                            ->limit(20)
                                            ->get()
                                            ->mapWithKeys(fn ($v) => [
                                                $v->id => "#{$v->voucher_number} — {$v->payee} — AED " . number_format($v->amount, 2)
                                            ])
                                            ->toArray();
                                    })
                                    ->getOptionLabelUsing(fn ($value) => $value
                                        ? ($v = Voucher::find($value))
                                            ? "#{$v->voucher_number} — {$v->payee}"
                                            : $value
                                        : null
                                    )
                                    ->default($line->voucher_id)
                                    ->columnSpan(2),

                                Forms\Components\Hidden::make("line_{$line->id}_id")
                                    ->default($line->id),
                            ]),
                        ])
                        ->compact();
                    }

                    return $schema;
                })
                ->action(function (array $data) use ($record) {
                    $record->refresh();
                    $lines = $record->lines()->get();

                    foreach ($lines as $line) {
                        $updates = [];
                        $ref = $data["line_{$line->id}_reference"] ?? null;
                        $status = $data["line_{$line->id}_match_status"] ?? $line->match_status;
                        $voucherId = $data["line_{$line->id}_voucher_id"] ?? $line->voucher_id;

                        $updates['reference'] = $ref;
                        $updates['match_status'] = $status;
                        $updates['voucher_id'] = $voucherId ?: null;

                        $line->update($updates);
                    }

                    Notification::make()->title('Statement lines updated')->success()->send();
                    $this->refreshFormData([]);
                })
                ->visible(fn () => $record->status === 'draft' ||
                    (auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Admin'))),
        ];
    }
}
