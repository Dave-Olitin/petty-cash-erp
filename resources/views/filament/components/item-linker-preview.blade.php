@props([
    'preview',
    'includeSmart' => true,
])

@php
    $exactMatches = $preview['exact_matches'] ?? [];
    $smartMatches = $preview['smart_matches'] ?? [];
    $unmatched = $preview['unmatched'] ?? [];
    $allMatches = array_merge($exactMatches, $smartMatches);
    $totalUnassigned = $preview['total_unassigned'] ?? 0;
    $totalLinked = $preview['total_linked'] ?? 0;
    $exactCount = $preview['exact_count'] ?? 0;
    $smartCount = $preview['smart_count'] ?? 0;
    $unmatchedCount = $preview['unmatched_count'] ?? 0;
    $affectedTransactions = $preview['affected_transactions'] ?? 0;
@endphp

<div class="space-y-4 text-sm" x-data="{ tab: 'matched' }">
    <!-- Summary Stat Cards (Strict 4-Column Layout) -->
    <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px;">
        <!-- Total Unassigned -->
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/75 dark:bg-gray-800/60 p-3">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">In Queue</div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $totalUnassigned }}</span>
                <span class="text-xs text-gray-500">unassigned</span>
            </div>
        </div>

        <!-- Exact Matches -->
        <div class="rounded-lg border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50/50 dark:bg-emerald-950/20 p-3">
            <div class="text-xs font-medium text-emerald-700 dark:text-emerald-400 flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                Exact Matches
            </div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-xl font-bold text-emerald-700 dark:text-emerald-300">{{ $exactCount }}</span>
                <span class="text-xs text-emerald-600 dark:text-emerald-400">items</span>
            </div>
        </div>

        <!-- Smart Matches -->
        <div class="rounded-lg border border-blue-200 dark:border-blue-800/60 bg-blue-50/50 dark:bg-blue-950/20 p-3">
            <div class="text-xs font-medium text-blue-700 dark:text-blue-400 flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" /></svg>
                Smart Matches
            </div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-xl font-bold text-blue-700 dark:text-blue-300">{{ $smartCount }}</span>
                <span class="text-xs text-blue-600 dark:text-blue-400">items</span>
            </div>
        </div>

        <!-- Remaining Unmatched -->
        <div class="rounded-lg border border-amber-200 dark:border-amber-800/60 bg-amber-50/50 dark:bg-amber-950/20 p-3">
            <div class="text-xs font-medium text-amber-700 dark:text-amber-400 flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                Unmatched
            </div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-xl font-bold text-amber-700 dark:text-amber-300">{{ $unmatchedCount }}</span>
                <span class="text-xs text-amber-600 dark:text-amber-400">items</span>
            </div>
        </div>
    </div>

    <!-- Tabs Header -->
    <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pt-1">
        <div class="flex space-x-2">
            <button
                type="button"
                @click="tab = 'matched'"
                :class="tab === 'matched' ? 'border-primary-500 text-primary-600 dark:text-primary-400 font-semibold border-b-2' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 hover:border-gray-300'"
                class="px-3 py-2 text-xs flex items-center gap-1.5 transition-colors focus:outline-none"
            >
                <span>Ready to Link</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">
                    {{ count($allMatches) }}
                </span>
            </button>

            <button
                type="button"
                @click="tab = 'unmatched'"
                :class="tab === 'unmatched' ? 'border-amber-500 text-amber-600 dark:text-amber-400 font-semibold border-b-2' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 hover:border-gray-300'"
                class="px-3 py-2 text-xs flex items-center gap-1.5 transition-colors focus:outline-none"
            >
                <span>Remaining Unmatched</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                    {{ count($unmatched) }}
                </span>
            </button>
        </div>

        <div class="text-[11px] text-gray-500 dark:text-gray-400">
            Affects <strong class="text-gray-700 dark:text-gray-200">{{ $affectedTransactions }}</strong> transaction(s)
        </div>
    </div>

    <!-- TAB 1: Matched Table -->
    <div x-show="tab === 'matched'" class="space-y-2">
        @if(count($allMatches) > 0)
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden shadow-xs">
                <div class="max-h-72 overflow-y-auto">
                    <table class="w-full text-left text-xs divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-semibold uppercase tracking-wider text-[10px] sticky top-0 z-10 shadow-xs">
                            <tr>
                                <th class="py-2.5 px-3">#</th>
                                <th class="py-2.5 px-3">Transaction Item</th>
                                <th class="py-2.5 px-3">Matched Master</th>
                                <th class="py-2.5 px-3">Account Code to Assign</th>
                                <th class="py-2.5 px-3 text-right">Match Type</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                            @foreach($allMatches as $index => $item)
                                <tr class="hover:bg-gray-50/75 dark:hover:bg-gray-800/40 transition-colors">
                                    <td class="py-2 px-3 text-gray-400 font-mono text-[10px]">
                                        {{ $index + 1 }}
                                    </td>
                                    <td class="py-2 px-3 font-medium text-gray-900 dark:text-gray-100 max-w-[200px] truncate" title="{{ $item['item_name'] }}">
                                        {{ $item['item_name'] }}
                                    </td>
                                    <td class="py-2 px-3 text-gray-600 dark:text-gray-300 max-w-[180px] truncate" title="{{ $item['master_name'] }}">
                                        {{ $item['master_name'] }}
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="font-mono text-[11px] font-semibold text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-950/60 px-1.5 py-0.5 rounded border border-primary-200 dark:border-primary-800">
                                                {{ $item['account_code'] }}
                                            </span>
                                            <span class="text-gray-600 dark:text-gray-400 truncate max-w-[150px]" title="{{ $item['account_name'] }}">
                                                {{ $item['account_name'] }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3 text-right whitespace-nowrap">
                                        @if(str_starts_with($item['match_type'], 'Exact'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                                Exact
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800" title="{{ $item['match_type'] }}">
                                                {{ $item['match_type'] }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center justify-between">
                <span>Showing all <strong>{{ count($allMatches) }}</strong> matches ready to be updated.</span>
                <span>Click <strong>"Auto-link Now"</strong> below to apply these codes.</span>
            </div>
        @else
            <div class="p-6 text-center rounded-lg border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/30">
                <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <div class="mt-2 text-sm font-semibold text-gray-700 dark:text-gray-300">No matches found</div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Add more items to your Item Masterfile or turn on Smart Matching.</p>
            </div>
        @endif
    </div>

    <!-- TAB 2: Unmatched Table -->
    <div x-show="tab === 'unmatched'" style="display: none;" class="space-y-2">
        @if(count($unmatched) > 0)
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden shadow-xs">
                <div class="max-h-72 overflow-y-auto">
                    <table class="w-full text-left text-xs divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-semibold uppercase tracking-wider text-[10px] sticky top-0 z-10 shadow-xs">
                            <tr>
                                <th class="py-2.5 px-3">#</th>
                                <th class="py-2.5 px-3">Transaction Item Text</th>
                                <th class="py-2.5 px-3">Transaction #</th>
                                <th class="py-2.5 px-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                            @foreach($unmatched as $index => $item)
                                <tr class="hover:bg-gray-50/75 dark:hover:bg-gray-800/40 transition-colors">
                                    <td class="py-2 px-3 text-gray-400 font-mono text-[10px]">
                                        {{ $index + 1 }}
                                    </td>
                                    <td class="py-2 px-3 font-medium text-gray-900 dark:text-gray-100">
                                        {{ $item['item_name'] }}
                                    </td>
                                    <td class="py-2 px-3 text-gray-500 dark:text-gray-400 font-mono text-[11px]">
                                        TX #{{ $item['transaction_id'] }}
                                    </td>
                                    <td class="py-2 px-3 text-right">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                            Not in Masterfile
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                These items will remain unassigned. You can classify them later or add them to the Item Masterfile.
            </p>
        @else
            <div class="p-6 text-center rounded-lg border border-gray-200 dark:border-gray-700 bg-emerald-50/30 dark:bg-emerald-950/10">
                <div class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">100% of items matched!</div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">There are no remaining unmatched items in this batch.</p>
            </div>
        @endif
    </div>
</div>
