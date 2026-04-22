@php
    $fieldLabels = [
        'status'      => 'Status',
        'amount'      => 'Amount',
        'description' => 'Description',
        'payee'       => 'Payee',
        'type'        => 'Type',
        'category_id' => 'Category ID',
        'user_id'     => 'Requester ID',
    ];
    $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));
@endphp

<div class="space-y-4 text-sm p-2">
    {{-- Header --}}
    <div class="flex items-center gap-3 pb-3 border-b border-gray-200 dark:border-gray-700">
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ $record->created_at->format('d M Y, H:i') }} &bull; by <strong>{{ $record->causer?->name ?? 'System' }}</strong>
            </p>
            <span @class([
                'inline-flex px-2 py-0.5 rounded-full text-xs font-semibold mt-1',
                'bg-green-100 text-green-700' => $record->description === 'created',
                'bg-yellow-100 text-yellow-700' => $record->description === 'updated',
                'bg-red-100 text-red-700' => $record->description === 'deleted',
                'bg-gray-100 text-gray-600' => !in_array($record->description, ['created', 'updated', 'deleted']),
            ])>{{ ucfirst($record->description) }}</span>
        </div>
    </div>

    @if(empty($old))
        {{-- Created: just show fields --}}
        <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Field</th>
                        <th class="px-4 py-2 text-left text-green-600 dark:text-green-400 font-medium">Value Set</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($new as $key => $val)
                        <tr>
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300 font-medium">{{ $fieldLabels[$key] ?? ucwords(str_replace('_', ' ', $key)) }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-white">{{ is_null($val) ? '—' : $val }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        {{-- Updated: side-by-side diff --}}
        <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium w-1/4">Field</th>
                        <th class="px-4 py-2 text-left text-red-600 dark:text-red-400 font-medium w-3/8">Before</th>
                        <th class="px-4 py-2 text-left text-green-600 dark:text-green-400 font-medium w-3/8">After</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($allKeys as $key)
                        @php
                            $oldVal = array_key_exists($key, $old) ? ($old[$key] ?? '—') : '(not set)';
                            $newVal = array_key_exists($key, $new) ? ($new[$key] ?? '—') : '(not set)';
                            $changed = $oldVal != $newVal;
                        @endphp
                        <tr @class(['bg-yellow-50 dark:bg-yellow-900/20' => $changed])>
                            <td class="px-4 py-2 font-medium text-gray-600 dark:text-gray-300">
                                {{ $fieldLabels[$key] ?? ucwords(str_replace('_', ' ', $key)) }}
                                @if($changed)
                                    <span class="ml-1 text-xs text-yellow-600 dark:text-yellow-400">✏️</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 @if($changed) text-red-700 dark:text-red-400 line-through @else text-gray-500 dark:text-gray-400 @endif">
                                {{ $oldVal }}
                            </td>
                            <td class="px-4 py-2 @if($changed) text-green-700 dark:text-green-400 font-semibold @else text-gray-500 dark:text-gray-400 @endif">
                                {{ $newVal }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if(collect($allKeys)->every(fn($k) => ($old[$k] ?? null) == ($new[$k] ?? null)))
            <p class="text-center text-gray-400 text-xs mt-2">No field differences recorded.</p>
        @endif
    @endif
</div>
