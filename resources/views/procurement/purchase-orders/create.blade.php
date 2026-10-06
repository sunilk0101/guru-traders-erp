<x-app-layout>
    <x-slot name="header">New Purchase Order</x-slot>

    <x-ui.card title="New Purchase Order" variant="primary">
        <x-slot name="actions">
            <a href="{{ route('procurement.purchase-orders.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </x-slot>

        <form action="{{ route('procurement.purchase-orders.store') }}" method="POST" id="po-form" enctype="multipart/form-data">
            @csrf
            @include('procurement.purchase-orders._form', ['purchaseOrder' => null])
        </form>
    </x-ui.card>
</x-app-layout>
