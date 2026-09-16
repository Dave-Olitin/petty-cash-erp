<x-filament-widgets::widget>
    @php
        $banks  = $this->getBankSummaryData();
        $validBanks = array_values(array_filter($banks, fn($b) => $b['is_valid_code']));

        $accentColors = ['#2563eb', '#059669', '#7c3aed', '#0891b2', '#c2410c'];
    @endphp

    <div class="p-4">

        {{-- Header --}}
        <div class="flex items-center gap-2 mb-4">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Bank Accounts</span>
            <span class="text-xs text-gray-400">({{ count($validBanks) }} accounts)</span>
            @if (request()->has('tableFilters'))
                <a href="{{ route('filament.vouchers.resources.bank-reconciliations.index') }}"
                   class="ml-auto text-xs text-indigo-600 hover:underline font-medium">
                    ✕ Clear filter
                </a>
            @endif
        </div>

        {{-- Cards grid --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($validBanks as $idx => $bank)
                @php
                    $isSelected = request()->input('tableFilters.bank.value') === $bank['bank'];
                    $accent     = $accentColors[$idx % count($accentColors)];
                    $filterUrl  = route('filament.vouchers.resources.bank-reconciliations.index')
                                  . '?tableFilters[bank][value]=' . urlencode($bank['bank']);

                    // Derive a short readable bank label
                    // e.g. "CASH IN BANK|ADCB (TG) - 94978..." → "ADCB (TG)"
                    $rawName = $bank['account_name'] ?? $bank['bank'];
                    $shortName = preg_replace('/^CASH\s+IN\s+BANK[I\|\s\-]*/i', '', $rawName);
                    $shortName = preg_replace('/\s*[-]\s*\d[\d\s]*$/', '', $shortName);
                    $shortName = trim($shortName) ?: $rawName;
                @endphp

                <a href="{{ $filterUrl }}"
                   style="
                       display:block;
                       border: 1.5px solid {{ $isSelected ? $accent : '#e5e7eb' }};
                       border-left: 4px solid {{ $accent }};
                       border-radius: 10px;
                       padding: 14px 16px;
                       background: {{ $isSelected ? 'rgba(0,0,0,0.03)' : '#fff' }};
                       text-decoration: none;
                       transition: box-shadow 0.15s, border-color 0.15s;
                   "
                   onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,0.08)'"
                   onmouseout="this.style.boxShadow='none'">

                    {{-- Bank name --}}
                    <div style="font-size:13px;font-weight:700;color:#111827;margin-bottom:2px;line-height:1.3;">
                        {{ Str::limit($shortName, 26) }}
                    </div>

                    {{-- Account code --}}
                    <div style="font-family:monospace;font-size:10px;color:#9ca3af;margin-bottom:10px;">
                        {{ $bank['bank'] }}
                    </div>

                    {{-- Stats row --}}
                    <div style="display:flex;align-items:flex-end;justify-content:space-between;">
                        <div>
                            <div style="font-size:15px;font-weight:800;color:#111827;line-height:1;">
                                AED {{ number_format($bank['total'], 2) }}
                            </div>
                            <div style="font-size:10px;color:#6b7280;margin-top:2px;">
                                {{ $bank['count'] }} payment{{ $bank['count'] !== 1 ? 's' : '' }}
                            </div>
                        </div>
                        @if ($isSelected)
                            <span style="font-size:10px;font-weight:700;color:{{ $accent }};letter-spacing:0.5px;">
                                ACTIVE ●
                            </span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
