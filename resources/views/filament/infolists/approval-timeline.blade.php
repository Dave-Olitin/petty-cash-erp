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
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-top: -0.5rem;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }
    
    .approval-timeline-title-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 0.5rem;
        margin-bottom: 0.25rem;
    }
    .approval-timeline-title {
        font-size: 1.1rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }
    .approval-timeline-time {
        font-size: 0.8125rem;
        color: #64748b;
        font-weight: 600;
        background: #f1f5f9;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
    }
    
    /* Detail Rows (User & Comment) */
    .approval-timeline-detail {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        color: #475569;
        font-size: 0.9rem;
        word-break: break-word;
    }

    /* Fluid Responsiveness */
    .approval-timeline-title-row {
        gap: 0.5rem;
        flex-wrap: wrap; /* Automatically stacks when narrow */
    }
    
    @media (max-width: 640px) {
        .approval-timeline-title {
            font-size: 1rem; /* Slightly smaller on mobile */
        }
        .approval-timeline-time {
            width: fit-content;
        }
    }
    .approval-timeline-detail svg {
        width: 1.25rem;
        height: 1.25rem;
        color: #94a3b8;
        margin-top: 0.125rem;
        flex-shrink: 0;
    }
    .approval-timeline-user-name {
        font-weight: 700;
        color: #334155;
    }
    
    /* Pending Specifics */
    .approval-timeline-item.is-pending .approval-timeline-content {
        background-color: transparent;
        border: 1px dashed #cbd5e1;
        box-shadow: none;
    }
    .approval-timeline-item.is-pending .approval-timeline-title {
        color: #94a3b8;
    }
    

    /* Dark Mode Overrides */
    .dark .approval-timeline-content { background-color: #1e293b; border-color: #334155; }
    .dark .approval-timeline-title-row { border-color: #334155; }
    .dark .approval-timeline-title { color: #f8fafc; }
    .dark .approval-timeline-time { background: #334155; color: #cbd5e1; }
    .dark .approval-timeline-detail { color: #cbd5e1; }
    .dark .approval-timeline-user-name { color: #f8fafc; }
    .dark .approval-timeline-list::before { background-color: #374151; }
    .dark .approval-timeline-node { background-color: #1f2937; }
    
    .dark .node-success { background-color: rgba(59, 130, 246, 0.1); }
    .dark .node-warning { background-color: rgba(249, 115, 22, 0.1); }
    .dark .node-danger { background-color: rgba(239, 68, 68, 0.1); }
    .dark .node-pending { background-color: #1f2937; }
    .dark .node-pending .approval-timeline-node-inner { background-color: #4b5563; }

    /* Seen By Section */
    .seen-by-section {
        margin-top: 2rem;
        padding-top: 1rem;
        border-top: 1px solid #f1f5f9;
    }
    .dark .seen-by-section { border-top-color: #334155; }
    
    .seen-by-header {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        margin-bottom: 0.75rem;
        color: #64748b;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .seen-by-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .seen-by-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.625rem;
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 9999px;
        font-size: 0.75rem;
        color: #475569;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }
    .dark .seen-by-badge {
        background-color: #1e293b;
        border-color: #334155;
        color: #cbd5e1;
    }
    .seen-by-badge svg {
        width: 0.875rem;
        height: 0.875rem;
        color: #94a3b8;
    }
    .seen-by-time {
        color: #94a3b8;
        font-weight: 400;
        margin-left: 0.125rem;
    }
</style>

<div class="approval-timeline">
    <div class="approval-timeline-header">
        <x-heroicon-o-bars-3-bottom-left />
        <h3>Approval History</h3>
    </div>

    <div class="approval-timeline-list">
        @foreach ($getRecord()->approvals()->with('user.roles')->oldest()->get() as $approval)
            <div class="approval-timeline-item">
                <!-- Node Icon -->
                <div class="approval-timeline-node 
                    @if(in_array($approval->action, ['approved', 'paid'])) node-success
                    @elseif(in_array($approval->action, ['rejected', 'voided'])) node-danger
                    @else node-warning
                    @endif">
                    <div class="approval-timeline-node-inner">
                        @if(in_array($approval->action, ['approved', 'paid']))
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                              <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                            </svg>
                        @elseif(in_array($approval->action, ['rejected', 'voided']))
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
        @endforeach

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

    {{-- Seen By Block (WhatsApp Style) --}}
    @php
        $voucherCreatorId = $getRecord()->user_id;

        $views = $getRecord()->views()
            ->with('user.roles')
            ->where('user_id', '!=', $voucherCreatorId) // Exclude the requester (obvious they've seen it)
            ->whereHas('user', function ($q) {
                // Exclude Super Admin users — they are system administrators, not reviewers
                $q->whereDoesntHave('roles', fn ($r) => $r->where('name', 'Super Admin'));
            })
            ->orderBy('updated_at', 'desc')
            ->get();
    @endphp

    @if($views->count() > 0)
        <div class="seen-by-section">
            <div class="seen-by-header">
                <x-heroicon-m-eye class="w-4 h-4" />
                <span>Seen By</span>
            </div>
            <div class="seen-by-list">
                @foreach($views as $view)
                    <div class="seen-by-badge" title="Last seen: {{ $view->updated_at->format('d M Y, H:i') }}">
                        <x-heroicon-s-user-circle />
                        <span>
                            {{ $view->user->name === auth()->user()->name ? 'You' : explode(' ', $view->user->name)[0] }}
                            <span class="seen-by-time">&bull; {{ $view->updated_at->diffForHumans(short: true) }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
