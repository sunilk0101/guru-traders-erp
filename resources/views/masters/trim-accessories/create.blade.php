<x-app-layout>
    <x-slot name="header">Add Trim / Accessory</x-slot>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <x-ui.card title="Create Trim / Accessory" variant="primary">
                <form action="{{ route('masters.trim-accessories.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @include('masters.trim-accessories._form')

                    <div class="mt-4 d-flex gap-2 justify-content-end">
                        <a href="{{ route('masters.trim-accessories.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Save Trim / Accessory
                        </button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
