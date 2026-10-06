<x-app-layout>
    <x-slot name="header">Order Formats</x-slot>

    <x-ui.card title="Order Format Master" variant="primary">
        <x-slot name="actions">
            @can('po-format.create')
                <a href="{{ route('masters.formats.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add Format
                </a>
            @endcan
        </x-slot>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-5">
                <label class="form-label small text-body-secondary mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="form-control form-control-sm" placeholder="Format name or description">
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
                <a href="{{ route('masters.formats.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Format Name</th>
                        <th style="width:150px">Multi-colour</th>
                        <th style="width:90px" class="text-center">Units</th>
                        <th style="width:110px" class="text-center">Categories</th>
                        <th style="width:90px" class="text-center">Images</th>
                        <th style="width:110px">Status</th>
                        <th class="text-end sticky-actions-col" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($formats as $format)
                        <tr>
                            <td class="text-body-secondary">{{ $loop->iteration + ($formats->currentPage() - 1) * $formats->perPage() }}</td>
                            <td>
                                <div class="fw-semibold">{{ $format->name }}</div>
                                @if($format->description)
                                    <div class="small text-body-secondary">{{ Str::limit($format->description, 70) }}</div>
                                @endif
                            </td>
                            <td>
                                {{-- Changes the shape of every order row using
                                     the format, so it belongs in the list. --}}
                                <span class="badge {{ $format->allow_multiple_colours ? 'text-bg-primary' : 'text-bg-light border' }}">
                                    {{ $format->allow_multiple_colours ? 'Several per item' : 'One per row' }}
                                </span>
                            </td>
                            <td class="text-center text-body-secondary">{{ $format->units_count }}</td>
                            <td class="text-center text-body-secondary">{{ $format->categories_count }}</td>
                            <td class="text-center text-body-secondary">{{ $format->images_count }}</td>
                            <td>
                                @can('po-format.edit')
                                    <form action="{{ route('masters.formats.toggle-status', $format) }}" method="POST" class="js-confirm"
                                          data-confirm="{{ ($format->status === 'active' ? 'Deactivate' : 'Activate') . ' order format "' . $format->name . '"?' }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"
                                                data-bs-toggle="tooltip" title="Click to toggle">
                                            <x-ui.status-badge :status="$format->status === 'active'" />
                                        </button>
                                    </form>
                                @else
                                    <x-ui.status-badge :status="$format->status === 'active'" />
                                @endcan
                            </td>
                            <td class="text-end sticky-actions-col">
                                <div class="btn-group btn-group-sm">
                                    @can('po-format.view')
                                        <a href="{{ route('masters.formats.show', $format) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan
                                    @can('po-format.edit')
                                        <a href="{{ route('masters.formats.edit', $format) }}"
                                           class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('po-format.delete')
                                        {{-- L-04: DocumentFormatService::canDelete() already blocks this
                                             server-side when categories reference the format — the button
                                             just never reflected that in the UI the way Categories' own
                                             delete button does (:disabled based on products_count). --}}
                                        <x-ui.delete-form
                                            :action="route('masters.formats.destroy', $format)"
                                            :confirm='"Delete order format \"" . $format->name . "\"?"'
                                            :disabled="$format->categories_count > 0"
                                            disabled-reason="In use by categories" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="8" icon="bi-file-earmark-ruled"
                                          title="No order formats yet"
                                          message="A format decides the shape of the item table on every inquiry, OC and PO." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($formats->hasPages())
            <div class="mt-3">{{ $formats->links() }}</div>
        @endif

        <div class="text-body-secondary small mt-2">
            Showing {{ $formats->firstItem() ?? 0 }}–{{ $formats->lastItem() ?? 0 }} of {{ $formats->total() }}
        </div>
    </x-ui.card>
</x-app-layout>
