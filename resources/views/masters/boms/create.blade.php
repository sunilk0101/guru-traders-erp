<x-app-layout>
    <x-slot name="header">Add BOM</x-slot>

    <x-ui.card title="Create BOM" variant="primary">
        <form action="{{ route('masters.boms.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @include('masters.boms._form')

            <div class="mt-4 d-flex gap-2 justify-content-end">
                <a href="{{ route('masters.boms.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Save BOM
                </button>
            </div>
        </form>
    </x-ui.card>
</x-app-layout>
