<x-app-layout>
    <x-slot name="header">Edit Trim / Accessory</x-slot>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <x-ui.card title="Edit Trim / Accessory: {{ $trimAccessory->name }}" variant="primary">
                <form action="{{ route('masters.trim-accessories.update', $trimAccessory) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    @include('masters.trim-accessories._form')

                    <div class="mt-4 d-flex gap-2 justify-content-end">
                        <a href="{{ route('masters.trim-accessories.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Update Trim / Accessory
                        </button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
