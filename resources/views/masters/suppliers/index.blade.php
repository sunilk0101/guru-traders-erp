@php
    use App\Models\Supplier;
@endphp

<x-app-layout>
    <x-slot name="header">Suppliers</x-slot>

    <x-ui.card title="Supplier Master" variant="primary">
        <x-slot name="actions">
            @can('supplier.create')
                <a href="{{ route('masters.suppliers.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add Supplier
                </a>
            @endcan
        </x-slot>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label small text-body-secondary mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="form-control form-control-sm" placeholder="Code, company, GST, contact or city">
            </div>

            {{-- One table, two kinds of party — DATABASE_SCHEMA.md §5. Until the
                 Jobber screen is split out this filter is how you get one or
                 the other. A party marked "Both" appears under either. --}}
            <div class="col-md-3">
                <label class="form-label small text-body-secondary mb-1">Party Type</label>
                <select name="party_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(Supplier::PARTY_TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['party_type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
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

            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-secondary text-nowrap d-inline-flex align-items-center flex-shrink-0"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('masters.suppliers.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th style="width:100px">Code</th>
                        <th>Company</th>
                        <th style="width:110px">Type</th>
                        <th>Contact</th>
                        <th>City</th>
                        <th style="width:100px" class="text-end">Credit</th>
                        <th style="width:100px" class="text-center">Categories</th>
                        <th style="width:110px">Status</th>
                        <th class="text-end sticky-actions-col" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $supplier)
                        <tr>
                            <td class="text-body-secondary">{{ $loop->iteration + ($suppliers->currentPage() - 1) * $suppliers->perPage() }}</td>
                            <td><span class="badge text-bg-light border font-monospace">{{ $supplier->display_code }}</span></td>
                            <td>
                                <div class="fw-semibold">{{ $supplier->company_name }}</div>
                                @if($supplier->supplierType)
                                    <div class="small text-body-secondary">{{ $supplier->supplierType->name }}</div>
                                @endif
                            </td>
                            <td>
                                {{-- Jobwork and trading are different businesses; the
                                     list has to say which this row is at a glance. --}}
                                <span class="badge text-bg-light border">
                                    {{ ['supplier' => 'Trading', 'jobber' => 'Jobwork', 'both' => 'Both'][$supplier->party_type] }}
                                </span>
                            </td>
                            <td class="text-body-secondary">
                                {{ $supplier->primaryContact?->name ?: '—' }}
                                @if($supplier->primaryContact?->designation)
                                    <div class="small">{{ $supplier->primaryContact->designation->name }}</div>
                                @endif
                            </td>
                            <td class="text-body-secondary">
                                {{ $supplier->city?->name ?: '—' }}
                                @if($supplier->state)
                                    <div class="small">{{ $supplier->state->name }}</div>
                                @endif
                            </td>
                            <td class="text-end text-body-secondary">{{ $supplier->credit_terms_label ?: '—' }}</td>
                            <td class="text-center text-body-secondary">{{ $supplier->categories_count }}</td>
                            <td>
                                @can('supplier.edit')
                                    <form action="{{ route('masters.suppliers.toggle-status', $supplier) }}" method="POST" class="js-confirm"
                                          data-confirm="{{ ($supplier->status === 'active' ? 'Deactivate' : 'Activate') . ' supplier "' . $supplier->company_name . '"?' }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"
                                                data-bs-toggle="tooltip" title="Click to toggle">
                                            <x-ui.status-badge :status="$supplier->status === 'active'" />
                                        </button>
                                    </form>
                                @else
                                    <x-ui.status-badge :status="$supplier->status === 'active'" />
                                @endcan
                            </td>
                            <td class="text-end sticky-actions-col">
                                <div class="btn-group btn-group-sm">
                                    @can('supplier.view')
                                        <a href="{{ route('masters.suppliers.show', $supplier) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan
                                    @can('supplier.edit')
                                        <a href="{{ route('masters.suppliers.edit', $supplier) }}"
                                           class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('supplier.delete')
                                        <x-ui.delete-form
                                            :action="route('masters.suppliers.destroy', $supplier)"
                                            :confirm='"Delete supplier \"" . $supplier->company_name . "\"?"' />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="10" icon="bi-truck"
                                          title="No suppliers yet"
                                          message="Add the first supplier or jobber — a purchase order is raised against one." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($suppliers->hasPages())
            <div class="mt-3">{{ $suppliers->links() }}</div>
        @endif

        <div class="text-body-secondary small mt-2">
            Showing {{ $suppliers->firstItem() ?? 0 }}–{{ $suppliers->lastItem() ?? 0 }} of {{ $suppliers->total() }}
        </div>
    </x-ui.card>
</x-app-layout>
