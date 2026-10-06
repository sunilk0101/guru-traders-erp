<x-app-layout>
    <x-slot name="header">Edit BOM</x-slot>

    <x-ui.card title="Edit BOM: {{ $bom->name }}" variant="primary">
        <form action="{{ route('masters.boms.update', $bom) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('masters.boms._form')

            <div class="mt-4 d-flex gap-2 justify-content-end">
                <a href="{{ route('masters.boms.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Update BOM
                </button>
            </div>
        </form>
    </x-ui.card>
</x-app-layout>
