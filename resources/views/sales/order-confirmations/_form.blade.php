@props(['orderConfirmation' => null])

@php
    $isEdit = (bool) $orderConfirmation;
    $val = fn (string $field, $default = null) => old($field, $isEdit ? $orderConfirmation->{$field} : $default);

    // See sales/inquiries/_form.blade.php for why these are built as plain
    // variables rather than inline inside @json() — the directive's argument
    // parser truncates silently on a long multi-line nested array literal.
    $buyersJs = $buyers->mapWithKeys(function ($b) {
        return [$b->id => [
            'agent_id'               => $b->agent_id,
            'agent_commission_type'  => $b->agent_commission_type,
            'agent_commission_value' => $b->agent_commission_value,
            'currency_id'            => $b->currency_id,
            'categories'             => $b->categories->pluck('id'),
        ]];
    });

    $formatsJs = $formats->mapWithKeys(function ($f) {
        $standardColumns = [];
        foreach (\App\Models\DocumentFormatColumn::STANDARD as $key => $meta) {
            if ($meta['print_only']) {
                continue;
            }
            $column = $f->columns->firstWhere('key', $key);
            $standardColumns[$key] = [
                'enabled'     => $column ? (bool) $column->is_enabled : true,
                'label'       => $column->label ?? $meta['label'],
                'mandatory'   => $column ? (bool) $column->is_mandatory : false,
                'sub_columns' => $column?->sub_columns ?? [],
            ];
        }

        $customColumns = $f->columns->where('is_custom', true)->where('is_enabled', true)
            ->map(fn ($c) => ['key' => $c->key, 'label' => $c->label])
            ->values();

        return [$f->id => [
            'module'                 => $f->module,
            'allow_multiple_colours' => (bool) $f->allow_multiple_colours,
            'delivery_details'       => $f->delivery_details,
            'packing_details'        => $f->packing_details,
            'reference_images'       => $f->images->map(fn ($img) => [
                'url'     => $img->url,
                'caption' => $img->caption,
                'name'    => $img->original_name,
            ])->values(),
            'units'                  => $f->units->pluck('name'),
            'categories'             => $f->categories->pluck('id'),
            'columns'                => $standardColumns,
            'customColumns'          => $customColumns,
        ]];
    });
@endphp

<x-ui.form-section title="Contract Identity" icon="bi-check2-square"
                   subtitle="→ Buyer Master · Agent Master">
    <div class="row">
        <div class="col-md-3 mb-3">
            <label class="form-label fw-semibold">Contract No.</label>
            <input type="text" class="form-control bg-body-tertiary" readonly
                   value="{{ $isEdit ? $orderConfirmation->oc_num : 'GT/[buyer code]/[seq]/[FY]' }}">
            <div class="form-text">Global running sequence</div>
        </div>

        <x-ui.select name="mode" label="Type" required col="col-md-3"
                     :options="$modes" :selected="$val('mode', 'oc')" />

        <x-ui.field name="oc_date" label="OC Date" type="date" required col="col-md-3"
                    :value="$val('oc_date') instanceof \Carbon\CarbonInterface ? $val('oc_date')->format('Y-m-d') : $val('oc_date', now()->format('Y-m-d'))" />

        <x-ui.field name="buyer_ref" label="Buyer's Ref" col="col-md-3"
                    :value="$val('buyer_ref')" placeholder="Buyer's own PO/ref no." />
    </div>

    <div class="row">
        <x-ui.select name="buyer_id" label="Buyer" required col="col-md-6"
                     :options="$buyers->pluck('label', 'id')" :selected="$val('buyer_id')" />

        <x-ui.select name="category_id" label="Category" required col="col-md-6"
                     :options="$categories" :selected="$val('category_id')" />
    </div>

    <div class="row">
        <x-ui.select name="document_format_id" label="Order Format" required col="col-md-6"
                     :options="$formats->pluck('name', 'id')" :selected="$val('document_format_id')" />

        <div class="col-md-6 mb-3">
            <label class="form-label fw-semibold">Format Type</label>
            <input type="text" class="form-control bg-body-tertiary" id="format_type" readonly placeholder="— From Format —">
        </div>
    </div>

    <div class="row">
        <x-ui.select name="agent_id" label="Agent" col="col-md-3"
                     :options="$agents" :selected="$val('agent_id')" hint="Buyer Master" />

        <x-ui.select name="agent_commission_type" label="Commission Type" col="col-md-2"
                     :options="['percent' => 'Percent', 'flat' => 'Flat']" :selected="$val('agent_commission_type')" />

        <x-ui.field name="agent_commission_value" label="Commission" type="number" col="col-md-2"
                    :value="$val('agent_commission_value')" placeholder="0.00" />

        <x-ui.select name="currency_id" label="Currency" required col="col-md-2"
                     :options="$currencies" :selected="$val('currency_id')" hint="Buyer Master" />

        <x-ui.select name="incoterm" label="Incoterm" col="col-md-3"
                     :options="['FOB' => 'FOB', 'CIF' => 'CIF', 'CFR' => 'CFR', 'EXW' => 'EXW', 'DDP' => 'DDP']"
                     :selected="$val('incoterm', 'FOB')" />
    </div>
</x-ui.form-section>

<x-ui.form-section title="Shipment Details" icon="bi-truck" subtitle="→ Export Docs module">
    <div class="row">
        <x-ui.select name="ship_method" label="Ship Method" col="col-md-3"
                     :options="['Sea' => 'Sea', 'Air' => 'Air', 'Land' => 'Land', 'Courier' => 'Courier']"
                     :selected="$val('ship_method', 'Sea')" />

        <x-ui.field name="shipment_date" label="Shipment Month/Date" col="col-md-3"
                    :value="$val('shipment_date')" placeholder="e.g. March 2026" />

        <x-ui.field name="pol" label="POL" col="col-md-3"
                    :value="$val('pol')" placeholder="e.g. Nhava Sheva, Mumbai" />

        <x-ui.field name="pod" label="POD" col="col-md-3"
                    :value="$val('pod')" placeholder="e.g. Jebel Ali, Dubai" />
    </div>

    <div class="row">
        <x-ui.field name="payment_terms" label="Payment Terms" col="col-md-6"
                    :value="$val('payment_terms')" placeholder="e.g. 30 days AOV" />

        <x-ui.field name="remarks" label="Remarks" col="col-md-6"
                    :value="$val('remarks')" placeholder="Special instructions…" />
    </div>
</x-ui.form-section>

<x-ui.form-section title="Items" icon="bi-table" id="items-section"
                   subtitle="Select a format above to define the table structure. Expand Costing per item for FOB, colour and size breakdown.">
    <div id="oc-direct-note" class="text-body-secondary small mb-3 d-none">
        <i class="bi bi-info-circle me-1"></i>
        Direct buyer contract — items are entered at PO stage. POs are raised against this contract number.
    </div>

    <div id="items-wrap">
        @php $existingItems = old('items', $isEdit ? $orderConfirmation->items : []); @endphp

        @foreach($existingItems as $item)
            @php
                $isArr = is_array($item);
                $iDesign = $isArr ? ($item['design_no'] ?? '') : $item->design_no;
                $iDesc = $isArr ? ($item['description'] ?? '') : $item->description;
                $iProduct = $isArr ? ($item['product_id'] ?? '') : $item->product_id;
                $iSupplier = $isArr ? ($item['supplier_id'] ?? '') : $item->supplier_id;
                $iUnit = $isArr ? ($item['unit'] ?? '') : $item->unit;
                $iFob = $isArr ? ($item['fob_value_id'] ?? '') : $item->fob_value_id;
                $iPrice = $isArr ? ($item['price'] ?? '') : $item->price;
                $iCostPrice = $isArr ? ($item['cost_price'] ?? '') : $item->cost_price;
                $iRemarks = $isArr ? ($item['remarks'] ?? '') : $item->remarks;
                $iColours = $isArr ? ($item['colours'] ?? []) : $item->colours;
                $iProductLabel = $isArr ? null : $item->product?->name;
                $iSupplierLabel = $isArr ? null : $item->supplier?->label;
                $iCustom = $isArr ? ($item['custom'] ?? []) : ($item->custom_values ?? []);
            @endphp
            <div class="inquiry-item border rounded p-3 mb-3" data-item
                 data-product-id="{{ $iProduct }}" data-product-label="{{ $iProductLabel }}"
                 data-supplier-id="{{ $iSupplier }}" data-supplier-label="{{ $iSupplierLabel }}"
                 data-unit="{{ $iUnit }}" data-custom-values="{{ json_encode($iCustom) }}">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold item-index-label">Item</span>
                    <button type="button" class="btn btn-sm btn-outline-danger js-remove-item"><i class="bi bi-trash"></i></button>
                </div>
                <div class="row g-2">
                    <div class="col-md-3" data-column="design_no">
                        <label class="form-label small js-column-label">Design No. / Name</label>
                        <input type="text" class="form-control form-control-sm js-field" data-field="design_no" maxlength="150" value="{{ $iDesign }}">
                    </div>
                    <div class="col-md-3" data-column="product">
                        <label class="form-label small js-column-label">Product</label>
                        <select class="form-select form-select-sm js-field js-product-select" data-field="product_id"><option value="">— Select —</option></select>
                    </div>
                    <div class="col-md-3" data-column="supplier">
                        <label class="form-label small js-column-label">Supplier</label>
                        <select class="form-select form-select-sm js-field js-supplier-select" data-field="supplier_id"><option value="">— Select —</option></select>
                    </div>
                    <div class="col-md-3" data-column="unit">
                        <label class="form-label small js-column-label">Unit</label>
                        <select class="form-select form-select-sm js-field js-unit-select" data-field="unit"><option value="">—</option></select>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-6">
                        <label class="form-label small">Description</label>
                        <input type="text" class="form-control form-control-sm js-field" data-field="description" maxlength="500" value="{{ $iDesc }}">
                    </div>
                    <div class="col-md-2" data-column="price">
                        <label class="form-label small js-column-label">Price</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm js-field js-price" data-field="price" value="{{ $iPrice }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Qty</label>
                        <input type="text" class="form-control form-control-sm js-qty-display" readonly value="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Amount</label>
                        <input type="text" class="form-control form-control-sm js-amount-display" readonly value="{{ $isArr ? '0.00' : number_format((float) $item->amount, 2) }}">
                    </div>
                </div>
                <div class="row g-2 mt-1 custom-fields-wrap"></div>
                <button type="button" class="btn btn-sm btn-link px-0 mt-2 js-toggle-costing">
                    <i class="bi bi-caret-down-fill me-1"></i>Costing
                </button>
                <div class="costing-panel d-none mt-2 border-top pt-2">
                    <div class="row g-2 mb-2">
                        <div class="col-md-3">
                            <label class="form-label small">FOB Value</label>
                            <select class="form-select form-select-sm js-field" data-field="fob_value_id">
                                <option value="">— Select —</option>
                                @foreach($fobValues as $id => $name)
                                    <option value="{{ $id }}" @selected((string) $iFob === (string) $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Cost Price / Unit (₹)</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm js-field" data-field="cost_price" value="{{ $iCostPrice }}">
                            <div class="form-text">Internal — feeds Purchase Order</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Item Remarks</label>
                            <input type="text" class="form-control form-control-sm js-field" data-field="remarks" maxlength="500" value="{{ $iRemarks }}">
                        </div>
                    </div>
                    <div class="colours-wrap">
                        @foreach($iColours as $colour)
                            @php
                                $cIsArr = is_array($colour);
                                $cName = $cIsArr ? ($colour['colour'] ?? '') : $colour->colour;
                                $cSizes = $cIsArr ? ($colour['sizes'] ?? []) : $colour->sizes;
                            @endphp
                            <div class="inquiry-colour border rounded p-2 mb-2 bg-body-tertiary" data-colour>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <input type="text" class="form-control form-control-sm js-colour-name" placeholder="Colour" maxlength="60" style="max-width:12rem" value="{{ $cName }}">
                                    <span class="text-body-secondary small js-colour-subtotal ms-auto">Qty: 0</span>
                                    <button type="button" class="btn btn-sm btn-outline-danger js-remove-colour"><i class="bi bi-trash"></i></button>
                                </div>
                                <div class="sizes-wrap d-flex flex-wrap gap-2 mb-2">
                                    @foreach($cSizes as $size)
                                        @php
                                            $sIsArr = is_array($size);
                                            $sLabel = $sIsArr ? ($size['size'] ?? '') : $size->size;
                                            $sQty = $sIsArr ? ($size['qty'] ?? 0) : $size->qty;
                                        @endphp
                                        <div class="inquiry-size d-flex align-items-center gap-1" data-size style="max-width:11rem">
                                            <input type="text" class="form-control form-control-sm js-size-label" placeholder="Size" maxlength="20" style="width:5rem" value="{{ $sLabel }}">
                                            <input type="number" min="0" class="form-control form-control-sm js-size-qty" placeholder="Qty" style="width:5rem" value="{{ $sQty }}">
                                            <button type="button" class="btn btn-sm btn-outline-danger js-remove-size"><i class="bi bi-x"></i></button>
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary js-add-size">
                                    <i class="bi bi-plus-lg me-1"></i>Add size
                                </button>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary js-add-colour mt-1">
                        <i class="bi bi-plus-lg me-1"></i>Add colour
                    </button>
                </div>
            </div>
        @endforeach
    </div>

    @error('items')
        <div class="invalid-feedback d-block mb-2">{{ $message }}</div>
    @enderror

    <button type="button" id="add-item" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Add Item
    </button>
</x-ui.form-section>

<x-ui.form-section title="Delivery & Packing Details" icon="bi-box-seam"
                   subtitle="Pre-fills from Order Format · editable per OC · carries to PO. Reference images come from the format (print defaults).">
    <div class="row">
        <x-ui.textarea name="delivery_details" label="Delivery Details" required col="col-12"
                       rows="3" :value="$val('delivery_details')" />
    </div>
    <div class="row">
        <x-ui.textarea name="packing_details" label="Packing Details" required col="col-12"
                       rows="3" :value="$val('packing_details')" />
    </div>
    <div class="mt-2">
        <label class="form-label fw-semibold">Format reference images</label>
        <div id="format-reference-images" class="d-flex flex-wrap gap-3 text-body-secondary small">
            Pick an Order Format to load its packing / marking reference images.
        </div>
    </div>
</x-ui.form-section>

<x-ui.form-section title="Module Connections" icon="bi-diagram-3">
    <div class="d-flex flex-wrap gap-2">
        @foreach(['Buyer Master', 'Agent Master', 'Order Format', 'Purchase Orders (on Raise PO)'] as $module)
            <span class="badge text-bg-light border fw-normal">{{ $module }}</span>
        @endforeach
    </div>
</x-ui.form-section>

<div class="form-actions d-flex flex-wrap gap-2 align-items-center">
    <div class="me-auto small text-body-secondary">
        Status: <span class="fw-semibold text-body">{{ \App\Models\OrderConfirmation::STATUSES[$val('status', 'draft')] ?? 'Draft' }}</span>
    </div>
    <input type="hidden" name="status" id="status-input" value="{{ $val('status', 'draft') }}">

    <a href="{{ $isEdit ? route('sales.order-confirmations.show', $orderConfirmation) : route('sales.order-confirmations.index') }}"
       class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" id="btn-save-draft" class="btn btn-outline-secondary">
        <i class="bi bi-save me-1"></i>Save Draft
    </button>
    <button type="submit" id="btn-mark-sent" class="btn btn-outline-primary">
        <i class="bi bi-send me-1"></i>Mark OC Sent
    </button>
    <button type="submit" id="btn-confirm" class="btn btn-success px-4">
        <i class="bi bi-check-lg me-1"></i>Buyer Confirmed
    </button>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('oc-form');

    const buyers = @json($buyersJs);
    const formats = @json($formatsJs);

    const productsUrl  = "{{ route('sales.inquiries.products') }}";
    const suppliersUrl = "{{ route('sales.inquiries.suppliers') }}";

    const buyerSelect    = document.getElementById('buyer_id');
    const categorySelect = document.getElementById('category_id');
    const formatSelect   = document.getElementById('document_format_id');
    const formatTypeEl   = document.getElementById('format_type');
    const deliveryEl     = document.getElementById('delivery_details');
    const packingEl      = document.getElementById('packing_details');
    const itemsWrap      = document.getElementById('items-wrap');
    const modeSelect     = document.getElementById('mode');
    const itemsSection   = document.getElementById('items-section');
    const directNote     = document.getElementById('oc-direct-note');

    /* ------------------------------ Cascades ------------------------------ */

    /**
     * See sales/inquiries/_form.blade.php's copy of this function for the
     * full reasoning — clearIfInvalid must stay false on the initial page
     * load, or an edit screen silently blanks out a saved category/format
     * the moment it opens, whenever the Category Master's current links
     * don't happen to match what the record was actually saved with.
     */
    function filterOptionsByCategory(selectEl, allowedIds, clearIfInvalid) {
        Array.from(selectEl.options).forEach(function (opt) {
            if (opt.value === '') return;
            opt.hidden = allowedIds && allowedIds.length ? ! allowedIds.includes(Number(opt.value)) : false;
        });
        if (clearIfInvalid && selectEl.selectedOptions[0] && selectEl.selectedOptions[0].hidden) {
            selectEl.value = '';
        }
    }

    function applyBuyerCategoryFilter(clearIfInvalid) {
        const buyer = buyers[buyerSelect.value];
        filterOptionsByCategory(categorySelect, buyer ? buyer.categories : null, clearIfInvalid);
    }

    function applyCategoryFormatFilter(clearIfInvalid) {
        const categoryId = categorySelect.value ? Number(categorySelect.value) : null;
        Array.from(formatSelect.options).forEach(function (opt) {
            if (opt.value === '') return;
            const meta = formats[opt.value];
            opt.hidden = categoryId && meta ? ! meta.categories.includes(categoryId) : false;
        });
        if (clearIfInvalid && formatSelect.selectedOptions[0] && formatSelect.selectedOptions[0].hidden) {
            formatSelect.value = '';
        }
    }

    function applyFormatMeta() {
        const meta = formats[formatSelect.value];
        formatTypeEl.value = meta ? meta.module : '';
        populateUnitSelects(meta ? meta.units : []);
        itemsWrap.querySelectorAll('.js-add-colour').forEach(function (btn) {
            btn.classList.toggle('d-none', ! (meta && meta.allow_multiple_colours));
        });
        itemsWrap.querySelectorAll(':scope > .inquiry-item').forEach(function (itemEl) {
            applyColumnsToItem(itemEl, meta);
        });
    }

    function applyColumnsToItem(itemEl, meta) {
        const columns = (meta && meta.columns) || {};

        ['design_no', 'product', 'supplier', 'unit', 'price'].forEach(function (key) {
            const wrap = itemEl.querySelector('[data-column="' + key + '"]');
            if (! wrap) return;

            const col = columns[key];
            const enabled = col ? col.enabled : true;
            wrap.classList.toggle('d-none', ! enabled);

            const labelEl = wrap.querySelector('.js-column-label');
            if (labelEl && col) {
                labelEl.textContent = key === 'price' && columns.unit
                    ? col.label + (itemEl.querySelector('.js-unit-select').value ? ' / ' + itemEl.querySelector('.js-unit-select').value : '')
                    : col.label;
                if (col.mandatory) labelEl.textContent += ' *';
            }
        });

        const colourNamesEnabled = !! (meta && meta.allow_multiple_colours && (! columns.colour || columns.colour.enabled));
        itemEl.querySelectorAll('.js-colour-name').forEach(function (input) {
            input.classList.toggle('d-none', ! colourNamesEnabled);
        });

        itemEl.querySelectorAll(':scope .colours-wrap > .inquiry-colour').forEach(function (colourEl) {
            applySizeGrid(colourEl, meta);
        });

        applyCustomColumns(itemEl, (meta && meta.customColumns) || []);
    }

    /**
     * Same fixed size-tag grid as the Inquiry form — reuses the identical
     * .inquiry-size / .js-size-label / .js-size-qty shape so the submit
     * handler's colour/size compile loop needs no changes.
     */
    function sizeTagsFor(meta) {
        const sizeCol = meta && meta.columns && meta.columns.size;
        return (sizeCol && sizeCol.sub_columns) || [];
    }

    function applySizeGrid(colourEl, meta) {
        const tags = sizeTagsFor(meta);
        const sizesWrap = colourEl.querySelector('.sizes-wrap');
        const addSizeBtn = colourEl.querySelector('.js-add-size');

        if (! tags.length) {
            sizesWrap.querySelectorAll('.inquiry-size').forEach(function (row) {
                row.classList.remove('is-grid');
                row.querySelector('.js-size-label').readOnly = false;
                row.querySelector('.js-remove-size').classList.remove('d-none');
            });
            if (addSizeBtn) addSizeBtn.classList.remove('d-none');
            return;
        }

        const existingQty = {};
        sizesWrap.querySelectorAll('.inquiry-size').forEach(function (row) {
            const label = row.querySelector('.js-size-label').value;
            if (label) existingQty[label] = row.querySelector('.js-size-qty').value;
        });

        sizesWrap.innerHTML = '';

        tags.forEach(function (tag) {
            const node = sizeTemplate.content.cloneNode(true);

            const labelInput = node.querySelector('.js-size-label');
            labelInput.value = tag;
            labelInput.readOnly = true;

            node.querySelector('.js-size-qty').value = existingQty[tag] || '';

            const row = node.querySelector('.inquiry-size');
            row.classList.add('is-grid');
            node.querySelector('.js-remove-size').classList.add('d-none');

            sizesWrap.appendChild(node);
        });

        if (addSizeBtn) addSizeBtn.classList.add('d-none');
    }

    function applyCustomColumns(itemEl, customColumns) {
        const wrap = itemEl.querySelector('.custom-fields-wrap');
        if (! wrap) return;

        const current = {};
        wrap.querySelectorAll('[data-custom-key]').forEach(function (el) {
            current[el.dataset.customKey] = el.querySelector('input').value;
        });

        let saved = {};
        if (itemEl.dataset.customValues) {
            try { saved = JSON.parse(itemEl.dataset.customValues) || {}; } catch (e) { saved = {}; }
        }

        wrap.innerHTML = '';

        customColumns.forEach(function (col) {
            const field = document.createElement('div');
            field.className = 'col-md-3';
            field.dataset.customKey = col.key;

            const label = document.createElement('label');
            label.className = 'form-label small';
            label.textContent = col.label;

            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control form-control-sm js-custom-field';
            input.maxLength = 255;
            input.value = current[col.key] !== undefined ? current[col.key] : (saved[col.key] || '');

            field.appendChild(label);
            field.appendChild(input);
            wrap.appendChild(field);
        });
    }

    function populateUnitSelects(units) {
        itemsWrap.querySelectorAll('.js-unit-select').forEach(function (select) {
            const current = select.dataset.selected || select.value;
            select.innerHTML = '<option value="">—</option>';
            units.forEach(function (unit) {
                const opt = document.createElement('option');
                opt.value = unit;
                opt.textContent = unit;
                if (unit === current) opt.selected = true;
                select.appendChild(opt);
            });
        });
    }

    function refreshItemProductsAndSuppliers() {
        const categoryId = categorySelect.value || '';
        itemsWrap.querySelectorAll('.inquiry-item').forEach(function (itemEl) {
            loadSelectOptions(itemEl.querySelector('.js-product-select'), productsUrl, categoryId);
            loadSelectOptions(itemEl.querySelector('.js-supplier-select'), suppliersUrl, categoryId);
        });
    }

    function loadSelectOptions(selectEl, url, categoryId, presetValue, presetLabel) {
        const selected = presetValue !== undefined ? presetValue : selectEl.dataset.selected;

        fetch(url + '?category_id=' + encodeURIComponent(categoryId || ''))
            .then(function (r) { return r.json(); })
            .then(function (rows) {
                selectEl.innerHTML = '<option value="">— Select —</option>';

                let found = false;
                rows.forEach(function (row) {
                    const opt = document.createElement('option');
                    opt.value = row.id;
                    opt.textContent = row.text;
                    if (selected && String(row.id) === String(selected)) { opt.selected = true; found = true; }
                    selectEl.appendChild(opt);
                });

                if (selected && ! found) {
                    const opt = document.createElement('option');
                    opt.value = selected;
                    opt.textContent = (presetLabel !== undefined ? presetLabel : selectEl.dataset.selectedLabel) || ('#' + selected);
                    opt.selected = true;
                    selectEl.appendChild(opt);
                }
            });
    }

    buyerSelect.addEventListener('change', function () {
        const buyer = buyers[buyerSelect.value];
        applyBuyerCategoryFilter(true);

        if (buyer) {
            document.getElementById('agent_id').value = buyer.agent_id || '';
            document.getElementById('agent_commission_type').value = buyer.agent_commission_type || '';
            document.getElementById('agent_commission_value').value = buyer.agent_commission_value || '';
            document.getElementById('currency_id').value = buyer.currency_id || '';
        }
    });

    categorySelect.addEventListener('change', function () {
        applyCategoryFormatFilter(true);
        refreshItemProductsAndSuppliers();
    });

    formatSelect.addEventListener('change', function () {
        applyFormatMeta();

        const meta = formats[formatSelect.value];
        if (meta) {
            if (! deliveryEl.value.trim()) deliveryEl.value = meta.delivery_details || '';
            if (! packingEl.value.trim()) packingEl.value = meta.packing_details || '';
        }
        renderFormatReferenceImages(meta);
    });

    function renderFormatReferenceImages(meta) {
        const host = document.getElementById('format-reference-images');
        if (! host) return;
        const images = meta?.reference_images || [];
        if (! images.length) {
            host.className = 'd-flex flex-wrap gap-3 text-body-secondary small';
            host.textContent = meta
                ? 'This format has no reference images yet.'
                : 'Pick an Order Format to load its packing / marking reference images.';
            return;
        }
        host.className = 'd-flex flex-wrap gap-3';
        host.innerHTML = '';
        images.forEach(function (image) {
            const card = document.createElement('div');
            card.className = 'reference-image';
            const img = document.createElement('img');
            img.src = image.url;
            img.alt = image.name || '';
            const caption = document.createElement('div');
            caption.className = 'reference-image-caption text-body-secondary';
            caption.textContent = image.caption || image.name || '';
            card.append(img, caption);
            host.append(card);
        });
    }

    renderFormatReferenceImages(formats[formatSelect.value]);

    /* ------------------------- Direct vs OC mode ------------------------- */

    function applyMode() {
        const isDirect = modeSelect.value === 'direct';
        itemsSection.classList.toggle('d-none', isDirect);
        directNote.classList.toggle('d-none', ! isDirect);
    }

    modeSelect.addEventListener('change', applyMode);

    /* ------------------------------ Item rows ------------------------------ */

    const itemTemplate = document.getElementById('tpl-item');
    const colourTemplate = document.getElementById('tpl-colour');
    const sizeTemplate = document.getElementById('tpl-size');

    function renumberItems() {
        itemsWrap.querySelectorAll('.inquiry-item').forEach(function (el, index) {
            el.querySelector('.item-index-label').textContent = 'Item #' + (index + 1);
        });
    }

    function recalcItem(itemEl) {
        let qty = 0;
        itemEl.querySelectorAll('.inquiry-colour').forEach(function (colourEl) {
            let colourQty = 0;
            colourEl.querySelectorAll('.js-size-qty').forEach(function (input) {
                colourQty += parseInt(input.value || '0', 10) || 0;
            });
            colourEl.querySelector('.js-colour-subtotal').textContent = 'Qty: ' + colourQty;
            qty += colourQty;
        });

        const price = parseFloat(itemEl.querySelector('.js-price').value || '0') || 0;
        itemEl.querySelector('.js-qty-display').value = qty;
        itemEl.querySelector('.js-amount-display').value = (qty * price).toFixed(2);
    }

    function addColour(itemEl) {
        const node = colourTemplate.content.cloneNode(true);
        const coloursWrap = itemEl.querySelector('.colours-wrap');
        coloursWrap.appendChild(node);
        applySizeGrid(coloursWrap.lastElementChild, formats[formatSelect.value]);
        recalcItem(itemEl);
    }

    function addSize(colourEl) {
        const node = sizeTemplate.content.cloneNode(true);
        colourEl.querySelector('.sizes-wrap').appendChild(node);
    }

    function initItem(itemEl) {
        const categoryId = categorySelect.value || '';

        loadSelectOptions(
            itemEl.querySelector('.js-product-select'), productsUrl, categoryId,
            itemEl.dataset.productId || '', itemEl.dataset.productLabel || ''
        );
        loadSelectOptions(
            itemEl.querySelector('.js-supplier-select'), suppliersUrl, categoryId,
            itemEl.dataset.supplierId || '', itemEl.dataset.supplierLabel || ''
        );

        const unitSelect = itemEl.querySelector('.js-unit-select');
        unitSelect.dataset.selected = itemEl.dataset.unit || '';

        const meta = formats[formatSelect.value];
        populateUnitSelects(meta ? meta.units : []);
        applyColumnsToItem(itemEl, meta);

        if (itemEl.querySelectorAll('.inquiry-colour').length === 0) {
            addColour(itemEl);
        }

        recalcItem(itemEl);
    }

    document.getElementById('add-item').addEventListener('click', function () {
        const node = itemTemplate.content.cloneNode(true);
        itemsWrap.appendChild(node);
        const itemEl = itemsWrap.lastElementChild;
        initItem(itemEl);
        renumberItems();
    });

    itemsWrap.addEventListener('click', function (e) {
        if (e.target.closest('.js-remove-item')) {
            e.target.closest('.inquiry-item').remove();
            renumberItems();
            return;
        }
        if (e.target.closest('.js-toggle-costing')) {
            const panel = e.target.closest('.inquiry-item').querySelector('.costing-panel');
            panel.classList.toggle('d-none');
            return;
        }
        if (e.target.closest('.js-add-colour')) {
            addColour(e.target.closest('.inquiry-item'));
            return;
        }
        if (e.target.closest('.js-remove-colour')) {
            const itemEl = e.target.closest('.inquiry-item');
            e.target.closest('.inquiry-colour').remove();
            recalcItem(itemEl);
            return;
        }
        if (e.target.closest('.js-add-size')) {
            addSize(e.target.closest('.inquiry-colour'));
            return;
        }
        if (e.target.closest('.js-remove-size')) {
            const itemEl = e.target.closest('.inquiry-item');
            e.target.closest('.inquiry-size').remove();
            recalcItem(itemEl);
            return;
        }
    });

    itemsWrap.addEventListener('input', function (e) {
        if (e.target.classList.contains('js-size-qty') || e.target.classList.contains('js-price')) {
            recalcItem(e.target.closest('.inquiry-item'));
        }
    });

    itemsWrap.addEventListener('change', function (e) {
        if (e.target.classList.contains('js-unit-select')) {
            applyColumnsToItem(e.target.closest('.inquiry-item'), formats[formatSelect.value]);
        }
    });

    /* --------------------------- Status buttons --------------------------- */

    document.getElementById('btn-save-draft').addEventListener('click', function () {
        document.getElementById('status-input').value = 'draft';
    });
    document.getElementById('btn-mark-sent').addEventListener('click', function () {
        document.getElementById('status-input').value = 'sent';
    });
    document.getElementById('btn-confirm').addEventListener('click', function () {
        document.getElementById('status-input').value = 'confirmed';
    });

    /* ------------------------- Compile the grid on submit ------------------------- */

    function appendHidden(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value == null ? '' : value;
        input.dataset.generated = '1';
        form.appendChild(input);
    }

    form.addEventListener('submit', function () {
        form.querySelectorAll('input[data-generated]').forEach(function (el) { el.remove(); });

        if (modeSelect.value === 'direct') return;

        itemsWrap.querySelectorAll(':scope > .inquiry-item').forEach(function (itemEl, i) {
            const field = (name) => itemEl.querySelector('[data-field="' + name + '"]').value;

            appendHidden('items[' + i + '][design_no]', field('design_no'));
            appendHidden('items[' + i + '][description]', field('description'));
            appendHidden('items[' + i + '][product_id]', field('product_id'));
            appendHidden('items[' + i + '][supplier_id]', field('supplier_id'));
            appendHidden('items[' + i + '][unit]', field('unit'));
            appendHidden('items[' + i + '][fob_value_id]', field('fob_value_id'));
            appendHidden('items[' + i + '][price]', field('price'));
            appendHidden('items[' + i + '][cost_price]', field('cost_price'));
            appendHidden('items[' + i + '][remarks]', field('remarks'));

            itemEl.querySelectorAll(':scope .colours-wrap > .inquiry-colour').forEach(function (colourEl, j) {
                appendHidden('items[' + i + '][colours][' + j + '][colour]', colourEl.querySelector('.js-colour-name').value);

                colourEl.querySelectorAll(':scope .sizes-wrap > .inquiry-size').forEach(function (sizeEl, k) {
                    appendHidden('items[' + i + '][colours][' + j + '][sizes][' + k + '][size]', sizeEl.querySelector('.js-size-label').value);
                    appendHidden('items[' + i + '][colours][' + j + '][sizes][' + k + '][qty]', sizeEl.querySelector('.js-size-qty').value || 0);
                });
            });

            itemEl.querySelectorAll(':scope .custom-fields-wrap [data-custom-key]').forEach(function (fieldEl) {
                appendHidden('items[' + i + '][custom][' + fieldEl.dataset.customKey + ']', fieldEl.querySelector('input').value);
            });
        });
    });

    /* --------------------------------- Init --------------------------------- */

    // false: narrow the option lists to match, but never blank out a value
    // the record actually has saved just because it was opened.
    applyBuyerCategoryFilter(false);
    applyCategoryFormatFilter(false);
    applyFormatMeta();
    applyMode();
    itemsWrap.querySelectorAll(':scope > .inquiry-item').forEach(initItem);
});
</script>
@endpush

<template id="tpl-item">
    <div class="inquiry-item border rounded p-3 mb-3" data-item>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold item-index-label">Item</span>
            <button type="button" class="btn btn-sm btn-outline-danger js-remove-item"><i class="bi bi-trash"></i></button>
        </div>
        <div class="row g-2">
            <div class="col-md-3" data-column="design_no">
                <label class="form-label small js-column-label">Design No. / Name</label>
                <input type="text" class="form-control form-control-sm js-field" data-field="design_no" maxlength="150">
            </div>
            <div class="col-md-3" data-column="product">
                <label class="form-label small js-column-label">Product</label>
                <select class="form-select form-select-sm js-field js-product-select" data-field="product_id"><option value="">— Select —</option></select>
            </div>
            <div class="col-md-3" data-column="supplier">
                <label class="form-label small js-column-label">Supplier</label>
                <select class="form-select form-select-sm js-field js-supplier-select" data-field="supplier_id"><option value="">— Select —</option></select>
            </div>
            <div class="col-md-3" data-column="unit">
                <label class="form-label small js-column-label">Unit</label>
                <select class="form-select form-select-sm js-field js-unit-select" data-field="unit"><option value="">—</option></select>
            </div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-md-6">
                <label class="form-label small">Description</label>
                <input type="text" class="form-control form-control-sm js-field" data-field="description" maxlength="500">
            </div>
            <div class="col-md-2" data-column="price">
                <label class="form-label small js-column-label">Price</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm js-field js-price" data-field="price">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Qty</label>
                <input type="text" class="form-control form-control-sm js-qty-display" readonly value="0">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Amount</label>
                <input type="text" class="form-control form-control-sm js-amount-display" readonly value="0.00">
            </div>
        </div>
        <div class="row g-2 mt-1 custom-fields-wrap"></div>
        <button type="button" class="btn btn-sm btn-link px-0 mt-2 js-toggle-costing">
            <i class="bi bi-caret-down-fill me-1"></i>Costing
        </button>
        <div class="costing-panel d-none mt-2 border-top pt-2">
            <div class="row g-2 mb-2">
                <div class="col-md-3">
                    <label class="form-label small">FOB Value</label>
                    <select class="form-select form-select-sm js-field" data-field="fob_value_id">
                        <option value="">— Select —</option>
                        @foreach($fobValues as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Cost Price / Unit (₹)</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm js-field" data-field="cost_price">
                    <div class="form-text">Internal — feeds Purchase Order</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Item Remarks</label>
                    <input type="text" class="form-control form-control-sm js-field" data-field="remarks" maxlength="500">
                </div>
            </div>
            <div class="colours-wrap"></div>
            <button type="button" class="btn btn-sm btn-outline-secondary js-add-colour mt-1">
                <i class="bi bi-plus-lg me-1"></i>Add colour
            </button>
        </div>
    </div>
</template>

<template id="tpl-colour">
    <div class="inquiry-colour border rounded p-2 mb-2 bg-body-tertiary" data-colour>
        <div class="d-flex align-items-center gap-2 mb-2">
            <input type="text" class="form-control form-control-sm js-colour-name" placeholder="Colour" maxlength="60" style="max-width:12rem">
            <span class="text-body-secondary small js-colour-subtotal ms-auto">Qty: 0</span>
            <button type="button" class="btn btn-sm btn-outline-danger js-remove-colour"><i class="bi bi-trash"></i></button>
        </div>
        <div class="sizes-wrap d-flex flex-wrap gap-2 mb-2"></div>
        <button type="button" class="btn btn-sm btn-outline-secondary js-add-size">
            <i class="bi bi-plus-lg me-1"></i>Add size
        </button>
    </div>
</template>

<template id="tpl-size">
    <div class="inquiry-size d-flex align-items-center gap-1" data-size style="max-width:11rem">
        <input type="text" class="form-control form-control-sm js-size-label" placeholder="Size" maxlength="20" style="width:5rem">
        <input type="number" min="0" class="form-control form-control-sm js-size-qty" placeholder="Qty" style="width:5rem">
        <button type="button" class="btn btn-sm btn-outline-danger js-remove-size"><i class="bi bi-x"></i></button>
    </div>
</template>
