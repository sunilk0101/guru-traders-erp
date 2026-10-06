<x-app-layout>
    <x-slot name="header">Roles</x-slot>

    <x-ui.card title="Roles" variant="primary">
        <x-slot name="actions">
            @can('role.create')
                <a href="{{ route('user-management.roles.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add Role
                </a>
            @endcan
        </x-slot>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Role</th>
                        <th>Description</th>
                        <th class="text-center" style="width:110px">Permissions</th>
                        <th class="text-center" style="width:90px">Users</th>
                        <th class="text-end sticky-actions-col" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $role)
                        @php $isSystem = $registry->isSystemRole($role->name); @endphp
                        <tr>
                            <td class="text-body-secondary">{{ $loop->iteration }}</td>
                            <td>
                                <span class="fw-semibold">{{ $role->name }}</span>
                                @if($isSystem)
                                    <i class="bi bi-shield-lock-fill text-warning ms-1"
                                       data-bs-toggle="tooltip" title="System role — cannot be renamed or deleted"></i>
                                @endif
                            </td>
                            <td class="text-body-secondary small">
                                {{ $registry->roleDescription($role->name) ?? '—' }}
                            </td>
                            <td class="text-center">
                                @if($role->name === 'Super Admin')
                                    <span class="badge text-bg-warning-subtle text-warning-emphasis border border-warning-subtle">All</span>
                                @else
                                    <span class="badge text-bg-light border">{{ $role->permissions_count }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge text-bg-light border">{{ $role->users_count }}</span>
                            </td>
                            <td class="text-end sticky-actions-col">
                                <div class="btn-group btn-group-sm">
                                    @can('role.view')
                                        <a href="{{ route('user-management.roles.show', $role) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan
                                    @can('role.edit')
                                        <a href="{{ route('user-management.roles.edit', $role) }}"
                                           class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('role.delete')
                                        <x-ui.delete-form
                                            :action="route('user-management.roles.destroy', $role)"
                                            :confirm='"Delete role \"" . $role->name . "\"?"'
                                            :disabled="$isSystem || $role->users_count > 0"
                                            :disabled-reason="$isSystem ? 'System role' : 'Role is assigned to '.$role->users_count.' user(s)'" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <div class="alert alert-light border mt-3 small mb-0">
        <i class="bi bi-info-circle me-1"></i>
        <strong>System roles</strong> come from <code>config/permissions.php</code> and cannot be renamed or
        deleted, because route middleware refers to them by name. You can still change what most of them
        can do — only Super Admin is fixed.
    </div>
</x-app-layout>
