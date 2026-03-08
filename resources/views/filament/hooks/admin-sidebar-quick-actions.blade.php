@if(\App\Filament\Resources\TransactionResource::canCreate())
<div class="mb-4 mt-2">
    <x-filament::button
        color="primary"
        icon="heroicon-o-plus-circle"
        href="{{ \App\Filament\Resources\TransactionResource::getUrl('create') }}"
        tag="a"
        class="w-full justify-center"
    >
        New Transaction
    </x-filament::button>
</div>
@endif
