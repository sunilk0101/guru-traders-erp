<x-app-layout>
    <x-slot name="header">Packing — {{ $document->doc_num }}</x-slot>

    <x-ui.card :title="$document->doc_num" variant="primary">
        <x-slot name="actions">
            <a href="{{ route('export.documents.edit', $document) }}" class="btn btn-sm btn-primary">
                <i class="bi bi-pencil me-1"></i> Edit cartons
            </a>
            <a href="{{ route('export.packing.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </x-slot>

        <dl class="row mb-4">
            <dt class="col-sm-3 text-body-secondary fw-normal">Buyer</dt>
            <dd class="col-sm-9">{{ $document->buyer?->company_name }} ({{ $document->buyer?->display_code }})</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Total cartons</dt>
            <dd class="col-sm-9">{{ $document->cartons->count() ?: ($document->total_cartons ?: 0) }} {{ $document->package_kind ?: 'CARTONS' }}</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Net / Gross</dt>
            <dd class="col-sm-9">
                {{ $document->net_weight ? number_format((float) $document->net_weight, 3).' kg' : '—' }}
                /
                {{ $document->gross_weight ? number_format((float) $document->gross_weight, 3).' kg' : '—' }}
            </dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Marks &amp; Nos.</dt>
            <dd class="col-sm-9">{{ $document->marks_and_numbers ?: '—' }}</dd>
        </dl>

        {{-- Henna: supplier packing list form → upload → auto cartons --}}
        @can('export-document.edit')
            <div class="border rounded p-3 mb-4 bg-body-tertiary">
                <h6 class="fw-semibold mb-1">Import supplier packing list</h6>
                <p class="small text-body-secondary mb-3 mb-md-2">
                    Send the Excel or PDF form to outside packers. Upload the filled file here with the supplier selected —
                    cartons are created automatically so export docs need no double entry.
                </p>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="{{ route('export.packing.template-excel-document', $document) }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-file-earmark-excel me-1"></i> Excel for this shipment
                    </a>
                    <a href="{{ route('export.packing.template-pdf-document', $document) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-file-earmark-pdf me-1"></i> PDF form for this shipment
                    </a>
                    <a href="{{ route('export.packing.template-excel') }}" class="btn btn-sm btn-link">Blank Excel</a>
                    <a href="{{ route('export.packing.template-pdf') }}" class="btn btn-sm btn-link">Blank PDF</a>
                </div>

                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Supplier (who packed)</label>
                        <select id="pl-supplier" class="form-select form-select-sm">
                            <option value="">— Select —</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->label ?? ($supplier->company_name.' ('.$supplier->display_code.')') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small mb-1">Filled Excel / PDF / image</label>
                        <input type="file" id="pl-file" class="form-control form-control-sm"
                               accept=".xlsx,.xls,.csv,application/pdf,image/*">
                    </div>
                    <div class="col-md-3">
                        <button type="button" id="pl-preview-btn" class="btn btn-sm btn-primary w-100">
                            <i class="bi bi-eye me-1"></i> Preview import
                        </button>
                    </div>
                </div>

                @unless($ocrConfigured)
                    <div class="form-text text-warning mt-2">
                        PDF/image OCR needs <code>GEMINI_API_KEY</code>. Excel upload works without it.
                    </div>
                @endunless

                <div id="pl-preview" class="d-none mt-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div class="small">
                            <span id="pl-meta" class="text-body-secondary"></span>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <select id="pl-mode" class="form-select form-select-sm" style="width:auto">
                                <option value="replace">Replace existing cartons</option>
                                <option value="append">Append to existing cartons</option>
                            </select>
                            <form method="POST" action="{{ route('export.packing.import-apply', $document) }}" id="pl-apply-form" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="supplier_id" id="pl-apply-supplier">
                                <input type="hidden" name="mode" id="pl-apply-mode" value="replace">
                                <input type="hidden" name="cartons_json" id="pl-apply-cartons">
                                <input type="hidden" name="reference_no" id="pl-apply-ref">
                                <input type="hidden" name="remarks" id="pl-apply-remarks">
                                <input type="file" name="file" id="pl-apply-file" class="d-none">
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="bi bi-check2 me-1"></i> Create packing list
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="table-responsive" style="max-height:280px;overflow:auto">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Carton</th>
                                    <th>Net</th>
                                    <th>Gross</th>
                                    <th>Dimensions</th>
                                    <th>Description</th>
                                    <th>Unit</th>
                                    <th class="text-end">Qty</th>
                                </tr>
                            </thead>
                            <tbody id="pl-preview-body"></tbody>
                        </table>
                    </div>
                </div>
                <div id="pl-error" class="alert alert-danger py-2 small mt-2 d-none"></div>
            </div>
        @endcan

        <h6 class="text-body-secondary small text-uppercase mb-2">Generate Packing Lists</h6>
        <div class="row g-3 mb-4">
            @forelse($packingRows as $i => $entry)
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold small mb-1">
                            {{ chr(65 + $i) }}. {{ $entry->variantLabel() ?? 'Standard Format' }}
                        </div>
                        <span class="badge text-bg-{{ $entry->statusColor() }}">{{ $entry->statusLabel() }}</span>
                        @can('export-document.generate')
                            <a href="{{ route('export.documents.packing-list', [$document, $entry->variant_code]) }}"
                               class="btn btn-sm btn-outline-primary w-100 mt-2" target="_blank">
                                <i class="bi bi-file-earmark-pdf me-1"></i> Generate
                            </a>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="col-12 text-body-secondary small">No packing-list checklist rows on this shipment yet.</div>
            @endforelse
        </div>

        <h6 class="text-body-secondary small text-uppercase mb-2">Cartons</h6>
        @if($document->cartons->isNotEmpty())
            @foreach($document->cartons as $carton)
                <div class="border rounded p-3 mb-2">
                    <div class="d-flex justify-content-between">
                        <strong>Carton {{ $carton->carton_no ?: $loop->iteration }}</strong>
                        <span class="small text-body-secondary">
                            Net {{ $carton->net_weight ? number_format((float) $carton->net_weight, 3) : '—' }} kg
                            &middot; Gross {{ $carton->gross_weight ? number_format((float) $carton->gross_weight, 3) : '—' }} kg
                            @if($carton->dimensions) &middot; {{ $carton->dimensions }} @endif
                        </span>
                    </div>
                    @if($carton->lines->isNotEmpty())
                        <table class="table table-sm mb-0 mt-2">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-end">Qty</th>
                                    <th>Unit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($carton->lines as $line)
                                    <tr>
                                        <td>{{ $line->description ?: '—' }}</td>
                                        <td class="text-end">{{ $line->qty }}</td>
                                        <td>{{ $line->unit ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="small text-body-secondary mt-2">No lines recorded in this carton yet.</div>
                    @endif
                </div>
            @endforeach
        @else
            <p class="text-body-secondary small mb-0">
                No cartons yet. Import a supplier packing list above, or
                <a href="{{ route('export.documents.edit', $document) }}">edit cartons manually</a>.
            </p>
        @endif
    </x-ui.card>

    @can('export-document.edit')
        <script>
            (function () {
                const previewBtn = document.getElementById('pl-preview-btn');
                if (! previewBtn) return;

                const fileInput = document.getElementById('pl-file');
                const supplierSelect = document.getElementById('pl-supplier');
                const previewBox = document.getElementById('pl-preview');
                const previewBody = document.getElementById('pl-preview-body');
                const metaEl = document.getElementById('pl-meta');
                const errorEl = document.getElementById('pl-error');
                const modeSelect = document.getElementById('pl-mode');
                const applySupplier = document.getElementById('pl-apply-supplier');
                const applyMode = document.getElementById('pl-apply-mode');
                const applyCartons = document.getElementById('pl-apply-cartons');
                const applyRef = document.getElementById('pl-apply-ref');
                const applyRemarks = document.getElementById('pl-apply-remarks');
                const applyForm = document.getElementById('pl-apply-form');
                const applyFile = document.getElementById('pl-apply-file');
                let lastMeta = {};

                previewBtn.addEventListener('click', async function () {
                    errorEl.classList.add('d-none');
                    previewBox.classList.add('d-none');

                    if (! fileInput.files.length) {
                        errorEl.textContent = 'Choose an Excel or PDF/image file first.';
                        errorEl.classList.remove('d-none');
                        return;
                    }

                    const fd = new FormData();
                    fd.append('file', fileInput.files[0]);
                    if (supplierSelect.value) fd.append('supplier_id', supplierSelect.value);
                    fd.append('_token', '{{ csrf_token() }}');

                    previewBtn.disabled = true;
                    previewBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Reading…';

                    try {
                        const res = await fetch(@json(route('export.packing.import-preview', $document)), {
                            method: 'POST',
                            body: fd,
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        const data = await res.json();
                        if (! res.ok) throw new Error(data.message || 'Import failed');

                        previewBody.innerHTML = '';
                        (data.cartons || []).forEach(function (c) {
                            (c.lines || []).forEach(function (line, idx) {
                                const tr = document.createElement('tr');
                                tr.innerHTML =
                                    '<td>' + (idx === 0 ? (c.carton_no || '') : '') + '</td>' +
                                    '<td>' + (idx === 0 && c.net_weight != null ? c.net_weight : '') + '</td>' +
                                    '<td>' + (idx === 0 && c.gross_weight != null ? c.gross_weight : '') + '</td>' +
                                    '<td>' + (idx === 0 ? (c.dimensions || '') : '') + '</td>' +
                                    '<td>' + (line.description || '') + '</td>' +
                                    '<td>' + (line.unit || '') + '</td>' +
                                    '<td class="text-end">' + (line.qty ?? '') + '</td>';
                                previewBody.appendChild(tr);
                            });
                        });

                        metaEl.textContent = (data.carton_count || 0) + ' carton(s)'
                            + (data.supplier_name ? ' · ' + data.supplier_name : '')
                            + (data.reference_no ? ' · Ref ' + data.reference_no : '');

                        lastMeta = data;
                        applySupplier.value = supplierSelect.value || '';
                        applyCartons.value = JSON.stringify(data.cartons || []);
                        applyMode.value = modeSelect.value;
                        applyRef.value = data.reference_no || '';
                        applyRemarks.value = data.remarks || '';
                        previewBox.classList.remove('d-none');
                    } catch (err) {
                        errorEl.textContent = err.message || 'Import failed';
                        errorEl.classList.remove('d-none');
                    } finally {
                        previewBtn.disabled = false;
                        previewBtn.innerHTML = '<i class="bi bi-eye me-1"></i> Preview import';
                    }
                });

                modeSelect?.addEventListener('change', function () {
                    applyMode.value = modeSelect.value;
                });

                applyForm?.addEventListener('submit', function () {
                    applyMode.value = modeSelect.value;
                    applySupplier.value = supplierSelect.value || '';
                    // Re-attach the same chosen file for archive on the checklist.
                    if (fileInput.files.length && window.DataTransfer) {
                        const dt = new DataTransfer();
                        dt.items.add(fileInput.files[0]);
                        applyFile.files = dt.files;
                    }
                });
            })();
        </script>
    @endcan
</x-app-layout>
