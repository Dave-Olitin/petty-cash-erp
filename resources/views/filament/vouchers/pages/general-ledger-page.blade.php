<x-filament-panels::page>
    {{-- Ongoing Testing Alert --}}
    <div class="bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 rounded-xl p-4 flex items-start gap-3 shadow-sm mb-6">
        <x-filament::icon icon="heroicon-o-beaker" class="h-5 w-5 text-amber-600 dark:text-amber-400 mt-0.5" />
        <div>
            <h3 class="text-sm font-bold text-amber-800 dark:text-amber-400">Ongoing Testing & Auditing Phase</h3>
            <p class="text-xs text-amber-700 dark:text-amber-500 mt-1">
                This module is currently in active beta testing. Accounting reconciliations, formulas, and exports are subject to audit verification before final production sign-off. Please report any discrepancies.
            </p>
        </div>
    </div>

    {{-- ── Filters ── --}}
    <form wire:submit="updateReport">
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow border border-gray-200 dark:border-gray-800 p-6">
            {{ $this->form }}
            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="text-xs text-gray-400 italic">
                    * Adjust filters and click generate to refresh the report data.
                </div>
                <x-filament::button type="submit" icon="heroicon-m-magnifying-glass" wire:loading.attr="disabled" size="lg" class="px-8 shadow-md">
                    <span wire:loading.remove>Generate Ledger</span>
                    <span wire:loading>Processing...</span>
                </x-filament::button>
            </div>
        </div>
    </form>

    {{-- ── Ledger Table ── --}}
    <div class="mt-8 bg-white dark:bg-gray-900 rounded-xl shadow border border-gray-200 dark:border-gray-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider text-gray-500 bg-gray-50/50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                        <th class="px-6 py-4 font-bold">Date</th>
                        <th class="px-6 py-4 font-bold">JE Ref</th>
                        <th class="px-6 py-4 font-bold">Src</th>
                        <th class="px-6 py-4 font-bold">Voucher #</th>
                        <th class="px-6 py-4 text-right font-bold">PCV Amount</th>
                        <th class="px-6 py-4 font-bold">Payee</th>
                        <th class="px-6 py-4 font-bold">Branch</th>
                        <th class="px-6 py-4 text-right font-bold">Debit (AED)</th>
                        <th class="px-6 py-4 text-right font-bold">Credit (AED)</th>
                        <th class="px-6 py-4 text-right font-bold">Balance</th>
                    </tr>
                </thead>

                @forelse($this->ledgerGroups as $index => $group)
                    @php
                        $account = $group['account'];
                        $rows    = $group['rows'];
                        $totalDr = $group['total_debit'];
                        $totalCr = $group['total_credit'];
                        $closing = $group['closing_balance'];
                        $isDebitNormal = $account?->normal_balance === 'debit';
                        $closingLabel  = $closing >= 0
                            ? ($isDebitNormal ? 'DR' : 'CR')
                            : ($isDebitNormal ? 'CR' : 'DR');
                    @endphp

                    <tbody x-data="{ isOpen: false }" class="border-b border-gray-200 dark:border-gray-800 last:border-b-0">
                        {{-- Account Group Header Row --}}
                        <tr @click="isOpen = !isOpen" class="cursor-pointer bg-gray-100/80 dark:bg-gray-800/80 hover:bg-gray-200/60 dark:hover:bg-gray-700/60 transition-colors border-y border-gray-200 dark:border-gray-700">
                            <td colspan="7" class="px-6 py-3">
                                <div class="flex items-center gap-3">
                                    <x-filament::icon
                                        x-bind:class="isOpen ? 'rotate-180' : ''"
                                        icon="heroicon-m-chevron-down"
                                        class="h-4 w-4 text-gray-500 transition-transform duration-200"
                                    />
                                    <span class="font-mono text-xs font-bold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30 px-2 py-0.5 rounded border border-primary-200 dark:border-primary-800">
                                        {{ $account?->code ?? 'N/A' }}
                                    </span>
                                    <span class="font-extrabold text-gray-900 dark:text-white uppercase tracking-tight">
                                        {{ $account?->name ?? 'Unknown Account' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <span class="font-mono font-bold text-gray-900 dark:text-white">{{ number_format($totalDr, 2) }}</span>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <span class="font-mono font-bold text-gray-900 dark:text-white">{{ number_format($totalCr, 2) }}</span>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <span class="font-mono font-black text-primary-600 dark:text-primary-400">
                                    {{ number_format(abs($closing), 2) }}
                                    <span class="text-[9px] font-normal opacity-70 ml-0.5">{{ $closingLabel }}</span>
                                </span>
                            </td>
                        </tr>

                        {{-- Transaction Detail Rows --}}
                        @foreach($rows as $line)
                            @php
                                $bal = $line->running_balance;
                                $balLabel = $bal >= 0
                                    ? ($isDebitNormal ? 'DR' : 'CR')
                                    : ($isDebitNormal ? 'CR' : 'DR');
                                $isJeSource = $line->source === 'je';
                            @endphp
                            <tr x-show="isOpen" class="hover:bg-primary-50/30 dark:hover:bg-primary-900/10 transition-colors border-b border-gray-50 dark:border-gray-800/50 last:border-b-0 {{ !empty($line->is_info_only) ? 'opacity-60 italic bg-gray-50/50 dark:bg-gray-800/20' : '' }}">
                                {{-- Date --}}
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap pl-12 relative">
                                    <div class="absolute left-8 top-0 bottom-0 w-px bg-gray-200 dark:bg-gray-700"></div>
                                    {{ optional($line->date)->format('d/m/Y') ?? '—' }}
                                </td>

                                {{-- JE Ref --}}
                                <td class="px-6 py-3">
                                    @if($line->je_ref)
                                        <a href="{{ \App\Filament\Vouchers\Resources\JournalEntryResource::getUrl('view', ['record' => $line->je_id]) }}"
                                           class="font-mono text-xs font-bold text-primary-600 dark:text-primary-400 hover:underline">
                                            {{ $line->je_ref }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                {{-- Source badge --}}
                                <td class="px-6 py-3">
                                    @if($isJeSource)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                            JE
                                        </span>
                                    @elseif(!empty($line->is_info_only))
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-600 dark:bg-gray-800/40 dark:text-gray-400 border border-gray-200 dark:border-gray-700" title="This voucher has a linked Journal Entry. Excluded from ledger balance to prevent double-counting.">
                                            VCH (INFO)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                            VCH
                                        </span>
                                    @endif
                                </td>

                                {{-- Voucher # --}}
                                <td class="px-6 py-3">
                                    @if($line->voucher_id)
                                        <a href="{{ \App\Filament\Vouchers\Resources\VoucherResource::getUrl('view', ['record' => $line->voucher_id]) }}"
                                           class="text-xs text-gray-500 dark:text-gray-400 hover:underline">
                                            {{ $line->voucher_number ?? '—' }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                {{-- PCV Amount --}}
                                <td class="px-6 py-3 text-right font-mono text-xs text-gray-500 dark:text-gray-400">
                                    @if($line->voucher_type === 'petty_cash' && $line->voucher_amount)
                                        {{ number_format($line->voucher_amount, 2) }}
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                {{-- Payee --}}
                                <td class="px-6 py-3 text-xs text-gray-500 dark:text-gray-400 max-w-[150px] truncate" title="{{ $line->payee }}">
                                    {{ $line->payee ?: '—' }}
                                </td>

                                {{-- Branch --}}
                                <td class="px-6 py-3 text-xs text-gray-500 dark:text-gray-400 uppercase italic">
                                    {{ $line->branch ?: '—' }}
                                </td>

                                {{-- Debit --}}
                                <td class="px-6 py-3 text-right font-mono text-xs {{ !empty($line->is_info_only) ? 'text-gray-400/70 dark:text-gray-500/70 line-through' : ($line->debit > 0 ? 'text-gray-900 dark:text-white font-bold' : 'text-gray-300 dark:text-gray-700') }}">
                                    {{ $line->debit > 0 ? number_format($line->debit, 2) : '—' }}
                                </td>

                                {{-- Credit --}}
                                <td class="px-6 py-3 text-right font-mono text-xs {{ !empty($line->is_info_only) ? 'text-gray-400/70 dark:text-gray-500/70 line-through' : ($line->credit > 0 ? 'text-gray-900 dark:text-white font-bold' : 'text-gray-300 dark:text-gray-700') }}">
                                    {{ $line->credit > 0 ? number_format($line->credit, 2) : '—' }}
                                </td>

                                {{-- Running Balance --}}
                                <td class="px-6 py-3 text-right font-mono text-xs text-gray-900 dark:text-white">
                                    {{ number_format(abs($bal), 2) }}
                                    <span class="text-[9px] font-normal text-gray-400 ml-0.5">{{ $balLabel }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="10" class="text-center py-24 text-gray-400 dark:text-gray-600 bg-gray-50/20">
                                <x-filament::icon icon="heroicon-o-book-open" class="mx-auto h-16 w-16 mb-4 opacity-20" />
                                <p class="text-base font-medium">No transactions found.</p>
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </div>
    </div>

    {{-- ── Redesigned Grand Total Summary (Reliable Spacing) ── --}}
    @if($this->ledgerGroups->isNotEmpty())
        @php
            $gDebit = $this->grandTotalDebit;
            $gCredit = $this->grandTotalCredit;
            $gDiff = $gDebit - $gCredit;
        @endphp
        <div class="mt-6 bg-white dark:bg-gray-900 rounded-xl shadow border border-gray-200 dark:border-gray-800 p-1 divide-x divide-gray-100 dark:divide-gray-800">
            <div class="grid grid-cols-1 md:grid-cols-3">
                {{-- Total Debit --}}
                <div class="px-6 py-4 flex items-center justify-between">
                    <span class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">Total Debit</span>
                    <span class="font-mono text-lg font-bold text-gray-900 dark:text-white">{{ number_format($gDebit, 2) }}</span>
                </div>
                
                {{-- Total Credit --}}
                <div class="px-6 py-4 flex items-center justify-between border-t md:border-t-0 md:border-l border-gray-100 dark:border-gray-800">
                    <span class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">Total Credit</span>
                    <span class="font-mono text-lg font-bold text-gray-900 dark:text-white">{{ number_format($gCredit, 2) }}</span>
                </div>

                {{-- Difference --}}
                <div class="px-6 py-4 flex items-center justify-between bg-primary-50/50 dark:bg-primary-900/10 border-t md:border-t-0 md:border-l border-gray-100 dark:border-gray-800">
                    <span class="text-[10px] uppercase tracking-widest text-primary-600 dark:text-primary-400 font-bold">Difference</span>
                    <span class="font-mono text-lg font-black text-primary-600 dark:text-primary-400">
                        {{ number_format(abs($gDiff), 2) }}
                        <span class="text-xs font-normal opacity-70 ml-0.5">{{ $gDiff >= 0 ? 'DR' : 'CR' }}</span>
                    </span>
                </div>
            </div>
        </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════
         PCV LIQUIDATION RECONCILIATION — Side-by-side with GL
         Shows the variance between PCV advances and actual employee spending.
    ════════════════════════════════════════════════════════════════════ --}}
    @php
        $liqRows   = $this->liquidationSummary;
        $liqTotals = $this->liquidationTotals;
    @endphp

    @if($liqRows->isNotEmpty())
        {{-- Section Header --}}
        <div class="mt-12 flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-200 dark:border-gray-800 pb-3">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-clipboard-document-check" class="h-5 w-5 text-primary-500" />
                <h2 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-tight">
                    PCV Liquidation Reconciliation
                </h2>
            </div>
            
            {{-- Status Pills (Aligned Right in Header) --}}
            <div class="flex flex-wrap gap-2 text-[10px]">
                @if($liqTotals['pending_count'] > 0)
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full font-bold bg-warning-50 text-warning-700 dark:bg-warning-950/30 dark:text-warning-400 border border-warning-200 dark:border-warning-900/50">
                        ⏳ {{ $liqTotals['pending_count'] }} Pending
                    </span>
                @endif
                @if($liqTotals['complete_count'] > 0)
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full font-bold bg-success-50 text-success-700 dark:bg-success-950/30 dark:text-success-400 border border-success-200 dark:border-success-900/50">
                        ✅ {{ $liqTotals['complete_count'] }} Complete
                    </span>
                @endif
                @if($liqTotals['short_count'] > 0)
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full font-bold bg-danger-50 text-danger-700 dark:bg-danger-950/30 dark:text-danger-400 border border-danger-200 dark:border-danger-900/50">
                        🔴 {{ $liqTotals['short_count'] }} Short
                    </span>
                @endif
                @if($liqTotals['excess_count'] > 0)
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full font-bold bg-info-50 text-info-700 dark:bg-info-950/30 dark:text-info-400 border border-info-200 dark:border-info-900/50">
                        🔵 {{ $liqTotals['excess_count'] }} Excess
                    </span>
                @endif
            </div>
        </div>

        {{-- KPI Summary Row (Matching GL Total Block Style) --}}
        @php
            $netVar = $liqTotals['total_variance'];
            $varBgClass = abs($netVar) <= 0.01
                ? 'bg-success-50/30 dark:bg-success-950/10'
                : ($netVar < 0 ? 'bg-danger-50/30 dark:bg-danger-950/10' : 'bg-info-50/30 dark:bg-info-950/10');
            $varTextClass = abs($netVar) <= 0.01
                ? 'text-success-600 dark:text-success-400'
                : ($netVar < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-info-600 dark:text-info-400');
            $varLabel = abs($netVar) <= 0.01
                ? 'DR/CR Matched'
                : ($netVar < 0 ? 'Net Short' : 'Net Excess');
        @endphp

        <div class="mt-4 bg-white dark:bg-gray-900 rounded-xl shadow border border-gray-200 dark:border-gray-800 p-1 divide-x divide-gray-100 dark:divide-gray-800">
            <div class="grid grid-cols-1 md:grid-cols-4">
                {{-- Total Advanced --}}
                <div class="px-6 py-4 flex items-center justify-between">
                    <span class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">Total Advanced</span>
                    <span class="font-mono text-lg font-bold text-gray-900 dark:text-white">
                        {{ number_format($liqTotals['total_advanced'], 2) }}
                    </span>
                </div>

                {{-- Total Spent --}}
                <div class="px-6 py-4 flex items-center justify-between border-t md:border-t-0 md:border-l border-gray-100 dark:border-gray-800">
                    <span class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">Total Spent</span>
                    <span class="font-mono text-lg font-bold text-gray-900 dark:text-white">
                        {{ number_format($liqTotals['total_spent'], 2) }}
                    </span>
                </div>

                {{-- Total Returned --}}
                <div class="px-6 py-4 flex items-center justify-between border-t md:border-t-0 md:border-l border-gray-100 dark:border-gray-800">
                    <span class="text-[10px] uppercase tracking-widest text-success-500 font-bold">Total Returned</span>
                    <span class="font-mono text-lg font-bold text-success-600 dark:text-success-400">
                        {{ number_format($liqTotals['total_returned'], 2) }}
                    </span>
                </div>

                {{-- Net Variance --}}
                <div class="px-6 py-4 flex items-center justify-between {{ $varBgClass }} border-t md:border-t-0 md:border-l border-gray-100 dark:border-gray-800">
                    <span class="text-[10px] uppercase tracking-widest {{ $varTextClass }} font-bold">Net Variance</span>
                    <span class="font-mono text-lg font-black {{ $varTextClass }}">
                        {{ $netVar >= 0 ? '+' : '' }}{{ number_format($netVar, 2) }}
                        <span class="text-[9px] font-normal opacity-75 block text-right mt-0.5">{{ $varLabel }}</span>
                    </span>
                </div>
            </div>
        </div>

        {{-- Detailed Liquidation Table --}}
        <div class="mt-4 bg-white dark:bg-gray-900 rounded-xl shadow border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left border-collapse">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider text-gray-500 bg-gray-50/50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                            <th class="px-6 py-4 font-bold">Voucher #</th>
                            <th class="px-6 py-4 font-bold">Payee / Employee</th>
                            <th class="px-6 py-4 text-right font-bold">Advanced (AED)</th>
                            <th class="px-6 py-4 text-right font-bold">Spent (AED)</th>
                            <th class="px-6 py-4 text-right font-bold">Returned (AED)</th>
                            <th class="px-6 py-4 text-right font-bold">Variance (AED)</th>
                            <th class="px-6 py-4 font-bold">Status</th>
                            <th class="px-6 py-4 font-bold">Due / Settled</th>
                            <th class="px-6 py-4 font-bold">Linked JE</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-800/60">
                        @foreach($liqRows as $liq)
                            @php
                                $variance = $liq->variance;
                                $isExact  = abs($variance) <= 0.01;
                                $varColor = $isExact
                                    ? 'text-success-600 dark:text-success-400'
                                    : ($variance < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-info-600 dark:text-info-400');

                                $statusBg = match($liq->status) {
                                    'complete' => 'bg-success-100 text-success-700 dark:bg-success-900/40 dark:text-success-300',
                                    'short'    => 'bg-danger-100 text-danger-700 dark:bg-danger-900/40 dark:text-danger-300',
                                    'excess'   => 'bg-info-100 text-info-700 dark:bg-info-900/40 dark:text-info-300',
                                    default    => 'bg-warning-100 text-warning-700 dark:bg-warning-900/40 dark:text-warning-300',
                                };

                                // JE is already eager-loaded via voucher.journalEntries
                                $linkedJe = $liq->voucher?->journalEntries?->first() ?? null;
                            @endphp
                            <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-800/40 transition-colors">
                                {{-- Voucher # --}}
                                <td class="px-6 py-3">
                                    @if($liq->voucher)
                                        <a href="{{ \App\Filament\Vouchers\Resources\VoucherResource::getUrl('view', ['record' => $liq->voucher]) }}"
                                           class="font-mono text-xs font-bold text-primary-600 dark:text-primary-400 hover:underline">
                                            {{ $liq->voucher->voucher_number }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                {{-- Payee --}}
                                <td class="px-6 py-3 text-xs text-gray-700 dark:text-gray-300 max-w-[160px] truncate" title="{{ $liq->voucher?->payee }}">
                                    {{ $liq->voucher?->payee ?? '—' }}
                                </td>

                                {{-- Advanced --}}
                                <td class="px-6 py-3 text-right font-mono text-xs font-bold text-gray-900 dark:text-white">
                                    {{ number_format($liq->voucher?->amount ?? 0, 2) }}
                                </td>

                                {{-- Spent --}}
                                <td class="px-6 py-3 text-right font-mono text-xs text-gray-700 dark:text-gray-300">
                                    {{ number_format($liq->amount_spent, 2) }}
                                </td>

                                {{-- Returned --}}
                                <td class="px-6 py-3 text-right font-mono text-xs text-success-600 dark:text-success-400">
                                    {{ (float)$liq->amount_returned > 0 ? number_format($liq->amount_returned, 2) : '—' }}
                                </td>

                                {{-- Variance --}}
                                <td class="px-6 py-3 text-right font-mono text-xs font-bold {{ $varColor }}">
                                    @if($isExact)
                                        <span title="Exact">✅ —</span>
                                    @else
                                        {{ $variance > 0 ? '+' : '' }}{{ number_format($variance, 2) }}
                                    @endif
                                </td>

                                {{-- Status --}}
                                <td class="px-6 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold {{ $statusBg }}">
                                        {{ ucfirst($liq->status) }}
                                    </span>
                                </td>

                                {{-- Due / Settled --}}
                                <td class="px-6 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    @if($liq->liquidated_at)
                                        <span class="text-success-600 dark:text-success-400">
                                            ✓ {{ \Carbon\Carbon::parse($liq->liquidated_at)->format('d/m/Y') }}
                                        </span>
                                    @elseif($liq->due_date)
                                        <span class="{{ $liq->isOverdue() ? 'text-danger-600 dark:text-danger-400 font-bold' : '' }}">
                                            {{ $liq->isOverdue() ? '⚠️ ' : '' }}{{ \Carbon\Carbon::parse($liq->due_date)->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                {{-- Linked JE --}}
                                <td class="px-6 py-3 text-xs">
                                    @if($linkedJe)
                                        <a href="{{ \App\Filament\Vouchers\Resources\JournalEntryResource::getUrl('view', ['record' => $linkedJe]) }}"
                                           class="font-mono text-primary-600 dark:text-primary-400 hover:underline">
                                            {{ $linkedJe->entry_no }}
                                        </a>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-700 italic text-[11px]">No JE</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    {{-- Totals Footer --}}
                    <tfoot>
                        <tr class="bg-gray-50/70 dark:bg-gray-800/60 border-t-2 border-gray-200 dark:border-gray-700 text-xs font-bold">
                            <td class="px-6 py-3 text-gray-500 uppercase tracking-wider" colspan="2">Totals</td>
                            <td class="px-6 py-3 text-right font-mono text-gray-900 dark:text-white">
                                {{ number_format($liqTotals['total_advanced'], 2) }}
                            </td>
                            <td class="px-6 py-3 text-right font-mono text-gray-900 dark:text-white">
                                {{ number_format($liqTotals['total_spent'], 2) }}
                            </td>
                            <td class="px-6 py-3 text-right font-mono text-success-600 dark:text-success-400">
                                {{ number_format($liqTotals['total_returned'], 2) }}
                            </td>
                            @php
                                $tv = $liqTotals['total_variance'];
                                $tvClass = abs($tv) <= 0.01
                                    ? 'text-success-600 dark:text-success-400'
                                    : ($tv < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-info-600 dark:text-info-400');
                            @endphp
                            <td class="px-6 py-3 text-right font-mono {{ $tvClass }}">
                                {{ $tv > 0 ? '+' : '' }}{{ number_format($tv, 2) }}
                            </td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Footer note --}}
        <div class="mt-4 flex justify-between items-center text-xs">
            <span class="text-gray-400 italic">
                * Liquidation is operationally tracked independent of JE posting.
            </span>
            <a href="{{ \App\Filament\Vouchers\Resources\LiquidationResource::getUrl('index') }}"
               class="text-primary-500 hover:underline font-bold flex items-center gap-1">
                View full Liquidation module
                <x-filament::icon icon="heroicon-m-arrow-right" class="h-3 w-3" />
            </a>
        </div>

    @elseif($this->ledgerGroups->isNotEmpty())
        {{-- GL has data but no liquidations in range --}}
        <div class="mt-12 flex items-center gap-3">
            <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
            <span class="text-xs text-gray-400 italic whitespace-nowrap">No PCV liquidations found for the selected period.</span>
            <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
        </div>
    @endif

</x-filament-panels::page>

