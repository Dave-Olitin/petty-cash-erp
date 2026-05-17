<x-filament-panels::page>
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
                        <th class="px-6 py-4 font-bold">Voucher #</th>
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
                            <td colspan="5" class="px-6 py-3">
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
                            @endphp
                            <tr x-show="isOpen" class="hover:bg-primary-50/30 dark:hover:bg-primary-900/10 transition-colors border-b border-gray-50 dark:border-gray-800/50 last:border-b-0">
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap pl-12 relative">
                                    <div class="absolute left-8 top-0 bottom-0 w-px bg-gray-200 dark:bg-gray-700"></div>
                                    {{ $line->journalEntry?->date?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="px-6 py-3">
                                    @if($line->journalEntry)
                                        <a href="{{ \App\Filament\Vouchers\Resources\JournalEntryResource::getUrl('view', ['record' => $line->journalEntry]) }}"
                                           class="font-mono text-xs font-bold text-primary-600 dark:text-primary-400 hover:underline">
                                            {{ $line->journalEntry->entry_no }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3">
                                    @if($line->journalEntry?->voucher)
                                        <a href="{{ \App\Filament\Vouchers\Resources\VoucherResource::getUrl('view', ['record' => $line->journalEntry->voucher]) }}"
                                           class="text-xs text-gray-500 dark:text-gray-400 hover:underline">
                                            {{ $line->journalEntry->voucher->voucher_number }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-xs text-gray-500 dark:text-gray-400 max-w-[150px] truncate" title="{{ $line->journalEntry?->voucher?->payee }}">
                                    {{ $line->journalEntry?->voucher?->payee ?: '—' }}
                                </td>
                                <td class="px-6 py-3 text-xs text-gray-500 dark:text-gray-400 uppercase italic">
                                    {{ $line->branch ?: '—' }}
                                </td>
                                <td class="px-6 py-3 text-right font-mono text-xs {{ (float)$line->debit > 0 ? 'text-gray-900 dark:text-white font-bold' : 'text-gray-300 dark:text-gray-700' }}">
                                    {{ (float)$line->debit > 0 ? number_format($line->debit, 2) : '—' }}
                                </td>
                                <td class="px-6 py-3 text-right font-mono text-xs {{ (float)$line->credit > 0 ? 'text-gray-900 dark:text-white font-bold' : 'text-gray-300 dark:text-gray-700' }}">
                                    {{ (float)$line->credit > 0 ? number_format($line->credit, 2) : '—' }}
                                </td>
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
                            <td colspan="8" class="text-center py-24 text-gray-400 dark:text-gray-600 bg-gray-50/20">
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
</x-filament-panels::page>
