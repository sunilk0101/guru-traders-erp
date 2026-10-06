@props(['purchaseOrder' => null])

@php
    $isEdit = (bool) $purchaseOrder;
    $val = fn (string $field, $default = null) => old($field, $isEdit ? $purchaseOrder->{$field} : $default);

    // See sales/inquiries/_form.blade.php for why this is built as a plain
    // variable rather than inline inside @json().
    $ocJs = $orderConfirmations->mapWithKeys(function ($oc) {
        return [$oc->id => [
            'buyer'            => $oc->buyer?->company_name,
            'category_id'      => $oc->category_id,
            'delivery_details' => $oc->delivery_details,
            'packing_details'  => $oc->packing_details,
            'units'            => $oc->format?->units->pluck('name') ?? [],
            'allow_multiple_colours' => (bool) ($oc->format?->allow_multiple_colours ?? false),
            // Same Size sub-column tags the Inquiry/OC forms read off their
            // Order Format — a PO raised from this contract shows the same
            // fixed qty-per-size grid rather than free-form size entry.
            'sizeSubColumns'   => $oc->format?->columns->firstWhere('key', 'size')?->sub_columns ?? [],
        ]];
    });
@endphp

<x-ui.form-section title="PO Identity" icon="bi-cart-check" subtitle="→ Supplier Master · Contract / OC module">
    <div class="row">
        <div class="col-md-3 mb-3">
            <label class="form-label fw-semibold">PO No.</label>
            <input type="text" class="form-control bg-body-tertiary" readonly
                   value="{{ $isEdit ? $purchaseOrder->po_num : 'GT/PO/[seq]/[FY]' }}">
        </div>

        <x-ui.field name="po_date" label="PO Date" type="date" required col="col-md-3"
                    :value="$val('po_date') instanceof \Carbon\CarbonInterface ? $val('po_date')->format('Y-m-d') : $val('po_date', now()->format('Y-m-d'))" />

        <x-ui.select name="order_confirmation_id" label="Contract No." required col="col-md-3"
                     :options="$orderConfirmations->pluck('oc_num', 'id')" :selected="$val('order_confirmation_id')" />

        <div class="col-md-3 mb-3">
            <label class="form-label fw-semibold">Buyer</label>
            <input type="text" class="form-control bg-body-tertiary" id="buyer-display" readonly placeholder="— From Contract —">
        </div>
    </div>

    <div class="row">
        <x-ui.select name="supplier_id" label="Supplier" required col="col-md-4"
                     :options="$suppliers" :selected="$val('supplier_id')" />

        <x-ui.field name="dispatch_date" label="Dispatch Date" type="date" col="col-md-4"
                    :value="$val('dispatch_date') instanceof \Carbon\CarbonInterface ? $val('dispatch_date')->format('Y-m-d') : $val('dispatch_date')" />

        <x-ui.field name="remarks" label="Remarks" col="col-md-4"
                    :value="$val('remarks')" placeholder="Additional notes…" />
    </div>
</x-ui.form-section>

<x-ui.form-section title="PO Items" icon="bi-table"
                   subtitle="₹ price ← Inquiry costing · OC FOB → Export Invoice · HSN ← Product Master">
    <div id="items-wrap">
        @php $existingItems = old('items', $isEdit ? $purchaseOrder->items : []); @endphp

        @foreach($existingItems as $item)
            @php
                $isArr = is_array($item);
                $iOcItemId = $isArr ? ($item['order_confirmation_item_id'] ?? '') : $item->order_confirmation_item_id;
                $iDesign = $isArr ? ($item['design_no'] ?? '') : $item->design_no;
                $iDesc = $isArr ? ($item['description'] ?? '') : $item->description;
                $iProduct = $isArr ? ($item['product_id'] ?? '') : $item->product_id;
                $iUnit = $isArr ? ($item['unit'] ?? '') : $item->unit;
                $iCostPrice = $isArr ? ($item['cost_price'] ?? '') : $item->cost_price;
                $iRemarks = $isArr ? ($item['remarks'] ?? '') : $item->remarks;
                $iImagePath = $isArr ? ($item['existing_image_path'] ?? ($item['image_path'] ?? '')) : $item->image_path;
                $iImageUrl = $isArr ? ($item['image_url'] ?? '') : ($item->image_url ?? '');
                $iColours = $isArr ? ($item['colours'] ?? []) : $item->colours;
                $iProductLabel = $isArr ? null : $item->product?->name;
            @endphp
            <div class="inquiry-item border rounded p-3 mb-3" data-item
                 data-product-id="{{ $iProduct }}" data-product-label="{{ $iProductLabel }}" data-unit="{{ $iUnit }}">
                <input type="hidden" class="js-field" data-field="order_confirmation_item_id" value="{{ $iOcItemId }}">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold item-index-label">Item</span>
                    <button type="button" class="btn btn-sm btn-outline-danger js-remove-item"><i class="bi bi-trash"></i></button>
                </div>
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label small">Design No. / Name</label>
                        <input type="text" class="form-control form-control-sm js-field" data-field="design_no" maxlength="150" value="{{ $iDesign }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Product</label>
                        <select class="form-select form-select-sm js-field js-product-select" data-field="product_id"><option value="">— Select —</option></select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Unit</label>
                        <select class="form-select form-select-sm js-field js-unit-select" data-field="unit"><option value="">—</option></select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">₹ / Unit</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm js-field js-price" data-field="cost_price" value="{{ $iCostPrice }}">
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-6">
                        <label class="form-label small">Description</label>
                        <input type="text" class="form-control form-control-sm js-field" data-field="description" maxlength="500" value="{{ $iDesc }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Remarks</label>
                        <input type="text" class="form-control form-control-sm js-field" data-field="remarks" maxlength="500" value="{{ $iRemarks }}">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small">Item photo</label>
                        <div class="d-flex align-items-center gap-2">
                            <img class="js-item-image-preview rounded border bg-body-tertiary {{ $iImageUrl ? '' : 'd-none' }}"
                                 src="{{ $iImageUrl }}" alt=""
                                 style="width:48px;height:48px;object-fit:cover">
                            <input type="file" class="form-control form-control-sm js-item-image" accept="image/png,image/jpeg,image/webp">
                            <input type="hidden" class="js-field" data-field="existing_image_path" value="{{ $iImagePath }}">
                        </div>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-3">
                        <label class="form-label small">Qty</label>
                        <input type="text" class="form-control form-control-sm js-qty-display" readonly value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Amount</label>
                        <input type="text" class="form-control form-control-sm js-amount-display" readonly value="{{ $isArr ? '0.00' : number_format((float) $item->amount, 2) }}">
                    </div>
                </div>
                <div class="colours-wrap mt-2">
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
        @endforeach
    </div>

    @error('items')
        <div class="invalid-feedback d-block mb-2">{{ $message }}</div>
    @enderror

    <button type="button" id="add-item" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Add Item
    </button>
</x-ui.form-section>

<x-ui.form-section title="Delivery Timeline" icon="bi-truck"
                   subtitle="From PO sent → partial deliveries → full receipt. Goods Inward will auto-populate this once that module exists.">
    <div id="timeline-wrap">
        @php $existingTimeline = old('timeline', $isEdit ? $purchaseOrder->timelineEntries : []); @endphp

        @foreach($existingTimeline as $entry)
            @php
                $tIsArr = is_array($entry);
                $tDate = $tIsArr ? ($entry['date'] ?? '') : $entry->entry_date?->format('Y-m-d');
                $tNote = $tIsArr ? ($entry['note'] ?? '') : $entry->note;
                $tQty = $tIsArr ? ($entry['qty'] ?? '') : $entry->qty;
            @endphp
            <div class="d-flex gap-2 mb-2 timeline-row" data-timeline>
                <input type="date" class="form-control form-control-sm js-timeline-date" style="max-width:11rem" value="{{ $tDate }}">
                <input type="text" class="form-control form-control-sm js-timeline-note" placeholder="Note…" value="{{ $tNote }}">
                <input type="number" min="0" class="form-control form-control-sm js-timeline-qty" placeholder="Qty" style="max-width:8rem" value="{{ $tQty }}">
                <button type="button" class="btn btn-sm btn-outline-danger js-remove-timeline"><i class="bi bi-x"></i></button>
            </div>
        @endforeach
    </div>
    <button type="button" id="add-timeline" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-plus-lg me-1"></i>Add timeline entry
    </button>
</x-ui.form-section>

<x-ui.form-section title="Delivery & Packing Details" icon="bi-box-seam"
                   subtitle="Pre-fills from Order Format · editable per PO.">
    <div class="row">
        <x-ui.textarea name="delivery_details" label="Delivery Details" required col="col-12"
                       rows="3" :value="$val('delivery_details')" />
    </div>
    <div class="row">
        <x-ui.textarea name="packing_details" label="Packing Details" required col="col-12"
                       rows="3" :value="$val('packing_details')" />
    </div>
</x-ui.form-section>

<div class="form-actions d-flex flex-wrap gap-2 align-items-center">
    <div class="me-auto small text-body-secondary">
        Status: <span class="fw-semibold text-body">{{ $val('status', 'draft') === 'raised' ? 'Raised' : 'Draft' }}</span>
    </div>
    <input type="hidden" name="status" id="status-input" value="{{ $val('status', 'draft') }}">

    <a href="{{ $isEdit ? route('procurement.purchase-orders.show', $purchaseOrder) : route('procurement.purchase-orders.index') }}"
       class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" id="btn-save-draft" class="btn btn-outline-secondary">
        <i class="bi bi-save me-1"></i>Save Draft
    </button>
    <button type="submit" id="btn-raise" class="btn btn-primary px-4">
        <i class="bi bi-check-lg me-1"></i>Raise PO
    </button>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('po-form');
    const ocData = @json($ocJs);
    const productsUrl = "{{ route('sales.inquiries.products') }}";

    const contractSelect = document.getElementById('order_confirmation_id');
    const buyerDisplay    = document.getElementById('buyer-display');
    const deliveryEl      = document.getElementById('delivery_details');
    const packingEl       = document.getElementById('packing_details');
    const itemsWrap       = document.getElementById('items-wrap');

    function currentOc() {
        return ocData[contractSelect.value] || null;
    }

    function populateUnitSelects() {
        const oc = currentOc();
        const units = oc ? oc.units : [];
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

    function applyColourButtons() {
        const oc = currentOc();
        itemsWrap.querySelectorAll('.js-add-colour').forEach(function (btn) {
            btn.classList.toggle('d-none', ! (oc && oc.allow_multiple_colours));
        });
    }

    /**
     * Same fixed size-tag grid as the Inquiry/OC forms — a PO raised from a
     * contract whose Order Format gives Size fixed sub-columns shows the
     * identical qty-per-tag grid, not free-form size entry. Reuses the exact
     * .inquiry-size / .js-size-label / .js-size-qty shape, so the submit
     * handler's colour/size compile loop needs no changes.
     */
    function sizeTagsFor(oc) {
        return (oc && oc.sizeSubColumns) || [];
    }

    function applySizeGrid(colourEl, oc) {
        const tags = sizeTagsFor(oc);
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

    function applySizeGridToAllItems() {
        const oc = currentOc();
        itemsWrap.querySelectorAll(':scope > .inquiry-item').forEach(function (itemEl) {
            itemEl.querySelectorAll(':scope .colours-wrap > .inquiry-colour').forEach(function (colourEl) {
                applySizeGrid(colourEl, oc);
            });
        });
    }

    contractSelect.addEventListener('change', function () {
        const oc = currentOc();
        buyerDisplay.value = oc ? oc.buyer : '';

        if (oc) {
            if (! deliveryEl.value.trim()) deliveryEl.value = oc.delivery_details || '';
            if (! packingEl.value.trim()) packingEl.value = oc.packing_details || '';
        }

        populateUnitSelects();
        applyColourButtons();
        applySizeGridToAllItems();
        refreshItemProducts();
    });

    function refreshItemProducts() {
        const oc = currentOc();
        const categoryId = oc ? oc.category_id : '';
        itemsWrap.querySelectorAll('.inquiry-item').forEach(function (itemEl) {
            loadSelectOptions(itemEl.querySelector('.js-product-select'), categoryId);
        });
    }

    function loadSelectOptions(selectEl, categoryId, presetValue, presetLabel) {
        const selected = presetValue !== undefined ? presetValue : selectEl.dataset.selected;

        fetch(productsUrl + '?category_id=' + encodeURIComponent(categoryId || ''))
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
        applySizeGrid(coloursWrap.lastElementChild, currentOc());
        recalcItem(itemEl);
    }

    function addSize(colourEl) {
        const node = sizeTemplate.content.cloneNode(true);
        colourEl.querySelector('.sizes-wrap').appendChild(node);
    }

    function initItem(itemEl) {
        const oc = currentOc();
        loadSelectOptions(
            itemEl.querySelector('.js-product-select'), oc ? oc.category_id : '',
            itemEl.dataset.productId || '', itemEl.dataset.productLabel || ''
        );

        const unitSelect = itemEl.querySelector('.js-unit-select');
        unitSelect.dataset.selected = itemEl.dataset.unit || '';
        populateUnitSelects();

        itemEl.querySelectorAll(':scope .colours-wrap > .inquiry-colour').forEach(function (colourEl) {
            applySizeGrid(colourEl, oc);
        });

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

    /* ------------------------------ Timeline rows ------------------------------ */

    const timelineWrap = document.getElementById('timeline-wrap');
    const timelineTemplate = document.getElementById('tpl-timeline');

    document.getElementById('add-timeline').addEventListener('click', function () {
        const node = timelineTemplate.content.cloneNode(true);
        timelineWrap.appendChild(node);
    });

    timelineWrap.addEventListener('click', function (e) {
        if (e.target.closest('.js-remove-timeline')) {
            e.target.closest('.timeline-row').remove();
        }
    });

    /* --------------------------- Status buttons --------------------------- */

    document.getElementById('btn-save-draft').addEventListener('click', function () {
        document.getElementById('status-input').value = 'draft';
    });
    document.getElementById('btn-raise').addEventListener('click', function () {
        document.getElementById('status-input').value = 'raised';
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

        itemsWrap.querySelectorAll(':scope > .inquiry-item').forEach(function (itemEl, i) {
            const field = (name) => itemEl.querySelector('[data-field="' + name + '"]').value;

            appendHidden('items[' + i + '][order_confirmation_item_id]', field('order_confirmation_item_id'));
            appendHidden('items[' + i + '][design_no]', field('design_no'));
            appendHidden('items[' + i + '][description]', field('description'));
            appendHidden('items[' + i + '][product_id]', field('product_id'));
            appendHidden('items[' + i + '][unit]', field('unit'));
            appendHidden('items[' + i + '][cost_price]', field('cost_price'));
            appendHidden('items[' + i + '][remarks]', field('remarks'));
            appendHidden('items[' + i + '][existing_image_path]', field('existing_image_path'));
            const fileInput = itemEl.querySelector('.js-item-image');
            if (fileInput) {
                fileInput.name = 'items[' + i + '][image]';
            }

            itemEl.querySelectorAll(':scope .colours-wrap > .inquiry-colour').forEach(function (colourEl, j) {
                appendHidden('items[' + i + '][colours][' + j + '][colour]', colourEl.querySelector('.js-colour-name').value);

                colourEl.querySelectorAll(':scope .sizes-wrap > .inquiry-size').forEach(function (sizeEl, k) {
                    appendHidden('items[' + i + '][colours][' + j + '][sizes][' + k + '][size]', sizeEl.querySelector('.js-size-label').value);
                    appendHidden('items[' + i + '][colours][' + j + '][sizes][' + k + '][qty]', sizeEl.querySelector('.js-size-qty').value || 0);
                });
            });
        });

        timelineWrap.querySelectorAll(':scope > .timeline-row').forEach(function (row, i) {
            appendHidden('timeline[' + i + '][date]', row.querySelector('.js-timeline-date').value);
            appendHidden('timeline[' + i + '][note]', row.querySelector('.js-timeline-note').value);
            appendHidden('timeline[' + i + '][qty]', row.querySelector('.js-timeline-qty').value);
        });
    });

    itemsWrap.addEventListener('change', function (e) {
        if (! e.target.classList.contains('js-item-image')) return;
        const preview = e.target.closest('.inquiry-item')?.querySelector('.js-item-image-preview');
        const file = e.target.files && e.target.files[0];
        if (! preview || ! file) return;
        preview.src = URL.createObjectURL(file);
        preview.classList.remove('d-none');
    });

    /* --------------------------------- Init --------------------------------- */

    if (contractSelect.value) {
        const oc = currentOc();
        if (oc) buyerDisplay.value = oc.buyer;
    }
    populateUnitSelects();
    applyColourButtons();
    itemsWrap.querySelectorAll(':scope > .inquiry-item').forEach(initItem);
});
</script>
@endpush

<template id="tpl-item">
    <div class="inquiry-item border rounded p-3 mb-3" data-item>
        <input type="hidden" class="js-field" data-field="order_confirmation_item_id" value="">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold item-index-label">Item</span>
            <button type="button" class="btn btn-sm btn-outline-danger js-remove-item"><i class="bi bi-trash"></i></button>
        </div>
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label small">Design No. / Name</label>
                <input type="text" class="form-control form-control-sm js-field" data-field="design_no" maxlength="150">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Product</label>
                <select class="form-select form-select-sm js-field js-product-select" data-field="product_id"><option value="">— Select —</option></select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Unit</label>
                <select class="form-select form-select-sm js-field js-unit-select" data-field="unit"><option value="">—</option></select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">₹ / Unit</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm js-field js-price" data-field="cost_price">
            </div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-md-6">
                <label class="form-label small">Description</label>
                <input type="text" class="form-control form-control-sm js-field" data-field="description" maxlength="500">
            </div>
            <div class="col-md-6">
                <label class="form-label small">Remarks</label>
                <input type="text" class="form-control form-control-sm js-field" data-field="remarks" maxlength="500">
            </div>
            <div class="col-md-12">
                <label class="form-label small">Item photo</label>
                <div class="d-flex align-items-center gap-2">
                    <img class="js-item-image-preview rounded border bg-body-tertiary d-none" alt=""
                         style="width:48px;height:48px;object-fit:cover">
                    <input type="file" class="form-control form-control-sm js-item-image" accept="image/png,image/jpeg,image/webp">
                    <input type="hidden" class="js-field" data-field="existing_image_path" value="">
                </div>
            </div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-md-3">
                <label class="form-label small">Qty</label>
                <input type="text" class="form-control form-control-sm js-qty-display" readonly value="0">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Amount</label>
                <input type="text" class="form-control form-control-sm js-amount-display" readonly value="0.00">
            </div>
        </div>
        <div class="colours-wrap mt-2"></div>
        <button type="button" class="btn btn-sm btn-outline-secondary js-add-colour mt-1">
            <i class="bi bi-plus-lg me-1"></i>Add colour
        </button>
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

<template id="tpl-timeline">
    <div class="d-flex gap-2 mb-2 timeline-row" data-timeline>
        <input type="date" class="form-control form-control-sm js-timeline-date" style="max-width:11rem">
        <input type="text" class="form-control form-control-sm js-timeline-note" placeholder="Note…">
        <input type="number" min="0" class="form-control form-control-sm js-timeline-qty" placeholder="Qty" style="max-width:8rem">
        <button type="button" class="btn btn-sm btn-outline-danger js-remove-timeline"><i class="bi bi-x"></i></button>
    </div>
</template>
