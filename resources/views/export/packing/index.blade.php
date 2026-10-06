<x-app-layout>
    <x-slot name="header">Packing</x-slot>

    <x-ui.card title="Packing Desk" variant="primary">
        <p class="text-body-secondary small mb-3">
            Carton-wise packing for each shipment. Record cartons on the Export Document edit screen,
            then generate Packing List formats from here.
        </p>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-6">
                <label class="form-label small text-body-secondary mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       class="form-control form-control-sm" placeholder="Doc no., buyer">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-secondary text-nowrap d-inline-flex align-items-center flex-shrink-0"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('export.packing.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Export Doc</th>
                        <th>Buyer</th>
                        <th>Cartons</th>
                        <th>Net / Gross (kg)</th>
                        <th>Packing Lists</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $document)
                        @php
                            $cartonCount = $document->cartons->count();
                            $packingRows = $document->checklist->filter(fn ($row) => $row->type?->code === 'packing_list');
                            $generated = $packingRows->where('status', 'generated')->count();
                        @endphp
                        <tr>
                            <td class="fw-semibold font-monospace">{{ $document->doc_num }}</td>
                            <td>{{ $document->buyer?->company_name ?? '—' }}</td>
                            <td>{{ $cartonCount ?: ($document->total_cartons ?: 0) }}</td>
                            <td>
                                {{ $document->net_weight ? number_format((float) $document->net_weight, 3) : '—' }}
                                /
                                {{ $document->gross_weight ? number_format((float) $document->gross_weight, 3) : '—' }}
                            </td>
                            <td>
                                <span class="badge text-bg-light text-body-secondary">{{ $generated }} / {{ $packingRows->count() }} generated</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('export.packing.show', $document) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-boxes me-1"></i> Pack
                                </a>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="6" icon="bi-boxes"
                                          title="No shipments to pack yet"
                                          message="Raise an Export Document from a confirmed Order Confirmation first." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($documents->hasPages())
            <div class="mt-3">{{ $documents->links('pagination::bootstrap-5') }}</div>
        @endif
    </x-ui.card>
</x-app-layout>
