<x-app-layout>
    <x-slot name="header">Edit Product</x-slot>

    <x-ui.card title="{{ $product->item_group_code }} — {{ $product->name }}" variant="primary">
        <form action="{{ route('masters.products.update', $product) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('masters.products._form')
        </form>
    </x-ui.card>
</x-app-layout>
