<x-app-layout>
    <x-slot name="header">Agents</x-slot>

    <x-ui.card title="Agent Master" variant="primary">
        <x-slot name="actions">
            @can('agent.create')
                <a href="{{ route('masters.agents.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add Agent
                </a>
            @endcan
        </x-slot>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label small text-body-secondary mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="form-control form-control-sm" placeholder="Code, name, city, phone or remarks">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-body-secondary mb-1">Agent Type</label>
                <select name="agent_type" class="form-select form-select-sm">
                    <option value="">All types</option>
                    @foreach (\App\Models\Agent::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['agent_type'] ?? '') === $value)>{{ $label }}</option>
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
                <a href="{{ route('masters.agents.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th style="width:130px">
                            <a href="{{ route('masters.agents.index', array_merge(request()->query(), ['sort' => 'display_code', 'direction' => ($filters['sort'] ?? '') === 'display_code' && ($filters['direction'] ?? '') === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                Display Code
                                @if(($filters['sort'] ?? '') === 'display_code')
                                    <i class="bi bi-sort-alpha-{{ ($filters['direction'] ?? '') === 'asc' ? 'down' : 'up' }} ms-1"></i>
                                @else
                                    <i class="bi bi-arrow-down-up text-muted small ms-1" style="font-size: 0.75rem;"></i>
                                @endif
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('masters.agents.index', array_merge(request()->query(), ['sort' => 'name', 'direction' => ($filters['sort'] ?? '') === 'name' && ($filters['direction'] ?? '') === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                Agent Name
                                @if(($filters['sort'] ?? '') === 'name')
                                    <i class="bi bi-sort-alpha-{{ ($filters['direction'] ?? '') === 'asc' ? 'down' : 'up' }} ms-1"></i>
                                @else
                                    <i class="bi bi-arrow-down-up text-muted small ms-1" style="font-size: 0.75rem;"></i>
                                @endif
                            </a>
                        </th>
                        <th>Agent Type</th>
                        <th style="width:110px">City</th>
                        <th>Commission</th>
                        <th style="width:130px">Paid By</th>
                        <th style="width:120px">
                            <a href="{{ route('masters.agents.index', array_merge(request()->query(), ['sort' => 'status', 'direction' => ($filters['sort'] ?? '') === 'status' && ($filters['direction'] ?? '') === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                Status
                                @if(($filters['sort'] ?? '') === 'status')
                                    <i class="bi bi-sort-alpha-{{ ($filters['direction'] ?? '') === 'asc' ? 'down' : 'up' }} ms-1"></i>
                                @else
                                    <i class="bi bi-arrow-down-up text-muted small ms-1" style="font-size: 0.75rem;"></i>
                                @endif
                            </a>
                        </th>
                        <th class="text-end" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($agents as $agent)
                        <tr>
                            <td class="text-body-secondary">{{ $loop->iteration + ($agents->currentPage() - 1) * $agents->perPage() }}</td>
                            <td><span class="badge text-bg-light border font-monospace">{{ $agent->display_code }}</span></td>
                            <td class="fw-semibold">{{ $agent->name }}</td>
                            <td>
                                <span class="badge text-bg-secondary text-uppercase">{{ $agent->agent_type }}</span>
                            </td>
                            <td class="text-body-secondary">{{ $agent->city ?: '—' }}</td>
                            <td>
                                {{-- Every entry, not just the first: a "2% plus ₹5 a piece"
                                     arrangement reads as an error if only half of it shows. --}}
                                @forelse($agent->commissions as $commission)
                                    <span class="badge text-bg-light border font-monospace me-1">{{ $commission->label }}</span>
                                @empty
                                    <span class="text-body-secondary">—</span>
                                @endforelse
                                @if($agent->commissionBasis)
                                    <div class="small text-body-secondary">on {{ $agent->commissionBasis->name }}</div>
                                @endif
                            </td>
                            <td class="text-body-secondary small">
                                {{ \App\Models\Agent::COMMISSION_PAYERS[$agent->commission_paid_by] ?? '—' }}
                            </td>
                            <td>
                                @can('agent.edit')
                                    <form action="{{ route('masters.agents.toggle-status', $agent) }}" method="POST" class="js-confirm"
                                          data-confirm="{{ ($agent->status === 'active' ? 'Deactivate' : 'Activate') . ' agent "' . $agent->name . '"?' }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"
                                                data-bs-toggle="tooltip" title="Click to toggle">
                                            <x-ui.status-badge :status="$agent->status === 'active'" />
                                        </button>
                                    </form>
                                @else
                                    <x-ui.status-badge :status="$agent->status === 'active'" />
                                @endcan
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('agent.view')
                                        <a href="{{ route('masters.agents.show', $agent) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan
                                    @can('agent.edit')
                                        <a href="{{ route('masters.agents.edit', $agent) }}"
                                           class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('agent.delete')
                                        <x-ui.delete-form
                                            :action="route('masters.agents.destroy', $agent)"
                                            :confirm='"Delete agent \"" . $agent->name . "\"?"' />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <div class="text-body-secondary small mb-1">
                                    <i class="bi bi-person-badge fs-2 d-block mb-2 text-secondary"></i>
                                    No agents found.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($agents->hasPages())
            <div class="mt-3">{{ $agents->links('pagination::bootstrap-5') }}</div>
        @endif

        <div class="text-body-secondary small mt-2">
            Showing {{ $agents->firstItem() ?? 0 }}–{{ $agents->lastItem() ?? 0 }} of {{ $agents->total() }}
        </div>
    </x-ui.card>
</x-app-layout>
