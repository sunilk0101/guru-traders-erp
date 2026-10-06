<x-app-layout>
    <x-slot name="header">BOM</x-slot>

    <x-ui.card title="BOM Master" variant="primary">
        <x-slot name="actions">
            @can('bom.create')
                <a href="{{ route('masters.boms.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add BOM
                </a>
            @endcan
        </x-slot>

        <p class="text-body-secondary small mb-3">
            Named pack of trims and accessories, with a photo on each line (Excel-style image column).
            Pick a BOM on a Product, on Inquiry costing, and reuse the same photos.
        </p>

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
                <a href="{{ route('masters.boms.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Name</th>
                        <th class="text-end" style="width:90px">Lines</th>
                        <th class="text-end" style="width:120px">Pack total</th>
                        <th>Remarks</th>
                        <th style="width:110px">Status</th>
                        <th class="text-end" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($boms as $bom)
                        <tr>
                            <td class="text-body-secondary">{{ $loop->iteration + ($boms->currentPage() - 1) * $boms->perPage() }}</td>
                            <td class="fw-semibold">{{ $bom->name }}</td>
                            <td class="text-end">{{ $bom->lines_count }}</td>
                            <td class="text-end font-monospace">₹{{ number_format($bom->lineTotal(), 2) }}</td>
                            <td class="text-body-secondary">{{ $bom->remarks ?? '—' }}</td>
                            <td>
                                @can('bom.edit')
                                    <form action="{{ route('masters.boms.toggle-status', $bom) }}" method="POST" class="js-confirm"
                                          data-confirm="{{ ($bom->status === 'active' ? 'Deactivate' : 'Activate') . ' BOM "' . $bom->name . '"?' }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"
                                                data-bs-toggle="tooltip" title="Click to toggle">
                                            <x-ui.status-badge :status="$bom->status === 'active'" />
                                        </button>
                                    </form>
                                @else
                                    <x-ui.status-badge :status="$bom->status === 'active'" />
                                @endcan
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('bom.view')
                                        <a href="{{ route('masters.boms.show', $bom) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan
                                    @can('bom.edit')
                                        <a href="{{ route('masters.boms.edit', $bom) }}"
                                           class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('bom.delete')
                                        <x-ui.delete-form
                                            :action="route('masters.boms.destroy', $bom)"
                                            :confirm='"Delete BOM \"" . $bom->name . "\"?"' />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="7" icon="bi-diagram-3"
                                          title="No BOMs yet"
                                          message="Add the first BOM pack." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($boms->hasPages())
            <div class="mt-3">{{ $boms->links('pagination::bootstrap-5') }}</div>
        @endif

        <div class="text-body-secondary small mt-2">
            Showing {{ $boms->firstItem() ?? 0 }}–{{ $boms->lastItem() ?? 0 }} of {{ $boms->total() }}
        </div>
    </x-ui.card>
</x-app-layout>
