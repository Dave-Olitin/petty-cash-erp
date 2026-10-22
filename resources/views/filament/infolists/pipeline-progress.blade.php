@php
    $progress = $getRecord()->pipeline_stages;
    $percentage = $progress['percentage'] ?? 0;
    $completed = $progress['completed'] ?? 0;
    $total = $progress['total'] ?? 0;
    $stages = $progress['stages'] ?? [];
    $completedCost = (float) ($progress['completed_cost'] ?? 0);
    $remainingFee = (float) ($progress['remaining_fee'] ?? 0);
    $totalFee = (float) ($progress['total_fee'] ?? 0);
@endphp

<div class="pro-pipeline-container" style="display: flex; flex-direction: column; gap: 14px; width: 100%;">
    {{-- Header Metric & Progress Meter --}}
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        <div style="display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap;">
            <span style="font-size: 14px; font-weight: 700; color: inherit;">
                Pipeline Completion
            </span>
            <span style="font-size: 13px; color: #6b7280;">
                ({{ $completed }} of {{ $total }} stages completed)
            </span>
            @if ($totalFee > 0)
                <span style="font-size: 12px; font-weight: 600; color: #047857; background-color: #ecfdf5; padding: 2px 8px; border-radius: 4px; border: 1px solid #a7f3d0;">
                    AED {{ number_format($completedCost, 2) }} completed · AED {{ number_format($remainingFee, 2) }} remaining
                </span>
            @endif
        </div>
        <div>
            <span style="display: inline-block; padding: 4px 14px; border-radius: 9999px; font-size: 13px; font-weight: 700; {{ $percentage === 100 ? 'background-color: #d1fae5; color: #065f46;' : 'background-color: #eff6ff; color: #1e40af;' }}">
                {{ $percentage }}% Completed
            </span>
        </div>
    </div>

    {{-- Visual Progress Track --}}
    <div style="width: 100%; height: 10px; background-color: #e5e7eb; border-radius: 9999px; overflow: hidden; position: relative;">
        <div style="width: {{ $percentage }}%; height: 100%; border-radius: 9999px; background: {{ $percentage === 100 ? '#10b981' : 'linear-gradient(90deg, #3b82f6 0%, #2563eb 100%)' }}; transition: width 0.5s ease-in-out;"></div>
    </div>

    {{-- 9-Stage Milestone Grid --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px; width: 100%; margin-top: 4px;">
        @foreach ($stages as $index => $stage)
            @php
                $isCompleted = ($stage['state'] ?? '') === 'completed';
                $isInProgress = ($stage['state'] ?? '') === 'in_progress';
                $cost = (float) ($stage['cost'] ?? 0);
                $isNa = ($stage['state'] ?? '') === 'na' || (! $isCompleted && ! $isInProgress && $cost <= 0);
            @endphp
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border-radius: 8px; box-sizing: border-box;
                @if ($isCompleted)
                    border: 1px solid #bbf7d0; background-color: #f0fdf4;
                @elseif ($isInProgress)
                    border: 1px solid #fde68a; background-color: #fffbeb;
                @elseif ($isNa)
                    border: 1px dashed #cbd5e1; background-color: #f8fafc;
                @else
                    border: 1px solid #e5e7eb; background-color: #fafafa;
                @endif
            ">
                <div style="display: flex; flex-direction: column; gap: 2px; overflow: hidden;">
                    <span style="font-size: 13px; font-weight: 600; color: {{ $isCompleted ? '#166534' : ($isInProgress ? '#92400e' : ($isNa ? '#64748b' : '#374151')) }}; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ $index + 1 }}. {{ $stage['label'] }}
                    </span>
                    @if ($cost > 0)
                        <span style="font-size: 11px; font-weight: 500; color: #6b7280;">
                            AED {{ number_format($cost, 2) }}
                        </span>
                    @else
                        <span style="font-size: 11px; color: #94a3b8; font-weight: 500;">
                            No Fee
                        </span>
                    @endif
                </div>

                <div style="margin-left: 12px; flex-shrink: 0;">
                    @if ($isCompleted)
                        <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background-color: #dcfce7; color: #15803d; border: 1px solid #86efac;">
                            ✓ Done
                        </span>
                    @elseif ($isInProgress)
                        <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background-color: #fef3c7; color: #b45309; border: 1px solid #fcd34d;">
                            ● In Progress
                        </span>
                    @elseif ($isNa)
                        <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background-color: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1;">
                            N/A
                        </span>
                    @else
                        <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; background-color: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb;">
                            Pending
                        </span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
