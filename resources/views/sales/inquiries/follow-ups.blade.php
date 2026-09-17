<x-app-layout>
    <x-slot name="header">Buyer Follow-ups</x-slot>

    <x-ui.card title="Buyer Follow-ups" variant="primary">
        <x-slot name="actions">
            <a href="{{ route('sales.inquiries.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Inquiries
            </a>
        </x-slot>

        <p class="text-body-secondary small mb-3">
            Every follow-up comment logged against any inquiry, newest first — grouped by date below.
            Use the filters to narrow it down to one Category or Buyer.
        </p>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label small text-body-secondary mb-1">Category</label>
                <select name="category_id" class="form-select form-select-sm" data-searchable data-placeholder="All categories">
                    <option value="">All</option>
                    @foreach($categories as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-body-secondary mb-1">Buyer</label>
                <select name="buyer_id" class="form-select form-select-sm" data-searchable data-placeholder="All buyers">
                    <option value="">All</option>
                    @foreach($buyers as $id => $label)
                        <option value="{{ $id }}" @selected((string) ($filters['buyer_id'] ?? '') === (string) $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-body-secondary mb-1">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-body-secondary mb-1">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('sales.inquiries.follow-ups') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        @forelse($grouped as $date => $entries)
            <div class="mb-4">
                <h6 class="fw-semibold border-bottom pb-2 mb-2">
                    {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y (D)') }}
                    <span class="text-body-secondary fw-normal small">({{ $entries->count() }} follow-up{{ $entries->count() === 1 ? '' : 's' }})</span>
                </h6>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:140px">Inquiry No.</th>
                                <th>Buyer</th>
                                <th style="width:160px">Category</th>
                                <th>Comment</th>
                                <th style="width:120px">Logged by</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($entries as $followUp)
                                <tr>
                                    <td class="font-monospace">
                                        <a href="{{ route('sales.inquiries.show', $followUp->inquiry) }}">
                                            {{ $followUp->inquiry?->inquiry_no }}
                                        </a>
                                    </td>
                                    <td>
                                        {{ $followUp->inquiry?->buyer?->company_name }}
                                        <span class="text-body-secondary">({{ $followUp->inquiry?->buyer?->display_code }})</span>
                                    </td>
                                    <td>{{ $followUp->inquiry?->category?->name ?? '—' }}</td>
                                    <td>{{ $followUp->comment }}</td>
                                    <td class="text-body-secondary small">{{ $followUp->creator?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="bi-calendar2-check"
                              title="No follow-ups recorded"
                              message="Follow-up entries added from an inquiry's page will show up here, grouped by date." />
        @endforelse

        @if($followUps->hasPages())
            <div class="mt-3">{{ $followUps->links('pagination::bootstrap-5') }}</div>
        @endif

        <div class="text-body-secondary small mt-2">
            Showing {{ $followUps->firstItem() ?? 0 }}–{{ $followUps->lastItem() ?? 0 }} of {{ $followUps->total() }}
        </div>
    </x-ui.card>
</x-app-layout>
