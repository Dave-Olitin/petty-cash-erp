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
                    * Adjust filters and click generate to refresh the trial balance.
                </div>
                <x-filament::button type="submit" icon="heroicon-m-magnifying-glass" wire:loading.attr="disabled" size="lg" class="px-8 shadow-md">
                    <span wire:loading.remove>Generate Report</span>
                    <span wire:loading>Processing...</span>
                </x-filament::button>
            </div>
        </div>
    </form>

    {{-- ── Trial Balance Table ── --}}
    <div class="mt-8 bg-white dark:bg-gray-900 rounded-xl shadow border border-gray-200 dark:border-gray-800 overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-800 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-gray-50/50 dark:bg-gray-800/30">
            <div>
                <h2 class="text-lg font-bold text-gray-900 dark:text-white uppercase tracking-tight">Accounting — Trial Balance</h2>
                <p class="text-xs text-gray-500 font-medium">
                    Period: <span class="text-primary-600 dark:text-primary-400">{{ $data['from_date'] ? \Carbon\Carbon::parse($data['from_date'])->format('M d, Y') : 'Start' }}</span> 
                    to <span class="text-primary-600 dark:text-primary-400">{{ $data['to_date'] ? \Carbon\Carbon::parse($data['to_date'])->format('M d, Y') : 'Today' }}</span>
                </p>
            </div>
            <div>
                @if($this->isBalanced)
                    <div class="inline-flex items-center gap-x-2 rounded-full bg-green-50 px-3 py-1 text-xs font-bold text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-900/20 dark:text-green-400">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                        </span>
                        BALANCED
                    </div>
                @else
                    <div class="inline-flex items-center gap-x-2 rounded-full bg-red-50 px-3 py-1 text-xs font-bold text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-900/20 dark:text-red-400">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-pulse absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                        </span>
                        OUT OF BALANCE
                    </div>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
                <thead>
                    <tr class="bg-gray-50/30 dark:bg-gray-800/50 text-[11px] uppercase tracking-wider text-gray-500 font-bold border-b border-gray-200 dark:border-gray-800">
                        <th class="px-6 py-4">Account</th>
                        <th class="px-6 py-4">Name</th>
                        <th class="px-6 py-4 text-right">Debit (AED)</th>
                        <th class="px-6 py-4 text-right">Credit (AED)</th>
                        <th class="px-6 py-4 text-right">Net Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800/50">
                    @php $currentType = null; @endphp
                    @forelse($this->accounts as $account)
                        @if($account->total_debit != 0 || $account->total_credit != 0)
                            @if($currentType !== $account->type->value)
                                <tr class="bg-gray-50/20 dark:bg-gray-800/10">
                                    <td colspan="5" class="px-6 py-2 font-black text-gray-400 uppercase text-[9px] tracking-[0.2em] italic">
                                        {{ $account->type->label() }}
                                    </td>
                                </tr>
                                @php $currentType = $account->type->value; @endphp
                            @endif
                            <tr class="hover:bg-primary-50/30 dark:hover:bg-primary-900/10 transition-colors">
                                <td class="px-6 py-4 font-mono text-xs font-bold text-primary-600 dark:text-primary-400">
                                    {{ $account->code }}
                                </td>
                                <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                    {{ $account->name }}
                                </td>
                                <td class="px-6 py-4 text-right font-mono text-xs {{ $account->total_debit > 0 ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-700' }}">
                                    {{ $account->total_debit != 0 ? number_format($account->total_debit, 2) : '—' }}
                                </td>
                                <td class="px-6 py-4 text-right font-mono text-xs {{ $account->total_credit > 0 ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-700' }}">
                                    {{ $account->total_credit != 0 ? number_format($account->total_credit, 2) : '—' }}
                                </td>
                                <td class="px-6 py-4 text-right font-mono text-xs font-bold text-gray-900 dark:text-white">
                                    @php
                                        $bal = abs($account->net_balance);
                                        $suffix = $account->net_balance >= 0 ? ($account->normal_balance === 'debit' ? 'DR' : 'CR') : ($account->normal_balance === 'debit' ? 'CR' : 'DR');
                                    @endphp
                                    {{ number_format($bal, 2) }} <span class="text-[9px] font-normal text-gray-400 ml-0.5">{{ $suffix }}</span>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-24 text-gray-400 dark:text-gray-600">
                                <x-filament::icon icon="heroicon-o-scale" class="mx-auto h-16 w-16 mb-4 opacity-20" />
                                <p class="text-base font-medium">No account activity found for this period.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Trial Balance Summary Bar ── --}}
    @if($this->accounts->isNotEmpty())
        <div class="mt-6 bg-white dark:bg-gray-900 rounded-xl shadow border border-gray-200 dark:border-gray-800 p-1">
            <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-gray-100 dark:divide-gray-800">
                {{-- Total Debit --}}
                <div class="px-6 py-4 flex items-center justify-between">
                    <span class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">Total Debit</span>
                    <span class="font-mono text-lg font-bold text-gray-900 dark:text-white">{{ number_format($this->totalDebit, 2) }}</span>
                </div>
                
                {{-- Total Credit --}}
                <div class="px-6 py-4 flex items-center justify-between">
                    <span class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">Total Credit</span>
                    <span class="font-mono text-lg font-bold text-gray-900 dark:text-white">{{ number_format($this->totalCredit, 2) }}</span>
                </div>

                {{-- Status / Difference --}}
                <div class="px-6 py-4 flex items-center justify-between {{ $this->isBalanced ? 'bg-green-50/50 dark:bg-green-900/10' : 'bg-red-50/50 dark:bg-red-900/10' }}">
                    <span class="text-[10px] uppercase tracking-widest {{ $this->isBalanced ? 'text-green-600' : 'text-red-600' }} font-bold">Status</span>
                    @if($this->isBalanced)
                        <span class="font-bold text-green-600 dark:text-green-400 text-sm">BALANCED</span>
                    @else
                        <span class="font-mono text-lg font-black text-red-600 dark:text-red-400">
                            DIFF: {{ number_format(abs($this->totalDebit - $this->totalCredit), 2) }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
