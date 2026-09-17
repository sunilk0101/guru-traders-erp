<x-app-layout>
    <x-slot name="header">Inquiries</x-slot>

    <x-ui.card title="Inquiries" variant="primary">
        <x-slot name="actions">
            @can('inquiry.view')
                <a href="{{ route('sales.inquiries.follow-ups') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-calendar2-check me-1"></i> Follow-ups
                </a>
            @endcan
            @can('inquiry.create')
                <a href="{{ route('sales.inquiries.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> New Inquiry
                </a>
            @endcan
        </x-slot>

        <div class="row g-2 mb-3">
            @foreach([
                ['label' => 'Total',         'value' => $stats['total'],         'color' => 'primary'],
                ['label' => 'Drafts',        'value' => $stats['draft'],         'color' => 'secondary'],
                ['label' => 'Price Working', 'value' => $stats['price_working'], 'color' => 'warning'],
                ['label' => 'Quote Sent',    'value' => $stats['quote_sent'],    'color' => 'info'],
                ['label' => 'Confirmed',     'value' => $stats['confirmed'],     'color' => 'success'],
            ] as $card)
                <div class="col-6 col-md">
                    <div class="border rounded p-3 h-100">
                        <div class="text-body-secondary small text-uppercase" style="letter-spacing:.04em">{{ $card['label'] }}</div>
                        <div class="fs-3 fw-semibold text-{{ $card['color'] }}">{{ $card['value'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label small text-body-secondary mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="form-control form-control-sm" placeholder="Inquiry no., buyer ref, buyer name">
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
                <button class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('sales.inquiries.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Inquiry No.</th>
                        <th>Date</th>
                        <th>Buyer</th>
                        <th>Category</th>
                        <th>Order Format</th>
                        <th class="text-end">Amount</th>
                        <th style="width:130px">Status</th>
                        <th class="text-end" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inquiries as $inquiry)
                        <tr>
                            <td class="fw-semibold font-monospace">{{ $inquiry->inquiry_no }}</td>
                            <td>{{ $inquiry->inquiry_date?->format('d M Y') }}</td>
                            <td>{{ $inquiry->buyer?->company_name }} <span class="text-body-secondary">({{ $inquiry->buyer?->display_code }})</span></td>
                            <td>{{ $inquiry->category?->name ?? '—' }}</td>
                            <td>{{ $inquiry->format?->name ?? '—' }}</td>
                            <td class="text-end">{{ number_format($inquiry->totalAmount(), 2) }}</td>
                            <td>
                                <span class="badge text-bg-{{ $inquiry->statusColor() }}">{{ $inquiry->statusLabel() }}</span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('inquiry.view')
                                        <a href="{{ route('sales.inquiries.show', $inquiry) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('sales.inquiries.pdf', $inquiry) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Download PDF">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                        </a>
                                        <a href="{{ route('sales.inquiries.xlsx', $inquiry) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Download Excel">
                                            <i class="bi bi-file-earmark-excel"></i>
                                        </a>
                                    @endcan
                                    @can('inquiry.edit')
                                        <a href="{{ route('sales.inquiries.edit', $inquiry) }}"
                                           class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('inquiry.delete')
                                        <x-ui.delete-form
                                            :action="route('sales.inquiries.destroy', $inquiry)"
                                            :confirm="'Delete inquiry &quot;'.$inquiry->inquiry_no.'&quot;? This cannot be undone.'" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="8" icon="bi-chat-square-text"
                                          title="No inquiries yet"
                                          message="Raise the first inquiry against a buyer and an order format." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($inquiries->hasPages())
            <div class="mt-3">{{ $inquiries->links('pagination::bootstrap-5') }}</div>
        @endif

        <div class="text-body-secondary small mt-2">
            Showing {{ $inquiries->firstItem() ?? 0 }}–{{ $inquiries->lastItem() ?? 0 }} of {{ $inquiries->total() }}
        </div>
    </x-ui.card>
</x-app-layout>
