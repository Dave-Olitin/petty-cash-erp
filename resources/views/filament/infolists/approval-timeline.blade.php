<div class="space-y-4">
    <h3 class="text-sm font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400 mb-4 flex items-center gap-2">
        <x-heroicon-o-cpu-chip class="w-5 h-5 text-primary-500 animate-pulse" />
        Approval Sequence
    </h3>

    <div class="relative pl-6 border-l-2 border-gray-200 dark:border-gray-800 space-y-6">
        @forelse ($getRecord()->approvals as $approval)
            <div class="relative">
                <!-- Glowing Node -->
                <div class="absolute -left-[31px] top-1">
                    <div class="w-4 h-4 rounded-full border-4 border-white dark:border-gray-900 
                        @if($approval->action === 'approved') bg-success-500 shadow-[0_0_10px_rgba(34,197,94,0.6)]
                        @elseif($approval->action === 'rejected') bg-danger-500 shadow-[0_0_10px_rgba(239,68,68,0.6)]
                        @else bg-warning-500 shadow-[0_0_10px_rgba(245,158,11,0.6)]
                        @endif">
                    </div>
                </div>

                <!-- Content Card -->
                <div class="bg-white dark:bg-white/5 backdrop-blur-md border border-gray-100 dark:border-white/10 p-4 rounded-2xl shadow-sm hover:shadow-md transition-all duration-300 group">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-bold text-gray-900 dark:text-gray-100">
                                    {{ $approval->user->name ?? 'System' }}
                                </span>
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium tracking-wide shadow-sm
                                    @if($approval->action === 'approved') bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400 border border-success-200/50
                                    @elseif($approval->action === 'rejected') bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400 border border-danger-200/50
                                    @else bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400 border border-warning-200/50
                                    @endif">
                                    {{ strtoupper($approval->action) }}
                                </span>
                            </div>
                            
                            @if($approval->comments)
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 bg-gray-50 dark:bg-black/20 p-2 rounded-lg border border-gray-100 dark:border-white/5 inline-block">
                                    "{{ $approval->comments }}"
                                </p>
                            @endif
                        </div>
                        
                        <div class="text-right flex flex-col items-end">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 font-mono">
                                {{ $approval->created_at->format('d M') }}
                            </span>
                            <span class="text-[10px] text-gray-400 dark:text-gray-500 font-mono mt-0.5">
                                {{ $approval->created_at->format('H:i') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-sm text-gray-500 dark:text-gray-400 italic flex items-center gap-2">
                <x-heroicon-o-clock class="w-4 h-4" />
                No approvals recorded yet.
            </div>
        @endforelse
    </div>
</div>
