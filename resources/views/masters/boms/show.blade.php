<x-app-layout>
    <x-slot name="header">BOM Details</x-slot>

    <x-ui.card title="BOM: {{ $bom->name }}" variant="primary">
        <x-slot name="actions">
            @can('bom.edit')
                <a href="{{ route('masters.boms.edit', $bom) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan
            <a href="{{ route('masters.boms.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        </x-slot>

        <div class="table-responsive mb-4">
            <table class="table table-bordered mb-0">
                <tbody>
                    <tr>
                        <th style="width:200px" class="bg-light">BOM Name</th>
                        <td>{{ $bom->name }}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Status</th>
                        <td><x-ui.status-badge :status="$bom->status === 'active'" /></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Pack total</th>
                        <td>₹{{ number_format($bom->lineTotal(), 2) }}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Remarks</th>
                        <td>{{ $bom->remarks ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Created By</th>
                        <td>{{ $bom->creator?->name ?? '—' }} ({{ $bom->created_at?->format('d M Y H:i') ?? '—' }})</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Updated By</th>
                        <td>{{ $bom->updater?->name ?? '—' }} ({{ $bom->updated_at?->format('d M Y H:i') ?? '—' }})</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h6 class="text-uppercase text-body-secondary fw-bold small border-bottom pb-2 mb-3">Lines</h6>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:60px">Photo</th>
                        <th>Trim / Accessory</th>
                        <th>Size</th>
                        <th>Description</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bom->lines as $line)
                        <tr>
                            <td>
                                @if($line->image_url)
                                    <img src="{{ $line->image_url }}" alt="{{ $line->component_name }}"
                                         class="rounded border" style="width:40px;height:40px;object-fit:cover">
                                @else
                                    <span class="text-body-secondary">—</span>
                                @endif
                            </td>
                            <td>{{ $line->component_name }}</td>
                            <td>{{ $line->size ?: '—' }}</td>
                            <td>{{ $line->remarks ?: '—' }}</td>
                            <td class="text-end font-monospace">{{ $line->qty }}</td>
                            <td class="text-end font-monospace">{{ $line->rate !== null ? $line->rate : '—' }}</td>
                            <td class="text-end font-monospace">{{ number_format($line->lineTotal(), 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-body-secondary">No lines.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
</x-app-layout>
