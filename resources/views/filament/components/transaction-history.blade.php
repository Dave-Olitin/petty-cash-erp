<div class="space-y-6">
    @forelse ($histories as $history)
        <div class="flex gap-x-4">
            <!-- Timeline Column -->
            <div class="relative flex flex-col items-center">
                <!-- Vertical Line (connects to next item) -->
                @if(!$loop->last)
                    <div class="absolute top-0 bottom-[-1.5rem] left-1/2 w-px -ml-[0.5px] bg-gray-200 dark:bg-gray-700"></div>
                @endif
                
                <!-- Dot -->
                <div class="relative z-10 flex items-center justify-center w-8 h-8 rounded-full bg-white dark:bg-gray-900 border-2 border-primary-500 shadow-sm shrink-0">
                    <div class="w-2.5 h-2.5 rounded-full bg-primary-500"></div>
                </div>
            </div>

            <!-- Content Card -->
            <div class="flex-1 min-w-0">
                <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800">
                        <div class="flex items-center gap-2 truncate">
                            <span class="font-semibold text-gray-900 dark:text-gray-100 text-sm truncate">{{ $history->user->name ?? 'Unknown User' }}</span>
                            <span class="text-xs text-gray-400">•</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $history->created_at->format('M j, g:i a') }}</span>
                        </div>
                        <div class="text-[10px] font-medium text-gray-500 uppercase tracking-wide bg-white dark:bg-gray-700 px-2 py-0.5 rounded border border-gray-200 dark:border-gray-600 whitespace-nowrap">
                            {{ $history->created_at->diffForHumans(short: true) }}
                        </div>
                    </div>

                    <div class="p-4">
                        <!-- Reason -->
                        <div class="mb-4">
                            <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1.5">Reason</div>
                            <div class="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800/80 p-3 rounded-md border border-gray-100 dark:border-gray-700/50">
                                {{ $history->reason }}
                            </div>
                        </div>

                        <!-- Changes Table -->
                        @if(!empty($history->original_data) && !empty($history->modified_data))
                            <div class="rounded-md border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <table class="w-full text-sm text-left">
                                    <thead class="bg-gray-50 dark:bg-gray-800/80 text-xs text-gray-500 uppercase font-semibold">
                                        <tr>
                                            <th class="px-3 py-2 w-1/4 border-b border-gray-200 dark:border-gray-700 pl-4">Field</th>
                                            <th class="px-3 py-2 w-1/3 border-b border-gray-200 dark:border-gray-700 text-red-600 dark:text-red-400">Previous</th>
                                            <th class="px-3 py-2 w-1/3 border-b border-gray-200 dark:border-gray-700 text-green-600 dark:text-green-400">New</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                                        @foreach($history->modified_data as $key => $newValue)
                                            @php
                                                $oldValue = $history->original_data[$key] ?? null;
                                                if ($oldValue == $newValue || in_array($key, ['updated_at', 'id', 'created_at', 'deleted_at'])) continue;
                                                
                                                $label = ucfirst(str_replace('_', ' ', $key));
                                                
                                                if ($key === 'amount') {
                                                    $oldValue = 'AED ' . number_format((float)$oldValue, 2);
                                                    $newValue = 'AED ' . number_format((float)$newValue, 2);
                                                } elseif ($key === 'category_id') {
                                                    $label = 'Category';
                                                    $oldValue = \App\Models\Category::find($oldValue)?->name ?? 'None';
                                                    $newValue = \App\Models\Category::find($newValue)?->name ?? 'None';
                                                } elseif ($key === 'branch_id') {
                                                    $label = 'Branch';
                                                    $oldValue = \App\Models\Branch::find($oldValue)?->name ?? 'None';
                                                    $newValue = \App\Models\Branch::find($newValue)?->name ?? 'None';
                                                } elseif ($key === 'receipt_path') {
                                                    $label = 'Receipt';
                                                    $oldValue = $oldValue ? 'Has Image' : 'None';
                                                    $newValue = $newValue ? 'Updated Image' : 'None';
                                                }
                                            @endphp
                                            <tr class="group/row hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                                <td class="px-3 py-2.5 font-medium text-gray-700 dark:text-gray-300 pl-4 border-r border-transparent group-hover/row:border-gray-100 dark:group-hover/row:border-gray-800">
                                                    {{ $label }}
                                                </td>
                                                <td class="px-3 py-2.5 text-gray-500 dark:text-gray-400 break-words">
                                                    <span class="line-through decoration-red-300 decoration-2 opacity-75">{{ is_array($oldValue) ? json_encode($oldValue) : $oldValue }}</span>
                                                </td>
                                                <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-gray-100 break-words">
                                                    {{ is_array($newValue) ? json_encode($newValue) : $newValue }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-xs text-gray-400 italic">No specific field changes.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="flex flex-col items-center justify-center py-12 px-4 text-center border-2 border-dashed border-gray-200 dark:border-gray-800 rounded-lg">
            <div class="p-3 rounded-full bg-gray-50 dark:bg-gray-800 mb-3">
                <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">No History Recorded</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Changes to this transaction will appear here.</p>
        </div>
    @endforelse
</div>
