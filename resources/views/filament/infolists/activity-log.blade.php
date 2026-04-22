<style>
    /* Custom Activity Log Styles - Premium Minimal Design */
    .activity-log {
        padding: 0;
    }
    .activity-log-list {
        position: relative;
        padding-left: 2.5rem;
    }
    .activity-log-list::before {
        content: "";
        position: absolute;
        top: 2rem;
        bottom: 0;
        left: 1.125rem;
        width: 2px;
        background-color: #f3f4f6;
        z-index: 0;
    }
    .activity-log-item {
        position: relative;
        padding-bottom: 2rem;
    }
    .activity-log-item:last-child {
        padding-bottom: 0;
    }
    
    /* The Node Icon */
    .activity-log-node {
        position: absolute;
        left: -2.5rem;
        top: 0;
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 9999px;
        background-color: #f3f4f6;
    }
    .activity-log-node-inner {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 1.25rem;
        height: 1.25rem;
        border-radius: 9999px;
        color: white;
    }
    .activity-log-node-inner svg {
        width: 0.75rem;
        height: 0.75rem;
    }
    
    /* Status Colors for Nodes */
    .act-node-success { background-color: #f0fdf4; }
    .act-node-success .activity-log-node-inner { background-color: #22c55e; }
    
    .act-node-warning { background-color: #fff7ed; }
    .act-node-warning .activity-log-node-inner { background-color: #f97316; }

    .act-node-danger { background-color: #fef2f2; }
    .act-node-danger .activity-log-node-inner { background-color: #ef4444; }

    .act-node-info { background-color: #eff6ff; }
    .act-node-info .activity-log-node-inner { background-color: #3b82f6; }

    .act-node-default { background-color: #f8fafc; }
    .act-node-default .activity-log-node-inner { background-color: #64748b; }

    /* Content Area */
    .activity-log-content {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
    }
    
    .activity-log-title-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
    }
    .activity-log-title {
        font-size: 0.9375rem;
        font-weight: 700;
        color: #111827;
        margin: 0;
    }
    .activity-log-time {
        font-size: 0.8125rem;
        color: #9ca3af;
        font-weight: 500;
    }
    
    /* Detail Rows */
    .activity-log-detail {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        color: #6b7280;
        font-size: 0.875rem;
    }
    .activity-log-detail svg {
        width: 1.125rem;
        height: 1.125rem;
        color: #9ca3af;
        margin-top: 0.125rem;
        flex-shrink: 0;
    }
    .activity-log-user-name {
        font-weight: 500;
    }

    /* Diff Table Styling */
    .activity-diff-container {
        margin-top: 0.75rem;
        background-color: #f9fafb;
        border: 1px solid #f3f4f6;
        border-radius: 0.5rem;
        padding: 0.5rem;
        overflow-x: auto;
    }
    .activity-diff-table {
        width: 100%;
        font-size: 0.75rem;
        border-collapse: collapse;
    }
    .activity-diff-table th {
        text-align: left;
        color: #9ca3af;
        font-weight: 600;
        padding: 0.25rem 0.5rem;
        text-transform: uppercase;
        font-size: 0.65rem;
        letter-spacing: 0.05em;
    }
    .activity-diff-table td {
        padding: 0.375rem 0.5rem;
        border-top: 1px solid #f3f4f6;
    }
    .field-name { font-weight: 600; color: #4b5563; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    .val-old { color: #ef4444; }
    .val-new { color: #22c55e; font-weight: 500; }

    /* Dark Mode Overrides */
    .dark .activity-log-list::before { background-color: #374151; }
    .dark .activity-log-node { background-color: #1f2937; }
    .dark .activity-log-title { color: #f3f4f6; }
    .dark .activity-log-time { color: #9ca3af; }
    .dark .activity-log-detail { color: #9ca3af; }
    .dark .activity-log-detail svg { color: #6b7280; }
    .dark .activity-diff-container { background-color: rgba(31, 41, 55, 0.4); border-color: #374151; }
    .dark .activity-diff-table td { border-color: #374151; }
    .dark .field-name { color: #9ca3af; }
    
    .dark .act-node-success { background-color: rgba(34, 197, 94, 0.1); }
    .dark .act-node-warning { background-color: rgba(249, 115, 22, 0.1); }
    .dark .act-node-danger { background-color: rgba(239, 68, 68, 0.1); }
    .dark .act-node-info { background-color: rgba(59, 130, 246, 0.1); }
    .dark .act-node-default { background-color: #1f2937; }
</style>

<div class="activity-log">
    @php
        $activities = $getRecord()->activities()->with('causer')->latest()->get();
    @endphp

    @if($activities->isEmpty())
        <div class="px-4 py-8 text-center bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-dashed border-gray-200 dark:border-gray-700">
            <x-heroicon-o-chat-bubble-left-right class="mx-auto h-8 w-8 text-gray-400" />
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No activity recorded for this voucher yet.</p>
        </div>
    @else
        <div class="activity-log-list">
            @foreach($activities as $activity)
                <div class="activity-log-item">
                    <!-- Node Icon -->
                    <div class="activity-log-node 
                        {{ match($activity->description) {
                            'created' => 'act-node-success',
                            'updated' => 'act-node-info',
                            'deleted' => 'act-node-danger',
                            'submitted' => 'act-node-warning',
                            'approved' => 'act-node-success',
                            'rejected' => 'act-node-danger',
                            'paid' => 'act-node-success',
                            default   => 'act-node-default'
                        } }}">
                        <div class="activity-log-node-inner">
                            @if($activity->description === 'created')
                                <x-heroicon-m-plus />
                            @elseif($activity->description === 'updated')
                                <x-heroicon-m-pencil />
                            @elseif($activity->description === 'deleted')
                                <x-heroicon-m-trash />
                            @elseif($activity->description === 'submitted')
                                <x-heroicon-m-paper-airplane />
                            @elseif(in_array($activity->description, ['approved', 'paid']))
                                <x-heroicon-m-check />
                            @elseif($activity->description === 'rejected')
                                <x-heroicon-m-x-mark />
                            @else
                                <x-heroicon-m-bolt />
                            @endif
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="activity-log-content">
                        <div class="activity-log-title-row">
                            <h4 class="activity-log-title">
                                {{ match($activity->description) {
                                    'created' => 'Voucher Created',
                                    'updated' => 'Voucher Updated',
                                    'deleted' => 'Voucher Deleted',
                                    'submitted' => 'Voucher Submitted',
                                    'approved' => 'Voucher Approved',
                                    'rejected' => 'Voucher Rejected',
                                    'paid' => 'Voucher Paid',
                                    default => ucfirst($activity->description)
                                } }}
                            </h4>
                            <span class="activity-log-time">{{ $activity->created_at->diffForHumans() }}</span>
                        </div>

                        <div class="activity-log-detail">
                            <x-heroicon-o-user />
                            <span>
                                By <span class="activity-log-user-name">{{ $activity->causer ? $activity->causer->name : 'System' }}</span>
                                <span class="ml-1 text-xs text-gray-400">({{ $activity->created_at->format('d M y, h:i A') }})</span>
                            </span>
                        </div>
                        
                        @php
                            $props = $activity->properties;
                            if ($props instanceof \Spatie\Activitylog\Contracts\Activity) {
                                $props = $props->properties->toArray();
                            } elseif (is_object($props) && method_exists($props, 'toArray')) {
                                $props = $props->toArray();
                            } elseif (is_string($props)) {
                                $props = json_decode($props, true) ?? [];
                            }
                            
                            $old = \Illuminate\Support\Arr::get($props, 'old', []);
                            $new = \Illuminate\Support\Arr::get($props, 'attributes', []);
                            
                            if (empty($old) && empty($new)) {
                                $new = is_array($props) ? $props : [];
                            }
                        @endphp
                        
                        @if($activity->description === 'updated' && !empty($old) && !empty($new))
                            <div class="activity-diff-container">
                                <table class="activity-diff-table">
                                    <thead>
                                        <tr>
                                            <th>Field</th>
                                            <th>Before</th>
                                            <th>After</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($new as $key => $newVal)
                                            @if(in_array($key, ['created_at', 'updated_at', 'deleted_at', 'current_approval_step'])) @continue @endif
                                            @php $oldVal = \Illuminate\Support\Arr::get($old, $key, '—'); @endphp
                                            @if($oldVal == $newVal) @continue @endif
                                            <tr>
                                                <td class="field-name">{{ ucwords(str_replace('_', ' ', $key)) }}</td>
                                                <td class="val-old">{{ is_array($oldVal) ? 'Modified' : (is_null($oldVal) || $oldVal === '—' ? '—' : (strlen($oldVal) > 30 ? substr($oldVal, 0, 30).'...' : $oldVal)) }}</td>
                                                <td class="val-new">{{ is_array($newVal) ? 'Modified' : (is_null($newVal) ? '—' : (strlen($newVal) > 30 ? substr($newVal, 0, 30).'...' : $newVal)) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
