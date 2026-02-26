<style>
    /* Custom Approval Timeline Styles - Minimal Design */
    .approval-timeline {
        padding: 0;
    }
    .approval-timeline-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #f3f4f6;
    }
    .approval-timeline-header h3 {
        font-size: 1rem;
        font-weight: 800;
        color: #111827;
        margin: 0;
    }
    .approval-timeline-header svg {
        width: 1.25rem;
        height: 1.25rem;
        color: #6b7280;
    }
    .dark .approval-timeline-header { border-color: #374151; }
    .dark .approval-timeline-header h3 { color: #f3f4f6; }
    .dark .approval-timeline-header svg { color: #9ca3af; }
    .approval-timeline-list {
        position: relative;
        padding-left: 2.5rem; /* Space for the line and nodes */
    }
    .approval-timeline-list::before {
        content: "";
        position: absolute;
        top: 2rem;
        bottom: 0;
        left: 1.125rem; /* Center of the node */
        width: 2px;
        background-color: #f3f4f6; /* Very light gray line */
        z-index: 0;
    }
    .approval-timeline-item {
        position: relative;
        padding-bottom: 2rem;
    }
    .approval-timeline-item:last-child {
        padding-bottom: 0;
    }
    .approval-timeline-item:last-child::before {
        display: none; /* Try to hide line after last item, handled by wrapper bottom */
    }
    
    /* The Node Icon */
    .approval-timeline-node {
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
        background-color: #f3f4f6; /* Default light gray circle */
    }
    .approval-timeline-node-inner {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 1.25rem;
        height: 1.25rem;
        border-radius: 9999px;
        color: white;
    }
    .approval-timeline-node-inner svg {
        width: 0.75rem;
        height: 0.75rem;
        stroke-width: 3;
    }
    
    /* Status Colors for Nodes */
    .node-success { background-color: #eff6ff; } /* Light blue bg */
    .node-success .approval-timeline-node-inner { background-color: #3b82f6; } /* Solid blue */
    
    .node-danger { background-color: #fef2f2; }
    .node-danger .approval-timeline-node-inner { background-color: #ef4444; }
    
    .node-warning { background-color: #fff7ed; }
    .node-warning .approval-timeline-node-inner { background-color: #f97316; }

    .node-pending { background-color: #f8fafc; }
    .node-pending .approval-timeline-node-inner { background-color: #cbd5e1; }
    .node-pending .approval-timeline-node-inner svg { color: #f8fafc; }

    /* Content Area */
    .approval-timeline-content {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .approval-timeline-title-row {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
    }
    .approval-timeline-title {
        font-size: 1rem;
        font-weight: 700;
        color: #111827;
        margin: 0;
    }
    .approval-timeline-time {
        font-size: 0.8125rem;
        color: #9ca3af;
        font-weight: 500;
    }
    
    /* Detail Rows (User & Comment) */
    .approval-timeline-detail {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        color: #6b7280;
        font-size: 0.875rem;
    }
    .approval-timeline-detail svg {
        width: 1.125rem;
        height: 1.125rem;
        color: #9ca3af;
        margin-top: 0.125rem;
        flex-shrink: 0;
    }
    .approval-timeline-user-name {
        font-weight: 500;
    }
    
    /* Pending Specifics */
    .approval-timeline-item.is-pending .approval-timeline-title {
        color: #6b7280;
    }
    
    .approval-timeline-empty {
        font-size: 0.875rem;
        color: #6b7280;
        font-style: italic;
        padding-left: 2.5rem;
    }

    /* Dark Mode Overrides */
    .dark .approval-timeline-list::before { background-color: #374151; }
    .dark .approval-timeline-node { background-color: #1f2937; }
    .dark .approval-timeline-title { color: #f3f4f6; }
    .dark .approval-timeline-time { color: #9ca3af; }
    .dark .approval-timeline-detail { color: #9ca3af; }
    .dark .approval-timeline-detail svg { color: #6b7280; }
    
    .dark .node-success { background-color: rgba(59, 130, 246, 0.1); }
    .dark .node-warning { background-color: rgba(249, 115, 22, 0.1); }
    .dark .node-danger { background-color: rgba(239, 68, 68, 0.1); }
    .dark .node-pending { background-color: #1f2937; }
    .dark .node-pending .approval-timeline-node-inner { background-color: #4b5563; }
</style>

<div class="approval-timeline">
    <div class="approval-timeline-header">
        <x-heroicon-o-bars-3-bottom-left />
        <h3>Approval History</h3>
    </div>

    <div class="approval-timeline-list">
        @forelse ($getRecord()->approvals()->with('user.roles')->oldest()->get() as $approval)
            <div class="approval-timeline-item">
                <!-- Node Icon -->
                <div class="approval-timeline-node 
                    @if(in_array($approval->action, ['approved', 'paid'])) node-success
                    @elseif($approval->action === 'rejected') node-danger
                    @else node-warning
                    @endif">
                    <div class="approval-timeline-node-inner">
                        @if(in_array($approval->action, ['approved', 'paid']))
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                              <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                            </svg>
                        @elseif($approval->action === 'rejected')
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                              <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                            </svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                              <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                            </svg>
                        @endif
                    </div>
                </div>

                <!-- Content -->
                <div class="approval-timeline-content">
                    <div class="approval-timeline-title-row">
                        <h4 class="approval-timeline-title">
                            @if(strtolower($approval->action) === 'approved')
                                @if(auth()->id() === $approval->user_id)
                                    Recommended (You)
                                @else
                                    Recommended
                                @endif
                            @else
                                {{ ucfirst($approval->action) }}
                            @endif
                        </h4>
                        
                        <!-- Smart Time Display (e.g., "18 hours ago" or "16 - January - 2024") -->
                        <span class="approval-timeline-time">
                            @if($approval->created_at->diffInDays(now()) < 2)
                                {{ $approval->created_at->diffForHumans() }}
                            @else
                                {{ $approval->created_at->format('d - F - Y') }}
                            @endif
                        </span>
                    </div>

                    <div class="approval-timeline-detail">
                        <x-heroicon-o-user />
                        <span class="approval-timeline-user-name">
                            {{ trim($approval->user->name ?? 'System') }}
                        </span>
                    </div>
                    
                    @if($approval->comments)
                        <div class="approval-timeline-detail">
                            <x-heroicon-o-envelope />
                            <span>{!! nl2br(e($approval->comments)) !!}</span>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="approval-timeline-empty">
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
                <div class="approval-timeline-node node-pending">
                    <div class="approval-timeline-node-inner">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>

                <!-- Pending Content -->
                <div class="approval-timeline-content">
                    <div class="approval-timeline-title-row">
                        <h4 class="approval-timeline-title">{{ $step['action'] }}</h4>
                    </div>

                    <div class="approval-timeline-detail">
                        <x-heroicon-o-user />
                        <span class="approval-timeline-user-name">{{ $step['actor'] }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
