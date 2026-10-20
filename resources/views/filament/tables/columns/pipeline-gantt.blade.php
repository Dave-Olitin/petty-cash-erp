@php
    $record = $getRecord();
    $progress = $record?->pipeline_stages ?? ['percentage' => 0, 'completed' => 0, 'total' => 0, 'stages' => []];
    $percentage = $progress['percentage'] ?? 0;
    $completed = $progress['completed'] ?? 0;
    $total = $progress['total'] ?? 0;
    $stages = $progress['stages'] ?? [];

    $currentStage = null;
    foreach ($stages as $s) {
        if (($s['state'] ?? '') === 'in_progress') {
            $currentStage = $s['label'];
            break;
        }
    }
    if (! $currentStage && $completed < $total) {
        foreach ($stages as $s) {
            if (($s['state'] ?? '') === 'pending') {
                $currentStage = $s['label'];
                break;
            }
        }
    }
    if (! $currentStage && $percentage === 100) {
        $currentStage = 'All Completed';
    }

    $summaryTooltip = $completed . ' of ' . $total . ' stages completed (' . $percentage . '%)';
    $tooltipLines = [$summaryTooltip, '----------------------'];
    foreach ($stages as $i => $s) {
        $st = match($s['state'] ?? '') {
            'completed' => '✓ Completed',
            'in_progress' => '● In Progress',
            default => 'Pending',
        };
        $tooltipLines[] = ($i + 1) . '. ' . $s['label'] . ' — ' . $st;
    }
    $fullTooltip = implode("\n", $tooltipLines);
@endphp

<div 
    class="pro-gantt-chart-cell" 
    title="{{ $fullTooltip }}" 
    style="display: flex; flex-direction: column; gap: 3px; min-width: 140px; max-width: 175px; padding: 2px 0;"
>
    {{-- Segmented Gantt Bar --}}
    <div style="display: flex; align-items: center; gap: 2px; width: 100%; height: 7px;">
        @foreach ($stages as $stage)
            @php
                $st = $stage['state'] ?? 'pending';
                $color = match($st) {
                    'completed' => '#10b981', // green
                    'in_progress' => '#f59e0b', // amber
                    default => '#e5e7eb', // gray
                };
            @endphp
            <div 
                style="flex: 1; height: 100%; border-radius: 2px; background-color: {{ $color }};"
                title="{{ $stage['label'] }}: {{ ucfirst($st) }}"
            ></div>
        @endforeach
    </div>

    {{-- Gantt Meta & Stage Label --}}
    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; line-height: 1.1;">
        <span style="font-weight: 700; color: {{ $percentage === 100 ? '#15803d' : ($percentage > 0 ? '#374151' : '#9ca3af') }};">
            {{ $percentage }}%
        </span>
        <span style="font-size: 10px; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 105px;" title="{{ $currentStage }}">
            {{ $completed }}/{{ $total }} · {{ $currentStage ?? 'Draft' }}
        </span>
    </div>
</div>
