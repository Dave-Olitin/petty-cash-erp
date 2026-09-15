<x-filament-widgets::widget>
    @php
        $banks = $this->getBankSummaryData();
    @endphp

    <div class="p-2">
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-sm font-bold text-gray-800 dark:text-gray-200">
                    💳 Bank Accounts with Payment Vouchers
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    ({{ count($banks) }} accounts · click any bank or tag to filter vouchers below)
                </span>
            </div>
            @if (request()->has('tableFilters'))
                <a href="{{ route('filament.vouchers.resources.bank-reconciliations.index') }}"
                   style="font-size:12px;color:#4f46e5;font-weight:600;text-decoration:underline;">
                    ✕ Clear Filter / Show All
                </a>
            @endif
        </div>

        {{-- Valid account codes first --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($banks as $bank)
                @if ($bank['is_valid_code'])
                    @php
                        $isSelected = request()->input('tableFilters.bank.value') === $bank['bank'];
                    @endphp
                    <div style="
                        background: {{ $isSelected ? 'linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%)' : 'linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%)' }};
                        border: {{ $isSelected ? '2px solid #16a34a' : '1.5px solid #86efac' }};
                        border-radius: 12px;
                        padding: 16px;
                        position: relative;
                        overflow: hidden;
                        transition: transform 0.15s, box-shadow 0.15s;
                    ">
                        {{-- Top label --}}
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                            <span style="font-family:monospace;font-size:11px;font-weight:700;color:#15803d;background:#dcfce7;padding:2px 8px;border-radius:20px;border:1px solid #86efac;">
                                {{ $bank['bank'] }}
                            </span>
                            @if ($isSelected)
                                <span style="font-size:10px;color:#16a34a;font-weight:700;background:#fff;padding:1px 6px;border-radius:10px;">
                                    ● Active Filter
                                </span>
                            @endif
                        </div>

                        {{-- Account name --}}
                        <div style="font-size:12px;font-weight:600;color:#1e293b;margin-bottom:6px;line-height:1.3;min-height:32px;">
                            {{ Str::limit($bank['account_name'], 48) }}
                        </div>

                        {{-- Stats --}}
                        <div style="display:flex;gap:12px;margin-bottom:12px;">
                            <div>
                                <div style="font-size:18px;font-weight:800;color:#166534;">
                                    AED {{ number_format($bank['total'], 2) }}
                                </div>
                                <div style="font-size:11px;color:#4b5563;">
                                    {{ $bank['count'] }} voucher{{ $bank['count'] > 1 ? 's' : '' }} paid
                                </div>
                            </div>
                        </div>

                        {{-- Filter button --}}
                        <a href="{{ route('filament.vouchers.resources.bank-reconciliations.index') }}?tableFilters[bank][value]={{ urlencode($bank['bank']) }}"
                           style="
                               display:inline-flex;align-items:center;gap:6px;
                               background: {{ $isSelected ? '#15803d' : '#16a34a' }};
                               color:#fff;
                               padding:6px 14px;border-radius:8px;
                               font-size:12px;font-weight:600;text-decoration:none;
                               box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                           ">
                            <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                            </svg>
                            {{ $isSelected ? 'Filtering Vouchers' : 'View Vouchers' }}
                        </a>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Legacy / free-text banks --}}
        @php
            $legacyBanks = array_filter($banks, fn($b) => !$b['is_valid_code']);
        @endphp
        @if (count($legacyBanks) > 0)
            <div style="margin-top:20px;border-top:1px solid #e2e8f0;padding-top:16px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <div style="font-size:12px;font-weight:700;color:#b45309;">
                        ⚠️ Legacy Bank Names (not linked to account codes — {{ count($legacyBanks) }} groups)
                    </div>
                    <span style="font-size:11px;color:#78716c;">
                        Click any tag below to filter those vouchers and edit them manually
                    </span>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    @foreach ($legacyBanks as $bank)
                        @php
                            $isLegacySelected = request()->input('tableFilters.bank.value') === $bank['bank'];
                        @endphp
                        <a href="{{ route('filament.vouchers.resources.bank-reconciliations.index') }}?tableFilters[bank][value]={{ urlencode($bank['bank']) }}"
                           style="
                               background: {{ $isLegacySelected ? '#fde047' : '#fef9c3' }};
                               border: {{ $isLegacySelected ? '2px solid #ca8a04' : '1px solid #fde68a' }};
                               border-radius:8px;padding:6px 10px;
                               font-size:12px;text-decoration:none;
                               display:inline-flex;align-items:center;gap:6px;
                               transition:all 0.15s;
                           ">
                            <span style="font-weight:700;color:#92400e;">{{ $bank['bank'] }}</span>
                            <span style="color:#78716c;font-size:11px;">{{ $bank['count'] }} vouchers · AED {{ number_format($bank['total'], 2) }}</span>
                        </a>
                    @endforeach
                </div>
                <p style="font-size:11px;color:#9ca3af;margin-top:8px;">
                    💡 You can click <strong>Edit Bank</strong> on any voucher row to link it to the correct account code, or use <strong>Batch Fix Legacy Bank Names</strong> in the top right.
                </p>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
