<x-app-layout>
    <x-slot name="header">Trim / Accessories</x-slot>

    <x-ui.card title="Trim / Accessory Master" variant="primary">
        <x-slot name="actions">
            @can('trim-accessory.create')
                <a href="{{ route('masters.trim-accessories.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add Trim / Accessory
                </a>
            @endcan
        </x-slot>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-5">
                <label class="form-label small text-body-secondary mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="form-control form-control-sm" placeholder="Name or remarks">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-body-secondary mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-secondary text-nowrap d-inline-flex align-items-center flex-shrink-0"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('masters.trim-accessories.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th style="width:60px">Photo</th>
                        <th>Name</th>
                        <th>Remarks</th>
                        <th style="width:110px">Status</th>
                        <th class="text-end" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trimAccessories as $trimAccessory)
                        <tr>
                            <td class="text-body-secondary">{{ $loop->iteration + ($trimAccessories->currentPage() - 1) * $trimAccessories->perPage() }}</td>
                            <td>
                                @if($trimAccessory->image_url)
                                    <img src="{{ $trimAccessory->image_url }}" alt="{{ $trimAccessory->name }}"
                                         class="rounded border" style="width:32px;height:32px;object-fit:cover">
                                @else
                                    <span class="text-body-secondary small">—</span>
                                @endif
                            </td>
                            <td class="fw-semibold">{{ $trimAccessory->name }}</td>
                            <td class="text-body-secondary">{{ $trimAccessory->remarks ?? '—' }}</td>
                            <td>
                                @can('trim-accessory.edit')
                                    <form action="{{ route('masters.trim-accessories.toggle-status', $trimAccessory) }}" method="POST" class="js-confirm"
                                          data-confirm="{{ ($trimAccessory->status === 'active' ? 'Deactivate' : 'Activate') . ' Trim / Accessory "' . $trimAccessory->name . '"?' }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"
                                                data-bs-toggle="tooltip" title="Click to toggle">
                                            <x-ui.status-badge :status="$trimAccessory->status === 'active'" />
                                        </button>
                                    </form>
                                @else
                                    <x-ui.status-badge :status="$trimAccessory->status === 'active'" />
                                @endcan
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('trim-accessory.view')
                                        <a href="{{ route('masters.trim-accessories.show', $trimAccessory) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan
                                    @can('trim-accessory.edit')
                                        <a href="{{ route('masters.trim-accessories.edit', $trimAccessory) }}"
                                           class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('trim-accessory.delete')
                                        <x-ui.delete-form
                                            :action="route('masters.trim-accessories.destroy', $trimAccessory)"
                                            :confirm='"Delete Trim / Accessory \"" . $trimAccessory->name . "\"?"' />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="6" icon="bi-scissors"
                                          title="No Trim / Accessories yet"
                                          message="Add the first trim or accessory entry." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($trimAccessories->hasPages())
            <div class="mt-3">{{ $trimAccessories->links('pagination::bootstrap-5') }}</div>
        @endif

        <div class="text-body-secondary small mt-2">
            Showing {{ $trimAccessories->firstItem() ?? 0 }}–{{ $trimAccessories->lastItem() ?? 0 }} of {{ $trimAccessories->total() }}
        </div>
    </x-ui.card>
</x-app-layout>
