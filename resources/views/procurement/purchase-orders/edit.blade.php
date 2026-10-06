<x-app-layout>
    <x-slot name="header">Edit Purchase Order</x-slot>

    <x-ui.card title="{{ $purchaseOrder->po_num }}" variant="primary">
        <x-slot name="actions">
            <a href="{{ route('procurement.purchase-orders.show', $purchaseOrder) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-eye me-1"></i> View
            </a>
        </x-slot>

        <form action="{{ route('procurement.purchase-orders.update', $purchaseOrder) }}" method="POST" id="po-form" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('procurement.purchase-orders._form')
        </form>
    </x-ui.card>
</x-app-layout>
