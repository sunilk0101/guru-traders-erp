<x-app-layout>
    <x-slot name="header">Order Confirmations</x-slot>

    <x-ui.card title="Order Confirmations" variant="primary">
        <x-slot name="actions">
            @can('order-confirmation.create')
                <a href="{{ route('sales.order-confirmations.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> New OC
                </a>
            @endcan
        </x-slot>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label small text-body-secondary mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="form-control form-control-sm" placeholder="Contract no., buyer">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-body-secondary mb-1">Buyer</label>
                <select name="buyer_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($buyers as $id => $label)
                        <option value="{{ $id }}" @selected((string) ($filters['buyer_id'] ?? '') === (string) $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-body-secondary mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-secondary text-nowrap d-inline-flex align-items-center flex-shrink-0"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('sales.order-confirmations.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Contract No.</th>
                        <th>Date</th>
                        <th>Buyer</th>
                        <th>Type</th>
                        <th>Source Inquiry</th>
                        <th class="text-end">Amount</th>
                        <th style="width:120px">Status</th>
                        <th class="text-end" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($confirmations as $oc)
                        <tr>
                            <td class="fw-semibold font-monospace">{{ $oc->oc_num }}</td>
                            <td>{{ $oc->oc_date?->format('d M Y') }}</td>
                            <td>{{ $oc->buyer?->company_name }} <span class="text-body-secondary">({{ $oc->buyer?->display_code }})</span></td>
                            <td>
                                <span class="badge {{ $oc->mode === 'direct' ? 'text-bg-dark' : 'text-bg-light border' }}">
                                    {{ \App\Models\OrderConfirmation::MODES[$oc->mode] ?? $oc->mode }}
                                </span>
                            </td>
                            <td>{{ $oc->source_inquiry_id ? optional($oc->sourceInquiry)->inquiry_no : '—' }}</td>
                            <td class="text-end">{{ number_format($oc->totalAmount(), 2) }}</td>
                            <td><span class="badge text-bg-{{ $oc->statusColor() }}">{{ $oc->statusLabel() }}</span></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('order-confirmation.view')
                                        <a href="{{ route('sales.order-confirmations.show', $oc) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan
                                    @can('order-confirmation.edit')
                                        <a href="{{ route('sales.order-confirmations.edit', $oc) }}"
                                           class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('order-confirmation.delete')
                                        <x-ui.delete-form
                                            :action="route('sales.order-confirmations.destroy', $oc)"
                                            :confirm='"Delete OC \"" . $oc->oc_num . "\"? This cannot be undone."' />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="8" icon="bi-check2-square"
                                          title="No Order Confirmations yet"
                                          message="Convert a confirmed inquiry, or raise a direct buyer contract." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($confirmations->hasPages())
            <div class="mt-3">{{ $confirmations->links('pagination::bootstrap-5') }}</div>
        @endif

        <div class="text-body-secondary small mt-2">
            Showing {{ $confirmations->firstItem() ?? 0 }}–{{ $confirmations->lastItem() ?? 0 }} of {{ $confirmations->total() }}
        </div>
    </x-ui.card>
</x-app-layout>
