<x-app-layout>
    <x-slot name="header">Categories</x-slot>

    <x-ui.card title="Category Master" variant="primary">
        <x-slot name="actions">
            @can('category.create')
                <a href="{{ route('masters.categories.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add Category
                </a>
            @endcan
        </x-slot>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-5">
                <label class="form-label small text-body-secondary mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="form-control form-control-sm" placeholder="Code, name or remarks">
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
                <a href="{{ route('masters.categories.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th style="width:120px">Code</th>
                        <th>Category Name</th>
                        <th>Order Formats</th>
                        <th style="width:100px" class="text-center">Products</th>
                        <th style="width:110px">Status</th>
                        <th class="text-end" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr>
                            <td class="text-body-secondary">{{ $loop->iteration + ($categories->currentPage() - 1) * $categories->perPage() }}</td>
                            <td><span class="badge text-bg-light border font-monospace">{{ $category->code }}</span></td>
                            <td class="fw-semibold">{{ $category->name }}</td>
                            <td class="text-body-secondary">
                                @forelse($category->formats as $format)
                                    <span class="badge text-bg-light border me-1 mb-1">{{ $format->name }}</span>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td class="text-center text-body-secondary">{{ $category->products_count }}</td>
                            <td>
                                @can('category.edit')
                                    <form action="{{ route('masters.categories.toggle-status', $category) }}" method="POST" class="js-confirm"
                                          data-confirm="{{ ($category->status === 'active' ? 'Deactivate' : 'Activate') . ' category "' . $category->name . '"?' }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"
                                                data-bs-toggle="tooltip" title="Click to toggle">
                                            <x-ui.status-badge :status="$category->status === 'active'" />
                                        </button>
                                    </form>
                                @else
                                    <x-ui.status-badge :status="$category->status === 'active'" />
                                @endcan
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('category.view')
                                        <a href="{{ route('masters.categories.show', $category) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan
                                    @can('category.edit')
                                        <a href="{{ route('masters.categories.edit', $category) }}"
                                           class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('category.delete')
                                        <x-ui.delete-form
                                            :action="route('masters.categories.destroy', $category)"
                                            :confirm='"Delete category \"" . $category->name . "\"?"'
                                            :disabled="$category->products_count > 0"
                                            disabled-reason="In use by products" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="7" icon="bi-tags"
                                          title="No categories yet"
                                          message="Add the first category — products and jobbers are linked to it." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($categories->hasPages())
            <div class="mt-3">{{ $categories->links('pagination::bootstrap-5') }}</div>
        @endif

        <div class="text-body-secondary small mt-2">
            Showing {{ $categories->firstItem() ?? 0 }}–{{ $categories->lastItem() ?? 0 }} of {{ $categories->total() }}
        </div>
    </x-ui.card>
</x-app-layout>
