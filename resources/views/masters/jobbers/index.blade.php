@php
    use App\Models\Supplier;
@endphp

<x-app-layout>
    <x-slot name="header">Jobbers</x-slot>

    <x-ui.card title="Jobber Master" variant="primary">
        <x-slot name="actions">
            @can('jobber.create')
                <a href="{{ route('masters.jobbers.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add Jobber
                </a>
            @elsecan('supplier.create')
                <a href="{{ route('masters.jobbers.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add Jobber
                </a>
            @endcan
        </x-slot>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label small text-body-secondary mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="form-control form-control-sm" placeholder="Code, company, GST, contact or city">
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

            <div class="col-md-3">
                <label class="form-label small text-body-secondary mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-secondary text-nowrap d-inline-flex align-items-center flex-shrink-0"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('masters.jobbers.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
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
                        <th class="text-end" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jobbers as $jobber)
                        <tr>
                            <td class="text-body-secondary">{{ $loop->iteration + ($jobbers->currentPage() - 1) * $jobbers->perPage() }}</td>
                            <td><span class="badge text-bg-light border font-monospace">{{ $jobber->display_code }}</span></td>
                            <td>
                                <div class="fw-semibold">{{ $jobber->company_name }}</div>
                                @if($jobber->supplierType)
                                    <div class="small text-body-secondary">{{ $jobber->supplierType->name }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge text-bg-light border">
                                    {{ ['supplier' => 'Trading', 'jobber' => 'Jobwork', 'both' => 'Both'][$jobber->party_type] }}
                                </span>
                            </td>
                            <td class="text-body-secondary">
                                {{ $jobber->primaryContact?->name ?: '—' }}
                                @if($jobber->primaryContact?->designation)
                                    <div class="small">{{ $jobber->primaryContact->designation->name }}</div>
                                @endif
                            </td>
                            <td class="text-body-secondary">
                                {{ $jobber->city?->name ?: '—' }}
                                @if($jobber->state)
                                    <div class="small">{{ $jobber->state->name }}</div>
                                @endif
                            </td>
                            <td class="text-end text-body-secondary">{{ $jobber->credit_terms_label ?: '—' }}</td>
                            <td class="text-center text-body-secondary">{{ $jobber->categories_count }}</td>
                            <td>
                                @if(auth()->user()?->can('jobber.edit') || auth()->user()?->can('supplier.edit'))
                                    <form action="{{ route('masters.jobbers.toggle-status', $jobber) }}" method="POST" class="js-confirm"
                                          data-confirm="{{ ($jobber->status === 'active' ? 'Deactivate' : 'Activate') . ' jobber "' . $jobber->company_name . '"?' }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"
                                                data-bs-toggle="tooltip" title="Click to toggle">
                                            <x-ui.status-badge :status="$jobber->status === 'active'" />
                                        </button>
                                    </form>
                                @else
                                    <x-ui.status-badge :status="$jobber->status === 'active'" />
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @if(auth()->user()?->can('jobber.view') || auth()->user()?->can('supplier.view'))
                                        <a href="{{ route('masters.jobbers.show', $jobber) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endif
                                    @if(auth()->user()?->can('jobber.edit') || auth()->user()?->can('supplier.edit'))
                                        <a href="{{ route('masters.jobbers.edit', $jobber) }}"
                                           class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                    @if(auth()->user()?->can('jobber.delete') || auth()->user()?->can('supplier.delete'))
                                        <x-ui.delete-form
                                            :action="route('masters.jobbers.destroy', $jobber)"
                                            :confirm='"Delete jobber \"" . $jobber->company_name . "\"?"' />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="10" icon="bi-tools"
                                          title="No jobbers yet"
                                          message="Add the first jobber — work orders are assigned to them." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($jobbers->hasPages())
            <div class="mt-3">{{ $jobbers->links() }}</div>
        @endif

        <div class="text-body-secondary small mt-2">
            Showing {{ $jobbers->firstItem() ?? 0 }}–{{ $jobbers->lastItem() ?? 0 }} of {{ $jobbers->total() }}
        </div>
    </x-ui.card>
</x-app-layout>
