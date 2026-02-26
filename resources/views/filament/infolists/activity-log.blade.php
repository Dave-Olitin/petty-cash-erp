<div class="w-full">
    @php
        $activities = $getRecord()->activities()->with('causer')->latest()->get();
    @endphp

    @if($activities->isEmpty())
        <p class="text-sm text-gray-500 italic">No activity recorded for this voucher yet.</p>
    @else
        <div class="flow-root">
            <ul role="list" class="-mb-8">
                @foreach($activities as $activity)
                    <li>
                        <div class="relative pb-8">
                            @if(!$loop->last)
                                <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-white/10" aria-hidden="true"></span>
                            @endif
                            
                            <div class="relative flex space-x-3">
                                <div>
                                    <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white dark:ring-gray-900 
                                        {{ match($activity->description) {
                                            'created' => 'bg-success-500',
                                            'updated' => 'bg-warning-500',
                                            'deleted' => 'bg-danger-500',
                                            default   => 'bg-gray-500'
                                        } }}">
                                        
                                        @if($activity->description === 'created')
                                            <x-heroicon-m-plus class="h-4 w-4 text-white" />
                                        @elseif($activity->description === 'updated')
                                            <x-heroicon-m-pencil class="h-4 w-4 text-white" />
                                        @elseif($activity->description === 'deleted')
                                            <x-heroicon-m-trash class="h-4 w-4 text-white" />
                                        @else
                                            <x-heroicon-m-cog class="h-4 w-4 text-white" />
                                        @endif
                                    </span>
                                </div>
                                <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            <span class="font-medium text-gray-900 dark:text-white">
                                                {{ $activity->causer ? $activity->causer->name : 'System' }}
                                            </span> 
                                            {{ $activity->description }} the voucher
                                        </p>
                                        
                                        @php
                                            $props = $activity->properties;
                                            if ($props instanceof \Spatie\Activitylog\Contracts\Activity) {
                                                $props = $props->properties->toArray();
                                            } elseif (is_object($props) && method_exists($props, 'toArray')) {
                                                $props = $props->toArray();
                                            } elseif (is_string($props)) {
                                                $props = json_decode($props, true) ?? [];
                                            } elseif (!is_array($props)) {
                                                $props = [];
                                            }
                                            
                                            $old = \Illuminate\Support\Arr::get($props, 'old', []);
                                            $new = \Illuminate\Support\Arr::get($props, 'attributes', []);
                                            
                                            if (empty($old) && empty($new)) {
                                                $new = is_array($props) ? $props : [];
                                            }
                                        @endphp
                                        
                                        @if($activity->description === 'updated' && !empty($old) && !empty($new))
                                            <div class="mt-2 text-xs text-gray-500 bg-gray-50 dark:bg-white/5 rounded-md p-2 w-full overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col" class="py-1 text-left font-medium text-gray-500 dark:text-gray-400">Field</th>
                                                            <th scope="col" class="py-1 text-left font-medium text-gray-500 dark:text-gray-400">Old</th>
                                                            <th scope="col" class="py-1 text-left font-medium text-gray-500 dark:text-gray-400">New</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                                        @foreach($new as $key => $newVal)
                                                            @if(in_array($key, ['created_at', 'updated_at', 'deleted_at'])) @continue @endif
                                                            @php $oldVal = \Illuminate\Support\Arr::get($old, $key, '—'); @endphp
                                                            @if($oldVal == $newVal) @continue @endif
                                                            <tr>
                                                                <td class="py-1 whitespace-nowrap font-mono text-[10px] text-gray-900 dark:text-white">{{ ucwords(str_replace('_', ' ', $key)) }}</td>
                                                                <td class="py-1 font-mono text-[10px] text-danger-600 truncate max-w-[150px]">{{ is_array($oldVal) ? json_encode($oldVal) : StringHelpers::truncate($oldVal ?? '—', 50) }}</td>
                                                                <td class="py-1 font-mono text-[10px] text-success-600 truncate max-w-[150px]">{{ is_array($newVal) ? json_encode($newVal) : StringHelpers::truncate($newVal ?? '—', 50) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="whitespace-nowrap text-right text-xs text-gray-500 dark:text-gray-400">
                                        <time datetime="{{ $activity->created_at->toIso8601String() }}">
                                            {{ $activity->created_at->diffForHumans() }}
                                        </time>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

@php
    // Helper class since we can't easily define functions in blade without conflicts
    if (!class_exists('StringHelpers')) {
        class StringHelpers {
            public static function truncate($string, $length = 50, $append = "...") {
                $string = trim((string)$string);
                if(strlen($string) > $length) {
                    $string = substr($string, 0, $length) . $append;
                }
                return $string;
            }
        }
    }
@endphp
