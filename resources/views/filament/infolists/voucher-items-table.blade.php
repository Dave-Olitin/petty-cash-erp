<style>
    .voucher-table-wrapper {
        width: 100%;
        overflow-x: auto;
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
        background-color: white;
    }
    .dark .voucher-table-wrapper {
        background-color: #111827;
        border-color: #374151;
    }
    .voucher-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 0.8125rem;
    }
    .voucher-table th {
        background-color: #f8fafc;
        padding: 0.75rem 1rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.025em;
        color: #475569;
        font-size: 0.75rem;
        border-bottom: 2px solid #e5e7eb;
    }
    .dark .voucher-table th {
        background-color: #1f2937;
        color: #9ca3af;
        border-bottom-color: #374151;
    }
    .voucher-table td {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        vertical-align: middle;
    }
    .dark .voucher-table td {
        border-bottom-color: #1f2937;
        color: #d1d5db;
    }
    .voucher-table tr:last-child td {
        border-bottom: none;
    }
    .voucher-table tr:hover td {
        background-color: #f1f5f9;
    }
    .dark .voucher-table tr:hover td {
        background-color: #1f2937;
    }
    .type-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.125rem 0.5rem;
        border-radius: 9999px;
        font-weight: 700;
        font-size: 0.7rem;
    }
    .badge-debit {
        background-color: #f0fdf4;
        color: #166534;
        border: 1px solid #bdf2cb;
    }
    .badge-credit {
        background-color: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    .dark .badge-debit { background-color: rgba(22, 101, 52, 0.2); }
    .dark .badge-credit { background-color: rgba(153, 27, 27, 0.2); }

    .font-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }
    .text-right { text-align: right; }
</style>

<div class="voucher-table-wrapper shadow-sm">
    <table class="voucher-table">
        <thead>
            <tr>
                <th style="width: 40px;">#</th>
                <th style="width: 100px;">Branch</th>
                <th>Account & Description</th>
                <th>Supplier / TRN</th>
                <th style="width: 100px;">PO #</th>
                <th style="width: 100px;">Inv #</th>
                <th style="width: 60px;">Type</th>
                <th class="text-right" style="width: 120px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @php
                $items = $getRecord()->items;
                $totalDr = 0;
                $totalCr = 0;
            @endphp
            @forelse($items as $index => $item)
                @php
                    if ($item->entry_type === 'debit') $totalDr += (float)$item->amount;
                    else $totalCr += (float)$item->amount;
                @endphp
                <tr>
                    <td class="text-gray-400 font-medium">{{ $index + 1 }}</td>
                    <td>
                        <span class="font-semibold">{{ $item->branch_code ?? '—' }}</span>
                    </td>
                    <td>
                        <div class="flex flex-col">
                            <span class="font-bold text-gray-900 dark:text-gray-100">{{ $item->account_code }}</span>
                            @if($item->description)
                                <span class="text-xs text-gray-500 italic">{{ $item->description }}</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        @if($item->trn)
                            <span class="text-xs font-mono bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded border border-gray-200 dark:border-gray-700">
                                {{ $item->trn }}
                            </span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="text-xs">{{ $item->po_number ?? '—' }}</span>
                    </td>
                    <td>
                        <span class="text-xs">{{ $item->invoice_number ?? '—' }}</span>
                    </td>
                    <td>
                        <span class="type-badge {{ $item->entry_type === 'debit' ? 'badge-debit' : 'badge-credit' }}">
                            {{ strtoupper($item->entry_type) }}
                        </span>
                    </td>
                    <td class="text-right font-mono font-bold">
                        <span class="{{ $item->entry_type === 'debit' ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                            AED {{ number_format($item->amount, 2) }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center py-10 text-gray-400 italic">No line items found.</td>
                </tr>
            @endforelse
        </tbody>
        @if($items->isNotEmpty())
            <tfoot class="bg-gray-50 dark:bg-gray-800/50">
                <tr>
                    <td colspan="6" class="text-right font-bold text-gray-500 uppercase text-[10px] tracking-wider">Sub-Totals</td>
                    <td class="text-right font-mono text-[11px] font-bold text-green-700 dark:text-green-400">
                        DR: {{ number_format($totalDr, 2) }}
                    </td>
                    <td class="text-right font-mono text-[11px] font-bold text-red-700 dark:text-red-400">
                        CR: {{ number_format($totalCr, 2) }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
