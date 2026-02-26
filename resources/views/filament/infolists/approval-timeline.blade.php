<style>
    /* Custom Approval Timeline Styles */
    .approval-timeline {
        padding: 0;
    }
    .approval-timeline-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
    }
    .approval-timeline-header h3 {
        font-size: 0.875rem;
        font-weight: 700;
        color: #374151;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin: 0;
    }
    .approval-timeline-header svg {
        width: 1.25rem;
        height: 1.25rem;
        color: #9ca3af;
    }
    .approval-timeline-list {
        position: relative;
    }
    .approval-timeline-list::before {
        content: "";
        position: absolute;
        top: 0.5rem;
        bottom: 0.5rem;
        left: 15px;
        width: 2px;
        background-color: #e5e7eb;
    }
    .approval-timeline-item {
        position: relative;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .approval-timeline-node {
        position: relative;
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 1.5rem;
        height: 1.5rem;
        flex-shrink: 0;
        background-color: #ffffff;
        border-radius: 9999px;
        margin-top: 0.25rem;
        box-shadow: 0 0 0 4px #ffffff;
    }
    .approval-timeline-node .inner-dot {
        width: 0.75rem;
        height: 0.75rem;
        border-radius: 9999px;
    }
    .approval-timeline-node .inner-dot.status-success { background-color: #22c55e; }
    .approval-timeline-node .inner-dot.status-danger { background-color: #ef4444; }
    .approval-timeline-node .inner-dot.status-gray { background-color: #9ca3af; }
    .approval-timeline-node .inner-dot.status-pending {
        width: 0.5rem;
        height: 0.5rem;
        background-color: transparent;
        border: 2px solid #d1d5db;
    }
    .approval-timeline-card {
        flex: 1;
        min-width: 0;
        background-color: #ffffff;
        border: 1px solid #e5e7eb;
        padding: 1rem;
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }
    .approval-timeline-item.is-pending {
        opacity: 0.6;
    }
    .approval-timeline-card.is-pending {
        background-color: transparent;
        border: 2px dashed #e5e7eb;
        padding: 0.875rem;
    }
    .approval-timeline-card-header {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
    }
    .approval-timeline-card.is-pending .approval-timeline-card-header {
        align-items: center;
    }
    .approval-timeline-info {
        display: flex;
        flex-direction: column;
        gap: 0.625rem;
        min-width: 100px;
        flex: 1 1 auto;
    }
    .approval-timeline-user {
        display: flex;
        flex-direction: column;
    }
    .approval-timeline-name {
        font-weight: 700;
        color: #111827;
        font-size: 0.875rem;
        line-height: 1.25;
        overflow-wrap: break-word;
        word-break: normal;
    }
    .approval-timeline-role {
        font-weight: 500;
        color: #6b7280;
        font-size: 0.75rem;
        line-height: 1.25;
        margin-top: 0.125rem;
    }
    .approval-timeline-badge-container {
        display: flex;
    }
    .approval-timeline-badge {
        display: inline-flex;
        border-radius: 9999px;
        padding: 0.125rem 0.625rem;
        font-size: 0.625rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .approval-timeline-badge.status-success {
        background-color: #f0fdf4;
        color: #15803d;
        border: 1px solid rgba(187, 247, 208, 0.6);
    }
    .approval-timeline-badge.status-danger {
        background-color: #fef2f2;
        color: #b91c1c;
        border: 1px solid rgba(254, 202, 202, 0.6);
    }
    .approval-timeline-badge.status-gray {
        background-color: #f9fafb;
        color: #374151;
        border: 1px solid rgba(229, 231, 235, 0.6);
    }
    .approval-timeline-badge.status-pending {
        background-color: #f3f4f6;
        color: #9ca3af;
        border: none;
    }
    .approval-timeline-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.25rem;
        flex: 0 0 auto;
    }
    .approval-timeline-date {
        color: #6b7280;
        font-size: 0.75rem;
        font-weight: 500;
    }
    .approval-timeline-time {
        color: #9ca3af;
        font-size: 0.6875rem;
        font-family: monospace;
    }
    .approval-timeline-comments {
        margin-top: 1rem;
        font-size: 0.8125rem;
        color: #4b5563;
        background-color: #f9fafb;
        padding: 0.75rem;
        border-radius: 0.5rem;
        border: 1px solid #f3f4f6;
        font-style: italic;
        line-height: 1.625;
        overflow-wrap: break-word;
    }
    .approval-timeline-pending-actor {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 100px;
        flex: 1 1 auto;
    }
    .approval-timeline-pending-actor svg {
        width: 1rem;
        height: 1rem;
        color: #9ca3af;
        flex-shrink: 0;
        animation: spin 3s linear infinite;
    }
    .approval-timeline-pending-actor span {
        font-weight: 500;
        color: #6b7280;
        font-size: 0.875rem;
        overflow-wrap: break-word;
        word-break: normal;
    }
    .approval-timeline-empty {
        font-size: 0.875rem;
        color: #6b7280;
        font-style: italic;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding-left: 2rem;
    }
    .approval-timeline-empty svg {
        width: 1rem;
        height: 1rem;
    }

    /* Dark Mode Overrides */
    .dark .approval-timeline-header h3 { color: #e5e7eb; }
    .dark .approval-timeline-list::before { background-color: #374151; }
    .dark .approval-timeline-node { background-color: #111827; box-shadow: 0 0 0 4px #18181b; }
    .dark .approval-timeline-node .inner-dot.status-pending { border-color: #6b7280; }
    .dark .approval-timeline-card { background-color: #111827; border-color: #1f2937; }
    .dark .approval-timeline-card.is-pending { border-color: #374151; }
    .dark .approval-timeline-name { color: #f3f4f6; }
    .dark .approval-timeline-role { color: #9ca3af; }
    .dark .approval-timeline-badge.status-success { background-color: rgba(34, 197, 94, 0.1); color: #4ade80; border-color: rgba(34, 197, 94, 0.2); }
    .dark .approval-timeline-badge.status-danger { background-color: rgba(239, 68, 68, 0.1); color: #f87171; border-color: rgba(239, 68, 68, 0.2); }
    .dark .approval-timeline-badge.status-gray { background-color: #1f2937; color: #d1d5db; border-color: #374151; }
    .dark .approval-timeline-badge.status-pending { background-color: rgba(31, 41, 55, 0.8); color: #6b7280; }
    .dark .approval-timeline-meta { border-color: #1f2937; }
    .dark .approval-timeline-date { color: #9ca3af; }
    .dark .approval-timeline-time { color: #6b7280; }
    .dark .approval-timeline-comments { background-color: rgba(31, 41, 55, 0.5); border-color: #1f2937; color: #d1d5db; }
    .dark .approval-timeline-pending-actor span { color: #9ca3af; }
    .dark .approval-timeline-empty { color: #9ca3af; }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>

<div class="approval-timeline">
    <div class="approval-timeline-header">
        <x-heroicon-o-cpu-chip />
        <h3>Approval Workflow</h3>
    </div>

    <div class="approval-timeline-list">
        @forelse ($getRecord()->approvals()->with('user.roles')->oldest()->get() as $approval)
            <div class="approval-timeline-item">
                <!-- Node Icon -->
                <div class="approval-timeline-node">
                    <div class="inner-dot 
                        @if(in_array($approval->action, ['approved', 'paid'])) status-success
                        @elseif($approval->action === 'rejected') status-danger
                        @else status-gray
                        @endif">
                    </div>
                </div>

                <!-- Content Card -->
                <div class="approval-timeline-card">
                    <div class="approval-timeline-card-header">
                        <!-- Left: Info & Badge -->
                        <div class="approval-timeline-info">
                            <div class="approval-timeline-user">
                                <span class="approval-timeline-name">
                                    {{ trim($approval->user->name ?? 'System') }}
                                </span>
                                <span class="approval-timeline-role">
                                    {{ $approval->user?->roles?->first()?->name ?? 'User' }}
                                </span>
                            </div>
                            <div>
                                <span class="approval-timeline-badge 
                                    @if(in_array($approval->action, ['approved', 'paid'])) status-success
                                    @elseif($approval->action === 'rejected') status-danger
                                    @else status-gray
                                    @endif">
                                    {{ mb_strtolower($approval->action) }}
                                </span>
                            </div>
                        </div>

                        <!-- Right Container (Time) -->
                        <div class="approval-timeline-meta">
                            <span class="approval-timeline-date">{{ $approval->created_at->format('d M y') }}</span>
                            <span class="approval-timeline-time">{{ $approval->created_at->format('h:i a') }}</span>
                        </div>
                    </div>
                    
                    @if($approval->comments)
                        <div class="approval-timeline-comments">
                            "{!! nl2br(e($approval->comments)) !!}"
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="approval-timeline-empty">
                <x-heroicon-o-clock />
                <span>No approvals recorded yet.</span>
            </div>
        @endforelse

        {{-- 🔮 DYNAMIC PREDICTION OF PENDING STEPS 🔮 --}}
        @php
            $voucher = $getRecord();
            $pendingSteps = [];

            if ($voucher->status === 'draft') {
                $pendingSteps[] = ['actor' => "Requester", 'action' => 'Pending Submission'];
                $pendingSteps[] = ['actor' => "Accountant", 'action' => 'Pending Check'];
                $pendingSteps[] = ['actor' => "General Manager", 'action' => 'Pending Approval'];
            } 
            elseif ($voucher->status === 'pending_checker') {
                $pendingSteps[] = ['actor' => "Accountant", 'action' => 'Pending Check'];
                $pendingSteps[] = ['actor' => "General Manager", 'action' => 'Pending Approval'];
            } 
            elseif ($voucher->status === 'pending_approver') {
                $totalSteps = \App\Models\ApprovalWorkflow::totalSteps();
                $currentStep = (int) ($voucher->current_approval_step ?? 1);
                
                if ($totalSteps > 0) {
                    for ($i = $currentStep; $i <= $totalSteps; $i++) {
                        $stepConfig = \App\Models\ApprovalWorkflow::getApproverAtStep($i);
                        $actorName = $stepConfig ? ($stepConfig->label ?? $stepConfig->user->name) : "Approver";
                        $pendingSteps[] = ['actor' => trim(str_replace("\n", " ", $actorName)), 'action' => 'Pending Approval'];
                    }
                } else {
                    $pendingSteps[] = ['actor' => "General Manager", 'action' => 'Pending Approval'];
                }
            }
            elseif ($voucher->status === 'approved') {
                $pendingSteps[] = ['actor' => "Finance", 'action' => 'Pending Payment'];
            }
        @endphp

        @foreach($pendingSteps as $step)
            <div class="approval-timeline-item is-pending">
                <!-- Pending Node Icon -->
                <div class="approval-timeline-node">
                    <div class="inner-dot status-pending"></div>
                </div>

                <!-- Pending Card -->
                <div class="approval-timeline-card is-pending">
                    <div class="approval-timeline-card-header">
                        <div class="approval-timeline-pending-actor">
                            <x-heroicon-o-arrow-path />
                            <span>{{ $step['actor'] }}</span>
                        </div>
                        
                        <div>
                            <span class="approval-timeline-badge status-pending">
                                {{ $step['action'] }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
