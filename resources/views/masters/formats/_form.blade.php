@props([
    'format'  => null,
    'columns' => [],
    'units'   => [],
])

@php
    use App\Models\DocumentFormatColumn;

    // old() wins so a failed validation round-trip keeps what the user had —
    // including the order they'd dragged rows into and any custom columns
    // they'd added, not just the standard ones.
    $postedOrder = old('column_order');

    if ($postedOrder) {
        $rows = collect($postedOrder)->map(function ($key) {
            $isStandard = array_key_exists($key, DocumentFormatColumn::STANDARD);
            $posted = (array) old("columns.{$key}", []);

            return [
                'key'         => $key,
                'label'       => $posted['label'] ?? ($isStandard ? DocumentFormatColumn::STANDARD[$key]['label'] : ''),
                'enabled'     => filled($posted['enabled'] ?? null),
                'mandatory'   => filled($posted['mandatory'] ?? null),
                'is_custom'   => ! $isStandard,
                'print_only'  => $isStandard ? DocumentFormatColumn::STANDARD[$key]['print_only'] : false,
                'sub_columns' => collect(explode(',', (string) ($posted['sub_columns'] ?? '')))
                    ->map(fn ($tag) => trim($tag))->filter()->values()->all(),
            ];
        })->values();
    } else {
        $rows = collect($columns)->map(fn ($state, $key) => [
            'key'         => $key,
            'label'       => $state['label'],
            'enabled'     => (bool) $state['enabled'],
            'mandatory'   => (bool) ($state['mandatory'] ?? false),
            'is_custom'   => false,
            'print_only'  => $state['print_only'],
            'sub_columns' => $state['sub_columns'] ?? [],
        ])->values();

        if ($format) {
            $rows = $rows->concat(
                $format->columns->where('is_custom', true)->sortBy('sort_order')->map(fn ($c) => [
                    'key'         => $c->key,
                    'label'       => $c->label,
                    'enabled'     => $c->is_enabled,
                    'mandatory'   => $c->is_mandatory,
                    'is_custom'   => true,
                    'print_only'  => false,
                    'sub_columns' => $c->sub_columns ?? [],
                ])->values()
            );
        }
    }

    $unitList = old('units', $units);
@endphp

{{--
    Order Format master — "Define the column structure for this purchase order
    format".

    Section order follows the client's prototype: identity, units, columns,
    live preview, delivery/packing, then the module connections footnote.
--}}

<x-ui.form-section title="Format Identity" icon="bi-file-earmark-ruled"
                   subtitle="Linked to categories from the Category Master; used by Purchase Orders.">
    <div class="row">
        <x-ui.field name="name" label="Format Name" :value="$format?->name" required
                    col="col-12" placeholder="e.g. Format 4 — Colour × Size Grid" />
    </div>
    <div class="row">
        <x-ui.textarea name="description" label="Description / Notes" :value="$format?->description"
                       rows="2" col="col-12"
                       placeholder="Which categories use this format, any special notes…" />
    </div>
    <div class="row">
        <x-ui.select name="status" label="Status" required
                     :options="['active' => 'Active', 'inactive' => 'Inactive']"
                     :selected="$format?->status ?? 'active'"
                     :placeholder="false" />

        <div class="col-md-6 mb-3">
            <label class="form-label fw-semibold">Multiple colour rows per item</label>
            <div class="form-check form-switch mt-1">
                {{-- Hidden field first: an unchecked switch posts nothing, and
                     the request requires the key. --}}
                <input type="hidden" name="allow_multiple_colours" value="0">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="allow_multiple_colours" name="allow_multiple_colours" value="1"
                       @checked(old('allow_multiple_colours', $format?->allow_multiple_colours ?? false))>
                <label class="form-check-label" for="allow_multiple_colours" id="colour-switch-label">
                    Off — one colour per item row
                </label>
            </div>
            <div class="form-text">
                When on, each item row in Inquiry / OC / PO gets a <strong>+ Add colour</strong> button.
                All colour rows share the same Design, Product, Supplier, FOB and size breakdown.
            </div>
        </div>
    </div>
</x-ui.form-section>

<x-ui.form-section title="Units" icon="bi-rulers"
                   subtitle="Shared across PO, OC, Item Summary, Export Docs, Debit Note and Agent Commission.">
    <p class="text-body-secondary small">
        The selected unit in a PO row auto-populates the label in the Price column header
        (e.g. <span class="font-monospace">Price / PCS</span>).
    </p>

    <div class="unit-chips" id="unit-chips">
        @foreach($unitList as $unit)
            <span class="unit-chip">
                {{ $unit }}
                <input type="hidden" name="units[]" value="{{ $unit }}">
                <button type="button" class="unit-chip-remove" aria-label="Remove {{ $unit }}">&times;</button>
            </span>
        @endforeach
    </div>

    <div class="d-flex gap-2 mt-2" style="max-width:26rem">
        {{-- Not a <form> control that submits: pressing Enter here must add a
             chip, not save the format. --}}
        <input type="text" id="unit-input" class="form-control form-control-sm"
               placeholder="Add unit (e.g. DOZEN, BOX, KGS)" maxlength="20" autocomplete="off">
        <button type="button" id="unit-add" class="btn btn-sm btn-secondary text-nowrap">
            <i class="bi bi-plus-lg me-1"></i>Add Unit
        </button>
    </div>

    @error('units')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('units.*')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</x-ui.form-section>

<x-ui.form-section title="Item Table Columns" icon="bi-layout-three-columns"
                   subtitle="Untick a column to drop it, mark it mandatory, or drag rows to reorder the item table.">
    <p class="text-body-secondary small">
        Sr. No., Qty and Amount are always drawn — a row without them is not an order line.
        Give <strong>Size</strong> sub-columns (e.g. S, M, L, XL) to turn every item row's size entry into a
        fixed qty-per-size grid instead of free-form colour/size rows.
    </p>

    <div class="table-responsive">
        <table class="table table-sm align-middle grid-table mb-0" id="column-table">
            <thead>
                <tr>
                    <th style="width:28px"></th>
                    <th style="width:80px">Include</th>
                    <th style="width:110px">
                        <div class="d-flex align-items-center gap-1">
                            <input class="form-check-input m-0" type="checkbox" id="mandatory-select-all"
                                   title="Check all mandatory">
                            <label class="mb-0" for="mandatory-select-all">Mandatory</label>
                        </div>
                    </th>
                    <th style="width:220px">Column</th>
                    <th style="width:260px">Sub-columns</th>
                    <th style="width:110px">Visibility</th>
                    <th style="width:40px"></th>
                </tr>
            </thead>
            <tbody id="column-rows">
                @foreach($rows as $row)
                    @include('masters.formats._column-row', ['row' => $row])
                @endforeach
            </tbody>
        </table>
    </div>

    <template id="column-row-template">
        @include('masters.formats._column-row', ['row' => [
            'key' => '__KEY__', 'label' => '', 'enabled' => true, 'mandatory' => false,
            'is_custom' => true, 'print_only' => false, 'sub_columns' => [],
        ]])
    </template>

    @error('columns')
        <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
    @enderror
    @error('column_order')
        <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
    @enderror

    <div class="mt-3">
        <button type="button" id="add-custom-column" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-plus-lg me-1"></i>Add custom column
        </button>
        <span class="text-body-secondary small ms-2">⟵ Drag the handle to reorder · standard columns can't be removed, only hidden</span>
    </div>
</x-ui.form-section>

<x-ui.form-section title="Live Table Preview" icon="bi-table"
                   subtitle="The exact table structure as it appears in Inquiry → OC → PO.">
    <p class="text-body-secondary small">
        Sample data. The image column is hidden on screen and visible on print / PDF only.
        A red <span class="text-danger">*</span> marks a mandatory column.
    </p>

    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0" id="format-preview">
            <thead class="table-light"><tr></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</x-ui.form-section>

<x-ui.form-section title="Delivery & Packing Details" icon="bi-box-seam"
                   subtitle="Printed at the bottom of every PO using this format. Pre-fills when a PO is created — editable per PO.">
    <div class="row">
        <x-ui.textarea name="delivery_details" label="Delivery Details" :value="$format?->delivery_details"
                       rows="3" col="col-12"
                       placeholder="e.g. Delivery within 30 days from PO date. Goods to be dispatched to the address below." />
    </div>
    <div class="row">
        <x-ui.textarea name="packing_details" label="Packing Details" :value="$format?->packing_details"
                       rows="3" col="col-12"
                       placeholder="e.g. Each piece in individual poly bag. Assorted packing in export cartons of 12/24 pcs." />
    </div>

    <div class="mt-2">
        <label class="form-label fw-semibold">
            Reference Images
            <span class="text-body-secondary fw-normal small">— shown on print / PDF / Excel export only · defaults for Inquiry / OC / PO</span>
        </label>

        {{-- Already saved. Unticking one removes it on save; caption prints under the thumb. --}}
        @if($format && $format->images->isNotEmpty())
            <div class="d-flex flex-wrap gap-3 mb-3" id="existing-reference-images">
                @foreach($format->images as $image)
                    <div class="reference-image">
                        <img src="{{ $image->url }}" alt="{{ $image->original_name }}">
                        <span class="reference-image-keep">
                            <input type="checkbox" name="keep_images[]" value="{{ $image->id }}" checked>
                            Keep
                        </span>
                        <input type="text" name="image_captions[{{ $image->id }}]"
                               value="{{ old('image_captions.'.$image->id, $image->caption) }}"
                               class="form-control form-control-sm reference-image-caption"
                               maxlength="500" placeholder="Caption / instruction for print">
                    </div>
                @endforeach
            </div>
        @elseif($format)
            <input type="hidden" name="keep_images[]" value="">
        @endif

        <input type="file" name="images[]" id="images" multiple
               accept="image/jpeg,image/png,image/webp"
               class="form-control @error('images.*') is-invalid @enderror">
        <div id="new-reference-previews" class="d-flex flex-wrap gap-3 mt-3"></div>
        <div class="form-text">
            Packing diagrams &middot; label samples &middot; marking references &middot; carton markings.
            JPG, PNG or WebP, up to 4 MB each. Add a short caption under each thumbnail for print.
        </div>
        @error('images.*')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @error('image_captions.*')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @error('new_image_captions.*')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</x-ui.form-section>

<x-ui.form-section title="Module Connections" icon="bi-diagram-3"
                   subtitle="This format feeds into the following modules.">
    <div class="d-flex flex-wrap gap-2">
        @foreach(['Inquiries', 'Order Confirmations', 'Purchase Orders', 'Export Documents', 'Debit Notes'] as $module)
            <span class="badge text-bg-light border fw-normal">{{ $module }}</span>
        @endforeach
    </div>
    <div class="form-text mt-2">
        The unit list defined here is shared across all modules above.
        Category linking is managed from the
        @can('category.view')
            <a href="{{ route('masters.categories.index') }}">Category Master</a>.
        @else
            Category Master.
        @endcan
    </div>
</x-ui.form-section>

<div class="form-actions">
    <button type="submit" class="btn btn-primary px-4">
        <i class="bi bi-check-lg me-1"></i>{{ $format ? 'Update' : 'Save' }} Format
    </button>
    <a href="{{ route('masters.formats.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[action*="formats"]') || document.querySelector('form');

    /* ------------------------------ Units ------------------------------ */

    const chips = document.getElementById('unit-chips');
    const unitInput = document.getElementById('unit-input');
    const unitAdd = document.getElementById('unit-add');

    function currentUnits() {
        return Array.from(chips.querySelectorAll('input[name="units[]"]')).map((i) => i.value);
    }

    function addUnit() {
        // Upper-cased here as well as in the request, so the duplicate check
        // below compares what will actually be stored.
        const value = unitInput.value.trim().toUpperCase();

        if (! value || currentUnits().includes(value)) {
            unitInput.value = '';
            return;
        }

        const chip = document.createElement('span');
        chip.className = 'unit-chip';
        chip.textContent = value;

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'units[]';
        hidden.value = value;

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'unit-chip-remove';
        remove.setAttribute('aria-label', 'Remove ' + value);
        remove.innerHTML = '&times;';

        chip.append(hidden, remove);
        chips.append(chip);

        unitInput.value = '';
        renderPreview();
    }

    unitAdd.addEventListener('click', addUnit);
    unitInput.addEventListener('keydown', function (e) {
        // Enter in a bare text input submits the form otherwise.
        if (e.key === 'Enter') { e.preventDefault(); addUnit(); }
    });

    chips.addEventListener('click', function (e) {
        const button = e.target.closest('.unit-chip-remove');
        if (! button) return;
        button.closest('.unit-chip').remove();
        renderPreview();
    });

    /* --------------------------- Colour switch --------------------------- */

    const colourSwitch = document.getElementById('allow_multiple_colours');
    const colourLabel = document.getElementById('colour-switch-label');

    function describeColour() {
        colourLabel.textContent = colourSwitch.checked
            ? 'On — several colour rows per item'
            : 'Off — one colour per item row';
    }

    colourSwitch.addEventListener('change', describeColour);
    describeColour();

    /* --------------------------- Column rows --------------------------- */

    const columnRows = document.getElementById('column-rows');
    const columnTemplate = document.getElementById('column-row-template');
    let customColumnSeq = 0;

    document.getElementById('add-custom-column').addEventListener('click', function () {
        const key = 'new_' + (++customColumnSeq) + '_' + Date.now();
        const html = columnTemplate.innerHTML.replace(/__KEY__/g, key);
        columnRows.insertAdjacentHTML('beforeend', html);
        renderPreview();
    });

    columnRows.addEventListener('click', function (e) {
        if (e.target.closest('.js-remove-column')) {
            e.target.closest('.column-row').remove();
            renderPreview();
            return;
        }

        const preset = e.target.closest('.js-subcol-preset');
        if (preset) {
            addSubcolTags(e.target.closest('.column-row'), preset.dataset.preset.split(','));
            return;
        }

        if (e.target.closest('.js-subcol-remove')) {
            const row = e.target.closest('.column-row');
            e.target.closest('.subcol-chip').remove();
            syncSubcolValue(row);
            renderPreview();
            return;
        }

        if (e.target.closest('.js-subcol-add')) {
            const row = e.target.closest('.column-row');
            const input = row.querySelector('.js-subcol-input');
            addSubcolTags(row, input.value.split(/[,-]/));
            input.value = '';
            return;
        }
    });

    columnRows.addEventListener('keydown', function (e) {
        if (e.target.classList.contains('js-subcol-input') && e.key === 'Enter') {
            e.preventDefault();
            const row = e.target.closest('.column-row');
            addSubcolTags(row, e.target.value.split(/[,-]/));
            e.target.value = '';
        }
    });

    columnRows.addEventListener('input', function (e) {
        if (e.target.classList.contains('js-column-label') || e.target.classList.contains('js-column-mandatory')) {
            renderPreview();
        }
    });

    columnRows.addEventListener('change', function (e) {
        if (e.target.classList.contains('js-column-toggle') || e.target.classList.contains('js-column-mandatory')) {
            renderPreview();
        }
    });

    function addSubcolTags(row, rawTags) {
        const chipsWrap = row.querySelector('.subcol-chips');
        if (! chipsWrap) return;

        const existing = Array.from(chipsWrap.querySelectorAll('.subcol-chip')).map(function (c) {
            return c.dataset.tag;
        });

        rawTags.map((t) => t.trim().toUpperCase()).filter(Boolean).forEach(function (tag) {
            if (existing.includes(tag)) return;
            existing.push(tag);

            const chip = document.createElement('span');
            chip.className = 'unit-chip subcol-chip';
            chip.dataset.tag = tag;
            chip.textContent = tag;

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'unit-chip-remove js-subcol-remove';
            remove.setAttribute('aria-label', 'Remove ' + tag);
            remove.innerHTML = '&times;';

            chip.append(remove);
            chipsWrap.append(chip);
        });

        syncSubcolValue(row);
        renderPreview();
    }

    function syncSubcolValue(row) {
        const chipsWrap = row.querySelector('.subcol-chips');
        const hidden = row.querySelector('.js-subcol-value');
        if (! chipsWrap || ! hidden) return;

        hidden.value = Array.from(chipsWrap.querySelectorAll('.subcol-chip'))
            .map((c) => c.dataset.tag).join(',');
    }

    /* Drag to reorder — native HTML5 drag/drop on the row's handle cell. */
    let dragged = null;

    columnRows.addEventListener('dragstart', function (e) {
        const row = e.target.closest('.column-row');
        if (! row || ! e.target.closest('.column-drag-handle')) { e.preventDefault(); return; }
        dragged = row;
        row.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    columnRows.addEventListener('dragend', function () {
        if (dragged) dragged.classList.remove('is-dragging');
        columnRows.querySelectorAll('.drag-over-top, .drag-over-bottom').forEach(function (el) {
            el.classList.remove('drag-over-top', 'drag-over-bottom');
        });
        dragged = null;
    });

    columnRows.addEventListener('dragover', function (e) {
        if (! dragged) return;
        e.preventDefault();

        const row = e.target.closest('.column-row');
        if (! row || row === dragged) return;

        columnRows.querySelectorAll('.drag-over-top, .drag-over-bottom').forEach(function (el) {
            el.classList.remove('drag-over-top', 'drag-over-bottom');
        });

        const before = e.clientY < row.getBoundingClientRect().top + row.offsetHeight / 2;
        row.classList.add(before ? 'drag-over-top' : 'drag-over-bottom');
    });

    columnRows.addEventListener('drop', function (e) {
        if (! dragged) return;
        e.preventDefault();

        const row = e.target.closest('.column-row');
        if (! row || row === dragged) return;

        const before = e.clientY < row.getBoundingClientRect().top + row.offsetHeight / 2;
        row.insertAdjacentElement(before ? 'beforebegin' : 'afterend', dragged);

        columnRows.querySelectorAll('.drag-over-top, .drag-over-bottom').forEach(function (el) {
            el.classList.remove('drag-over-top', 'drag-over-bottom');
        });
        renderPreview();
    });

    /* Build column_order[] fresh from DOM order right before submit — the
       simplest way to keep the posted order in sync with drag/add/remove
       without maintaining a parallel list through every one of those events. */
    form.addEventListener('submit', function () {
        form.querySelectorAll('input[data-generated-order]').forEach(function (el) { el.remove(); });

        columnRows.querySelectorAll(':scope > .column-row').forEach(function (row) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'column_order[]';
            input.value = row.dataset.key;
            input.dataset.generatedOrder = '1';
            form.appendChild(input);
        });
    });

    /* ---------------------------- Live preview ----------------------------

       Rebuilt from the form's own state rather than from a saved format, so
       the preview answers "what will this look like" while it is being
       decided, not after. Mirrors DocumentFormat::priceLabel() for the Price
       header, and the Size sub-column grid every item row will show once
       tags are added to the Size row here. */

    const preview = document.getElementById('format-preview');
    const headRow = preview.querySelector('thead tr');
    const body = preview.querySelector('tbody');

    const SAMPLE = [
        { design_no: 'NS-101', product: 'LADIES EMBROIDERED KURTI', qty: 120, price: 450 },
        { design_no: 'NS-102', product: 'GENTS COTTON SHIRT',       qty: 80,  price: 350 },
    ];

    function money(n) {
        return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderPreview() {
        const unit = currentUnits()[0] || null;
        const cols = [{ key: 'sr_no', label: 'Sr. No.', printOnly: false }];
        let sizeTags = [];

        columnRows.querySelectorAll(':scope > .column-row').forEach(function (row) {
            const toggle = row.querySelector('.js-column-toggle');
            if (! toggle || ! toggle.checked) return;

            const key = row.dataset.key;
            const labelInput = row.querySelector('.js-column-label');
            const mandatory = row.querySelector('.js-column-mandatory');
            let label = (labelInput ? labelInput.value : '').trim() || key;

            if (key === 'price' && unit) label += ' / ' + unit;
            if (mandatory && mandatory.checked) label += ' *';

            if (key === 'size') {
                sizeTags = Array.from(row.querySelectorAll('.subcol-chip')).map((c) => c.dataset.tag);
                if (sizeTags.length) {
                    sizeTags.forEach((tag) => cols.push({ key: 'size_tag', tag: tag, label: tag, printOnly: false }));
                    cols.push({ key: 'size_total', label: (mandatory && mandatory.checked) ? 'Total *' : 'Total', printOnly: false });
                    return;
                }
            }

            cols.push({ key: key, label: label, printOnly: key === 'image' });

            // Qty always sits immediately before Price, as the prototype draws
            // it — it is not switchable, so it has no toggle of its own. When
            // Size has sub-columns, the per-tag qty cells above already cover
            // this, so the plain Qty column is skipped.
            if (key === 'unit' && ! sizeTags.length) cols.push({ key: 'qty', label: 'Qty', printOnly: false });
        });

        cols.push({ key: 'amount', label: 'Amount', printOnly: false });

        headRow.replaceChildren(...cols.map(function (col) {
            const th = document.createElement('th');
            th.textContent = col.label;
            if (col.printOnly) {
                th.className = 'text-body-secondary fst-italic';
                th.title = 'Hidden on screen — printed on PDF / Excel only';
            }
            return th;
        }));

        body.replaceChildren(...SAMPLE.map(function (row, i) {
            const tr = document.createElement('tr');
            let qtyLeft = row.qty;

            cols.forEach(function (col, ci) {
                const td = document.createElement('td');

                switch (col.key) {
                    case 'sr_no':  td.textContent = String(i + 1); break;
                    case 'design_no': td.textContent = row.design_no; break;
                    case 'product':   td.textContent = row.product; break;
                    case 'qty':       td.textContent = String(row.qty); break;
                    case 'unit':      td.textContent = unit || '—'; break;
                    case 'price':     td.textContent = money(row.price); break;
                    case 'amount':    td.textContent = money(row.qty * row.price); break;
                    case 'image':     td.innerHTML = '<i class="bi bi-image text-body-secondary"></i>'; break;
                    case 'size_tag': {
                        // Sample split evenly across tags so the preview shows
                        // plausible numbers, same idea as the real grid summing
                        // per-tag inputs into a total.
                        const share = Math.round(row.qty / (sizeTags.length || 1));
                        const value = ci === cols.length - 2 ? qtyLeft : share;
                        qtyLeft -= value;
                        td.textContent = String(value);
                        td.className = 'text-center';
                        break;
                    }
                    case 'size_total': td.textContent = String(row.qty); td.className = 'text-center fw-semibold'; break;
                    default:          td.textContent = '—';
                }

                if (col.printOnly) td.className = 'text-center';
                tr.append(td);
            });

            return tr;
        }));
    }

    renderPreview();

    /* --------------------- Mandatory select-all --------------------- */
    const mandatoryAll = document.getElementById('mandatory-select-all');
    mandatoryAll?.addEventListener('change', function () {
        document.querySelectorAll('.js-column-mandatory').forEach(function (box) {
            box.checked = mandatoryAll.checked;
        });
    });

    /* -------------- Reference image new-file thumbnails -------------- */
    const imagesInput = document.getElementById('images');
    const newPreviews = document.getElementById('new-reference-previews');

    imagesInput?.addEventListener('change', function () {
        if (! newPreviews) return;
        newPreviews.innerHTML = '';
        Array.from(imagesInput.files || []).forEach(function (file, index) {
            const wrap = document.createElement('div');
            wrap.className = 'reference-image';
            const img = document.createElement('img');
            img.alt = file.name;
            img.src = URL.createObjectURL(file);
            const caption = document.createElement('input');
            caption.type = 'text';
            caption.name = 'new_image_captions[' + index + ']';
            caption.className = 'form-control form-control-sm reference-image-caption';
            caption.maxLength = 500;
            caption.placeholder = 'Caption / instruction for print';
            wrap.append(img, caption);
            newPreviews.append(wrap);
        });
    });
});
</script>
@endpush
