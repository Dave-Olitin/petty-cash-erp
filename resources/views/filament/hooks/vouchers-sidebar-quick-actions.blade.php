@if(\App\Filament\Vouchers\Resources\VoucherResource::canCreate())
<div class="mb-4 mt-2">
    <x-filament::button
        color="primary"
        icon="heroicon-o-plus-circle"
        href="{{ \App\Filament\Vouchers\Resources\VoucherResource::getUrl('create') }}"
        tag="a"
        class="w-full justify-center"
    >
        <span x-data="{}" x-show="$store.sidebar.isOpen" x-transition class="whitespace-nowrap">New Voucher</span>
    </x-filament::button>
</div>
@endif
