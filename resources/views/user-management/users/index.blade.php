<x-app-layout>
    <x-slot name="header">Users</x-slot>

    <x-ui.card title="Users" variant="primary">
        <x-slot name="actions">
            @can('user.create')
                <a href="{{ route('user-management.users.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add User
                </a>
            @endcan
        </x-slot>

        {{-- Filters --}}
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label small text-body-secondary mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="form-control form-control-sm" placeholder="Name, email or phone">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-body-secondary mb-1">Role</label>
                <select name="role" class="form-select form-select-sm">
                    <option value="">All roles</option>
                    @foreach($roles as $roleName)
                        <option value="{{ $roleName }}" @selected(($filters['role'] ?? '') === $roleName)>{{ $roleName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-body-secondary mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="1" @selected(($filters['status'] ?? null) === '1')>Active</option>
                    <option value="0" @selected(($filters['status'] ?? null) === '0')>Inactive</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-secondary text-nowrap d-inline-flex align-items-center flex-shrink-0"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('user-management.users.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Roles</th>
                        <th style="width:110px">Status</th>
                        <th class="text-end" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td class="text-body-secondary">{{ $loop->iteration + ($users->currentPage() - 1) * $users->perPage() }}</td>
                            <td>
                                <span class="fw-semibold">{{ $user->name }}</span>
                                @if($user->isProtected())
                                    <i class="bi bi-shield-lock-fill text-warning ms-1"
                                       data-bs-toggle="tooltip" title="Protected system account"></i>
                                @endif
                            </td>
                            <td class="text-body-secondary">{{ $user->email }}</td>
                            <td class="text-body-secondary">{{ $user->phone ?: '—' }}</td>
                            <td>
                                @forelse($user->roles as $role)
                                    <span class="badge text-bg-info-subtle text-info-emphasis border border-info-subtle">{{ $role->name }}</span>
                                @empty
                                    <span class="text-body-secondary small">No role</span>
                                @endforelse
                            </td>
                            <td>
                                @can('user.edit')
                                    @if($user->isProtected() || $user->is(auth()->user()))
                                        <x-ui.status-badge :status="$user->status" />
                                    @else
                                        <form action="{{ route('user-management.users.toggle-status', $user) }}" method="POST" class="js-confirm"
                                            data-confirm="{{ ($user->status ? 'Deactivate' : 'Activate') . ' user "' . $user->name . '"?' }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"
                                                    data-bs-toggle="tooltip" title="Click to toggle">
                                                <x-ui.status-badge :status="$user->status" />
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    <x-ui.status-badge :status="$user->status" />
                                @endcan
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('user.view')
                                        <a href="{{ route('user-management.users.show', $user) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan
                                    @can('user.edit')
                                        <a href="{{ route('user-management.users.edit', $user) }}"
                                           class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('user.delete')
                                        <x-ui.delete-form
                                            :action="route('user-management.users.destroy', $user)"
                                            :confirm='"Delete user \"" . $user->name . "\"?"'
                                            :disabled="$user->isProtected() || $user->is(auth()->user())"
                                            :disabled-reason="$user->isProtected() ? 'Protected system account' : 'You cannot delete yourself'" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="7" icon="bi-people"
                                          title="No users found"
                                          message="Try clearing the filters, or add a new user." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="mt-3">{{ $users->links() }}</div>
        @endif

        <div class="text-body-secondary small mt-2">
            Showing {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} of {{ $users->total() }}
        </div>
    </x-ui.card>
</x-app-layout>
