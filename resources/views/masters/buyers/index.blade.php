<x-app-layout>
    <x-slot name="header">Buyers</x-slot>

    <x-ui.card title="Buyer Master" variant="primary">
        <x-slot name="actions">
            @can('buyer.create')
                <a href="{{ route('masters.buyers.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add Buyer
                </a>
            @endcan
        </x-slot>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label small text-body-secondary mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="form-control form-control-sm" placeholder="Code, company, contact or city">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-body-secondary mb-1">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($categories as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-body-secondary mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-secondary text-nowrap d-inline-flex align-items-center flex-shrink-0"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('masters.buyers.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th style="width:100px">Code</th>
                        <th>Company</th>
                        <th>Country</th>
                        <th>Agent</th>
                        <th style="width:110px" class="text-center">Categories</th>
                        <th style="width:110px">Status</th>
                        <th class="text-end" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($buyers as $buyer)
                        <tr>
                            <td class="text-body-secondary">{{ $loop->iteration + ($buyers->currentPage() - 1) * $buyers->perPage() }}</td>
                            <td><span class="badge text-bg-light border font-monospace">{{ $buyer->display_code }}</span></td>
                            <td>
                                <div class="fw-semibold">{{ $buyer->company_name }}</div>
                                @if($buyer->contact_person)
                                    <div class="small text-body-secondary">{{ $buyer->contact_person }}</div>
                                @endif
                            </td>
                            <td class="text-body-secondary">
                                {{ $buyer->country?->name ?: '—' }}
                                @if($buyer->city)
                                    <div class="small">{{ $buyer->city->name }}</div>
                                @endif
                            </td>
                            <td class="text-body-secondary">{{ $buyer->agent?->name ?: '—' }}</td>
                            <td class="text-center text-body-secondary">{{ $buyer->categories_count }}</td>
                            <td>
                                @can('buyer.edit')
                                    <form action="{{ route('masters.buyers.toggle-status', $buyer) }}" method="POST" class="js-confirm"
                                          data-confirm="{{ ($buyer->status === 'active' ? 'Deactivate' : 'Activate') . ' buyer "' . $buyer->company_name . '"?' }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"
                                                data-bs-toggle="tooltip" title="Click to toggle">
                                            <x-ui.status-badge :status="$buyer->status === 'active'" />
                                        </button>
                                    </form>
                                @else
                                    <x-ui.status-badge :status="$buyer->status === 'active'" />
                                @endcan
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('buyer.view')
                                        <a href="{{ route('masters.buyers.show', $buyer) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan
                                    @can('buyer.edit')
                                        <a href="{{ route('masters.buyers.edit', $buyer) }}"
                                           class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('buyer.delete')
                                        <x-ui.delete-form
                                            :action="route('masters.buyers.destroy', $buyer)"
                                            :confirm='"Delete buyer \"" . $buyer->company_name . "\"?"' />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="9" icon="bi-globe-asia-australia"
                                          title="No buyers yet"
                                          message="Add the first buyer — quotations and order confirmations are raised against one." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($buyers->hasPages())
            <div class="mt-3">{{ $buyers->links() }}</div>
        @endif

        <div class="text-body-secondary small mt-2">
            Showing {{ $buyers->firstItem() ?? 0 }}–{{ $buyers->lastItem() ?? 0 }} of {{ $buyers->total() }}
        </div>
    </x-ui.card>
</x-app-layout>
