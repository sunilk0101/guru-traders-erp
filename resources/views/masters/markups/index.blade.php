<x-app-layout>
    <x-slot name="header">Markup</x-slot>

    <x-ui.card title="Markup Master" variant="primary">
        <x-slot name="actions">
            @can('markup.create')
                <a href="{{ route('masters.markups.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add Markup Rule
                </a>
            @endcan
        </x-slot>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-5">
                <label class="form-label small text-body-secondary mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="form-control form-control-sm" placeholder="Supplier or buyer name / code">
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
                <a href="{{ route('masters.markups.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Supplier</th>
                        <th>Buyer</th>
                        <th style="width:100px" class="text-end">Markup %</th>
                        <th style="width:110px" class="text-end">Discount %</th>
                        <th style="width:120px">Record Date</th>
                        <th style="width:110px">Status</th>
                        <th class="text-end" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($markups as $markup)
                        <tr>
                            <td class="text-body-secondary">{{ $loop->iteration + ($markups->currentPage() - 1) * $markups->perPage() }}</td>
                            <td>
                                <div class="fw-semibold">{{ $markup->supplier?->company_name ?: '—' }}</div>
                                <div class="small text-body-secondary font-monospace">{{ $markup->supplier?->display_code }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $markup->buyer?->company_name ?: '—' }}</div>
                                <div class="small text-body-secondary font-monospace">{{ $markup->buyer?->display_code }}</div>
                            </td>
                            <td class="text-end fw-semibold">{{ rtrim(rtrim(number_format((float) $markup->markup_percent, 2), '0'), '.') }}%</td>
                            {{-- Read from the supplier, not stored here — see the
                                 Markup model. Shown because the profit line makes
                                 no sense without it. --}}
                            <td class="text-end text-body-secondary">{{ rtrim(rtrim(number_format($markup->discountPercent(), 2), '0'), '.') }}%</td>
                            <td class="text-body-secondary small">{{ $markup->record_date?->format('d M Y') }}</td>
                            <td>
                                @can('markup.edit')
                                    <form action="{{ route('masters.markups.toggle-status', $markup) }}" method="POST" class="js-confirm"
                                        data-confirm="{{ $markup->status === 'active' ? 'Deactivate this markup rule?' : 'Activate this markup rule?' }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"
                                                data-bs-toggle="tooltip" title="Click to toggle">
                                            <x-ui.status-badge :status="$markup->status === 'active'" />
                                        </button>
                                    </form>
                                @else
                                    <x-ui.status-badge :status="$markup->status === 'active'" />
                                @endcan
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('markup.view')
                                        <a href="{{ route('masters.markups.show', $markup) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan
                                    @can('markup.edit')
                                        <a href="{{ route('masters.markups.edit', $markup) }}"
                                           class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('markup.delete')
                                        <x-ui.delete-form
                                            :action="route('masters.markups.destroy', $markup)"
                                            confirm="Delete this markup rule?" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="8" icon="bi-percent"
                                          title="No markup rules yet"
                                          message="Add the first rule — an order confirmation prices its lines from one." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($markups->hasPages())
            <div class="mt-3">{{ $markups->links() }}</div>
        @endif

        <div class="text-body-secondary small mt-2">
            Showing {{ $markups->firstItem() ?? 0 }}–{{ $markups->lastItem() ?? 0 }} of {{ $markups->total() }}
        </div>
    </x-ui.card>
</x-app-layout>
