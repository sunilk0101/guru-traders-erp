<x-app-layout>
    <x-slot name="header">Trim / Accessory Details</x-slot>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <x-ui.card title="Trim / Accessory: {{ $trimAccessory->name }}" variant="primary">
                <x-slot name="actions">
                    @can('trim-accessory.edit')
                        <a href="{{ route('masters.trim-accessories.edit', $trimAccessory) }}" class="btn btn-sm btn-primary">
                            <i class="bi bi-pencil me-1"></i> Edit
                        </a>
                    @endcan
                    <a href="{{ route('masters.trim-accessories.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back to List
                    </a>
                </x-slot>

                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <tbody>
                            <tr>
                                <th style="width:200px" class="bg-light">Photo</th>
                                <td>
                                    @if($trimAccessory->image_url)
                                        <img src="{{ $trimAccessory->image_url }}" alt="{{ $trimAccessory->name }}"
                                             class="rounded border" style="width:72px;height:72px;object-fit:cover">
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="bg-light">Trim / Accessory Name</th>
                                <td>{{ $trimAccessory->name }}</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Status</th>
                                <td><x-ui.status-badge :status="$trimAccessory->status === 'active'" /></td>
                            </tr>
                            <tr>
                                <th class="bg-light">Remarks</th>
                                <td>{{ $trimAccessory->remarks ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Created By</th>
                                <td>{{ $trimAccessory->creator?->name ?? '—' }} ({{ $trimAccessory->created_at?->format('d M Y H:i') ?? '—' }})</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Updated By</th>
                                <td>{{ $trimAccessory->updater?->name ?? '—' }} ({{ $trimAccessory->updated_at?->format('d M Y H:i') ?? '—' }})</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
