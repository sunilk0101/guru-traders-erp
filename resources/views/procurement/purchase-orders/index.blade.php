<x-app-layout>
    <x-slot name="header">Purchase Orders</x-slot>

    <x-ui.card title="Purchase Orders" variant="primary">
        <x-slot name="actions">
            @can('purchase-order.create')
                <a href="{{ route('procurement.purchase-orders.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> New PO
                </a>
            @endcan
        </x-slot>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label small text-body-secondary mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="form-control form-control-sm" placeholder="PO no., contract no., supplier">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-body-secondary mb-1">Supplier</label>
                <select name="supplier_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($suppliers as $id => $label)
                        <option value="{{ $id }}" @selected((string) ($filters['supplier_id'] ?? '') === (string) $id)>{{ $label }}</option>
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
                <a href="{{ route('procurement.purchase-orders.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>PO No.</th>
                        <th>Date</th>
                        <th>Contract No.</th>
                        <th>Buyer</th>
                        <th>Supplier</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">₹ Value</th>
                        <th style="width:120px">Status</th>
                        <th class="text-end" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchaseOrders as $po)
                        <tr>
                            <td class="fw-semibold font-monospace">{{ $po->po_num }}</td>
                            <td>{{ $po->po_date?->format('d M Y') }}</td>
                            <td>{{ $po->orderConfirmation?->oc_num ?? '—' }}</td>
                            <td>{{ $po->orderConfirmation?->buyer?->company_name ?? '—' }}</td>
                            <td>{{ $po->supplier?->company_name }} <span class="text-body-secondary">({{ $po->supplier?->display_code }})</span></td>
                            <td class="text-end">{{ $po->items->sum('qty') }}</td>
                            <td class="text-end">{{ number_format($po->totalAmount(), 2) }}</td>
                            <td><span class="badge text-bg-{{ $po->statusColor() }}">{{ $po->statusLabel() }}</span></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('purchase-order.view')
                                        <a href="{{ route('procurement.purchase-orders.show', $po) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan
                                    @can('purchase-order.edit')
                                        <a href="{{ route('procurement.purchase-orders.edit', $po) }}"
                                           class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('purchase-order.delete')
                                        <x-ui.delete-form
                                            :action="route('procurement.purchase-orders.destroy', $po)"
                                            :confirm='"Delete PO \"" . $po->po_num . "\"? This cannot be undone."' />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="9" icon="bi-cart-check"
                                          title="No Purchase Orders yet"
                                          message="Raise a PO from a confirmed Order Confirmation, or create one directly." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($purchaseOrders->hasPages())
            <div class="mt-3">{{ $purchaseOrders->links('pagination::bootstrap-5') }}</div>
        @endif

        <div class="text-body-secondary small mt-2">
            Showing {{ $purchaseOrders->firstItem() ?? 0 }}–{{ $purchaseOrders->lastItem() ?? 0 }} of {{ $purchaseOrders->total() }}
        </div>
    </x-ui.card>
</x-app-layout>
