<?php

$f = __DIR__.'/../resources/views/sales/inquiries/_form.blade.php';
$lines = file($f);
$keepUntil = null;
foreach ($lines as $i => $line) {
    if (str_contains($line, 'renderFormatReferenceImages(formats[formatSelect.value]);')) {
        $keepUntil = $i; // 0-based inclusive
        break;
    }
}
if ($keepUntil === null) {
    fwrite(STDERR, "anchor not found\n");
    exit(1);
}

$tail = <<<'TAIL'

    /* ------------------------------ Item groups / rows ------------------------------ */

    const itemTemplate = document.getElementById('tpl-item');
    const groupTemplate = document.getElementById('tpl-item-group');
    const colourTemplate = document.getElementById('tpl-colour');
    const sizeTemplate = document.getElementById('tpl-size');
    const bomTemplate = document.getElementById('tpl-bom');

    function renumberItems() {
        let n = 0;
        itemsWrap.querySelectorAll('[data-item-group]').forEach(function (group, gIndex) {
            const label = group.querySelector('.js-group-label');
            if (label) label.textContent = 'Category block ' + String.fromCharCode(65 + gIndex);
            group.querySelectorAll('[data-item]').forEach(function (el) {
                n += 1;
                const cell = el.querySelector('.item-index-label');
                if (cell) cell.textContent = String(n);
            });
        });
    }

    function ensureQuickColourSize(itemEl) {
        let colourEl = itemQuery(itemEl, '.inquiry-colour');
        if (! colourEl) {
            addColour(itemEl);
            colourEl = itemQuery(itemEl, '.inquiry-colour');
        }
        let sizeEl = colourEl.querySelector('.inquiry-size');
        if (! sizeEl) {
            addSize(colourEl);
            sizeEl = colourEl.querySelector('.inquiry-size');
            const label = sizeEl.querySelector('.js-size-label');
            if (label && ! label.value) label.value = 'Qty';
        }
        return { colourEl: colourEl, sizeEl: sizeEl };
    }

    function syncQuickToBreakdown(itemEl) {
        const quickColour = itemEl.querySelector('.js-quick-colour');
        const quickQty = itemEl.querySelector('.js-quick-qty');
        if (! quickColour && ! quickQty) return;
        const pair = ensureQuickColourSize(itemEl);
        if (quickColour) pair.colourEl.querySelector('.js-colour-name').value = quickColour.value;
        if (quickQty) pair.sizeEl.querySelector('.js-size-qty').value = quickQty.value || 0;
        recalcItem(itemEl);
    }

    function syncBreakdownToQuick(itemEl) {
        const colourEl = itemQuery(itemEl, '.inquiry-colour');
        const quickColour = itemEl.querySelector('.js-quick-colour');
        const quickQty = itemEl.querySelector('.js-quick-qty');
        if (! colourEl) return;
        if (quickColour && document.activeElement !== quickColour) {
            quickColour.value = colourEl.querySelector('.js-colour-name')?.value || '';
        }
        let qty = 0;
        itemQueryAll(itemEl, '.js-size-qty').forEach(function (input) {
            qty += parseInt(input.value || '0', 10) || 0;
        });
        if (quickQty && document.activeElement !== quickQty) quickQty.value = qty || '';
    }

    function recalcItem(itemEl) {
        let qty = 0;
        itemQueryAll(itemEl, '.inquiry-colour').forEach(function (colourEl) {
            let colourQty = 0;
            colourEl.querySelectorAll('.js-size-qty').forEach(function (input) {
                colourQty += parseInt(input.value || '0', 10) || 0;
            });
            const colourSub = colourEl.querySelector('.js-colour-subtotal');
            if (colourSub) colourSub.textContent = 'Qty ' + colourQty;
            qty += colourQty;
        });

        const quickQtyEl = itemEl.querySelector('.js-quick-qty');
        if (qty === 0 && quickQtyEl && quickQtyEl.value !== '') {
            qty = parseInt(quickQtyEl.value || '0', 10) || 0;
        }

        const price = parseFloat(itemEl.querySelector('.js-price')?.value || '0') || 0;
        const cost = parseFloat(itemEl.querySelector('.js-cost-price')?.value || '0') || 0;
        const amount = (qty * price).toFixed(2);

        const qtyDisplay = itemEl.querySelector('.js-qty-display');
        const amountDisplay = itemEl.querySelector('.js-amount-display');
        const finalCost = itemEl.querySelector('.js-final-cost');
        if (qtyDisplay) qtyDisplay.value = qty;
        if (amountDisplay) amountDisplay.value = amount;
        if (finalCost) finalCost.value = cost ? cost.toFixed(2) : '';
    }

    function addColour(itemEl) {
        const node = colourTemplate.content.cloneNode(true);
        const coloursWrap = itemQuery(itemEl, '.colours-wrap');
        if (! coloursWrap) return;
        coloursWrap.appendChild(node);
        applySizeGrid(coloursWrap.lastElementChild, itemFormatMeta(itemEl));
        recalcItem(itemEl);
    }

    function addSize(colourEl) {
        const node = sizeTemplate.content.cloneNode(true);
        colourEl.querySelector('.sizes-wrap').appendChild(node);
    }

    function initItem(itemEl) {
        const group = itemEl.closest('[data-item-group]');
        syncGroupMetaToRows(group);

        const categoryId = group?.querySelector('.js-group-category')?.value
            || itemEl.querySelector('.js-item-category')?.value
            || categorySelect.value
            || '';

        loadSelectOptions(
            itemEl.querySelector('.js-product-select'), productsUrl, categoryId,
            itemEl.dataset.productId || '', itemEl.dataset.productLabel || ''
        );
        loadSelectOptions(
            itemEl.querySelector('.js-supplier-select'), suppliersUrl, categoryId,
            itemEl.dataset.supplierId || '', itemEl.dataset.supplierLabel || ''
        );

        const unitSelect = itemEl.querySelector('.js-unit-select');
        if (unitSelect) unitSelect.dataset.selected = itemEl.dataset.unit || '';

        const meta = itemFormatMeta(itemEl);
        populateItemUnits(itemEl, meta ? meta.units : []);
        applyColumnsToItem(itemEl, meta);

        if (itemQueryAll(itemEl, '.inquiry-colour').length === 0) {
            addColour(itemEl);
            syncQuickToBreakdown(itemEl);
        }

        recalcItem(itemEl);
        syncBreakdownToQuick(itemEl);
    }

    function initGroup(groupEl) {
        if (! groupEl.querySelector('.js-group-category').value && categorySelect.value) {
            groupEl.querySelector('.js-group-category').value = categorySelect.value;
        }
        if (! groupEl.querySelector('.js-group-format').value && formatSelect.value) {
            groupEl.querySelector('.js-group-format').value = formatSelect.value;
        }
        filterGroupFormatOptions(groupEl, false);
        syncGroupMetaToRows(groupEl);

        const rows = groupEl.querySelector('.js-group-rows');
        if (rows && rows.querySelectorAll('[data-item]').length === 0) {
            addRowToGroup(groupEl);
        } else {
            rows.querySelectorAll('[data-item]').forEach(initItem);
        }
    }

    function addRowToGroup(groupEl) {
        const node = itemTemplate.content.cloneNode(true);
        const rows = groupEl.querySelector('.js-group-rows');
        rows.appendChild(node);
        const items = rows.querySelectorAll('[data-item]');
        const last = items[items.length - 1];
        syncGroupMetaToRows(groupEl);
        initItem(last);
        renumberItems();
        return last;
    }

    document.getElementById('add-item-group').addEventListener('click', function () {
        const node = groupTemplate.content.cloneNode(true);
        itemsWrap.appendChild(node);
        const groupEl = itemsWrap.lastElementChild;
        initGroup(groupEl);
        renumberItems();
    });

    itemsWrap.addEventListener('click', function (e) {
        if (e.target.closest('.js-add-group-row')) {
            addRowToGroup(e.target.closest('[data-item-group]'));
            return;
        }
        if (e.target.closest('.js-remove-group')) {
            const groups = itemsWrap.querySelectorAll('[data-item-group]');
            if (groups.length <= 1) {
                const group = e.target.closest('[data-item-group]');
                group.querySelector('.js-group-rows').innerHTML = '';
                addRowToGroup(group);
                return;
            }
            e.target.closest('[data-item-group]').remove();
            renumberItems();
            return;
        }
        if (e.target.closest('.js-remove-item')) {
            const itemEl = itemFromEventTarget(e.target);
            const costing = itemCostingRow(itemEl);
            const group = itemEl.closest('[data-item-group]');
            itemEl.remove();
            if (costing) costing.remove();
            if (group && group.querySelectorAll('[data-item]').length === 0) {
                addRowToGroup(group);
            }
            renumberItems();
            return;
        }
        if (e.target.closest('.js-toggle-costing')) {
            const itemEl = itemFromEventTarget(e.target);
            const costing = itemCostingRow(itemEl);
            const toggle = e.target.closest('.js-toggle-costing');
            if (! costing) return;
            const isHidden = costing.classList.toggle('d-none');
            toggle.classList.toggle('is-open', ! isHidden);
            toggle.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
            return;
        }
        if (e.target.closest('.js-add-bom')) {
            const itemEl = itemFromEventTarget(e.target);
            const wrap = itemQuery(itemEl, '.bom-wrap');
            if (wrap) wrap.appendChild(buildBomRow({ qty: 1, is_custom: true }));
            return;
        }
        if (e.target.closest('.js-remove-bom')) {
            const row = e.target.closest('[data-bom-row]');
            if (row) row.remove();
            return;
        }
        if (e.target.closest('.js-add-colour')) {
            addColour(itemFromEventTarget(e.target));
            return;
        }
        if (e.target.closest('.js-remove-colour')) {
            const itemEl = itemFromEventTarget(e.target);
            e.target.closest('.inquiry-colour').remove();
            recalcItem(itemEl);
            syncBreakdownToQuick(itemEl);
            return;
        }
        if (e.target.closest('.js-add-size')) {
            addSize(e.target.closest('.inquiry-colour'));
            return;
        }
        if (e.target.closest('.js-remove-size')) {
            const itemEl = itemFromEventTarget(e.target);
            e.target.closest('.inquiry-size').remove();
            recalcItem(itemEl);
            syncBreakdownToQuick(itemEl);
        }
    });

    itemsWrap.addEventListener('input', function (e) {
        const itemEl = itemFromEventTarget(e.target);
        if (! itemEl) return;

        if (e.target.classList.contains('js-quick-colour') || e.target.classList.contains('js-quick-qty')) {
            syncQuickToBreakdown(itemEl);
            renderIncentiveEstimate(itemEl);
            return;
        }
        if (e.target.classList.contains('js-desc-mirror')) {
            const hidden = itemEl.querySelector('[data-field="description"]');
            if (hidden) hidden.value = e.target.value;
            return;
        }
        if (e.target.classList.contains('js-remarks-mirror')) {
            const hidden = itemEl.querySelector('[data-field="remarks"]');
            if (hidden) hidden.value = e.target.value;
            return;
        }
        if (e.target.classList.contains('js-size-qty') || e.target.classList.contains('js-price') || e.target.classList.contains('js-cost-price')) {
            recalcItem(itemEl);
            if (e.target.classList.contains('js-size-qty')) syncBreakdownToQuick(itemEl);
            renderIncentiveEstimate(itemEl);
        }
        if (e.target.classList.contains('js-colour-name')) {
            syncBreakdownToQuick(itemEl);
        }
    });

    itemsWrap.addEventListener('change', function (e) {
        if (e.target.classList.contains('js-group-category')) {
            const group = e.target.closest('[data-item-group]');
            filterGroupFormatOptions(group, true);
            syncGroupMetaToRows(group);
            group.querySelectorAll('[data-item]').forEach(function (itemEl) {
                refreshItemProductsAndSuppliers(itemEl);
                const meta = itemFormatMeta(itemEl);
                populateItemUnits(itemEl, meta ? meta.units : []);
                applyColumnsToItem(itemEl, meta);
            });
            return;
        }
        if (e.target.classList.contains('js-group-format')) {
            const group = e.target.closest('[data-item-group]');
            syncGroupMetaToRows(group);
            group.querySelectorAll('[data-item]').forEach(function (itemEl) {
                const meta = itemFormatMeta(itemEl);
                populateItemUnits(itemEl, meta ? meta.units : []);
                applyColumnsToItem(itemEl, meta);
            });
            return;
        }
        if (e.target.classList.contains('js-product-select')) {
            const itemEl = itemFromEventTarget(e.target);
            applyProductUnit(itemEl);
            applyProductBom(itemEl);
            renderIncentiveEstimate(itemEl);
            return;
        }
        if (e.target.classList.contains('js-unit-select')) {
            e.target.dataset.selected = e.target.value;
            const itemEl = itemFromEventTarget(e.target);
            applyColumnsToItem(itemEl, itemFormatMeta(itemEl));
        }
    });

    /* ---------------------------- Buyer follow-ups ---------------------------- */

    const followUpsWrap = document.getElementById('followups-wrap');
    const followUpTemplate = document.getElementById('tpl-followup');

    document.getElementById('add-followup').addEventListener('click', function () {
        const node = followUpTemplate.content.cloneNode(true);
        followUpsWrap.appendChild(node);
    });

    followUpsWrap.addEventListener('click', function (e) {
        if (e.target.closest('.js-remove-followup')) {
            e.target.closest('.inquiry-followup').remove();
        }
    });

    /* --------------------------- Mode / status buttons --------------------------- */

    document.getElementById('btn-save-draft').addEventListener('click', function () {
        document.getElementById('mode-input').value = 'draft';
        document.getElementById('status-select').value = 'draft';
    });

    document.getElementById('btn-submit').addEventListener('click', function () {
        document.getElementById('mode-input').value = 'submit';
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

        itemsWrap.querySelectorAll('[data-item]').forEach(syncQuickToBreakdown);

        itemsWrap.querySelectorAll('[data-item]').forEach(function (itemEl, i) {
            syncGroupMetaToRows(itemEl.closest('[data-item-group]'));
            const field = function (name) {
                const el = itemQuery(itemEl, '[data-field="' + name + '"]');
                return el ? el.value : '';
            };

            appendHidden('items[' + i + '][category_id]', field('category_id'));
            appendHidden('items[' + i + '][document_format_id]', field('document_format_id'));
            appendHidden('items[' + i + '][design_no]', field('design_no'));
            appendHidden('items[' + i + '][description]', field('description'));
            appendHidden('items[' + i + '][product_id]', field('product_id'));
            appendHidden('items[' + i + '][supplier_id]', field('supplier_id'));
            appendHidden('items[' + i + '][unit]', field('unit'));
            appendHidden('items[' + i + '][fob_value_id]', field('fob_value_id'));
            appendHidden('items[' + i + '][price]', field('price'));
            appendHidden('items[' + i + '][cost_price]', field('cost_price'));
            appendHidden('items[' + i + '][status]', field('status'));
            appendHidden('items[' + i + '][remarks]', field('remarks'));

            itemQueryAll(itemEl, '.inquiry-colour').forEach(function (colourEl, j) {
                appendHidden('items[' + i + '][colours][' + j + '][colour]', colourEl.querySelector('.js-colour-name').value);
                colourEl.querySelectorAll(':scope .sizes-wrap > .inquiry-size').forEach(function (sizeEl, k) {
                    appendHidden('items[' + i + '][colours][' + j + '][sizes][' + k + '][size]', sizeEl.querySelector('.js-size-label').value);
                    appendHidden('items[' + i + '][colours][' + j + '][sizes][' + k + '][qty]', sizeEl.querySelector('.js-size-qty').value || 0);
                });
            });

            itemQueryAll(itemEl, '[data-bom-row]').forEach(function (bomEl, b) {
                appendHidden('items[' + i + '][bom][' + b + '][component_name]', bomEl.querySelector('.js-bom-name').value);
                appendHidden('items[' + i + '][bom][' + b + '][qty]', bomEl.querySelector('.js-bom-qty').value || 0);
                appendHidden('items[' + i + '][bom][' + b + '][unit]', bomEl.querySelector('.js-bom-unit').value);
                appendHidden('items[' + i + '][bom][' + b + '][remarks]', bomEl.querySelector('.js-bom-remarks').value);
                appendHidden('items[' + i + '][bom][' + b + '][is_custom]', bomEl.dataset.isCustom === '0' ? '0' : '1');
            });

            itemQueryAll(itemEl, '[data-custom-key]').forEach(function (fieldEl) {
                appendHidden('items[' + i + '][custom][' + fieldEl.dataset.customKey + ']', fieldEl.querySelector('input').value);
            });
        });

        followUpsWrap.querySelectorAll(':scope > .inquiry-followup').forEach(function (fEl, i) {
            if (fEl.dataset.id) appendHidden('followups[' + i + '][id]', fEl.dataset.id);
            appendHidden('followups[' + i + '][date]', fEl.querySelector('.js-followup-date').value);
            appendHidden('followups[' + i + '][comment]', fEl.querySelector('.js-followup-comment').value);
        });
    });

    /* --------------------------------- Init --------------------------------- */

    applyBuyerCategoryFilter(false);
    applyCategoryFormatFilter(false);
    applyFormatMeta();
    itemsWrap.querySelectorAll('[data-item-group]').forEach(initGroup);
    renumberItems();
});
</script>
@endpush

<template id="tpl-item-group">
    @include('sales.inquiries._item_group', ['isGroupTemplate' => true, 'groupItems' => []])
</template>

<template id="tpl-item">
    @include('sales.inquiries._item_row', ['isTemplate' => true])
</template>

<template id="tpl-colour">
    <div class="inquiry-colour border rounded p-2 mb-2 bg-body" data-colour>
        <div class="d-flex align-items-center gap-2 mb-2">
            <input type="text" class="form-control form-control-sm js-colour-name" placeholder="Colour" maxlength="60" style="max-width:12rem">
            <span class="badge text-bg-light border js-colour-subtotal ms-auto">Qty 0</span>
            <button type="button" class="btn btn-sm btn-outline-danger js-remove-colour"><i class="bi bi-trash"></i></button>
        </div>
        <div class="sizes-wrap d-flex flex-wrap gap-2 mb-2"></div>
        <button type="button" class="btn btn-sm btn-outline-secondary js-add-size">
            <i class="bi bi-plus-lg me-1"></i>Add size
        </button>
    </div>
</template>

<template id="tpl-size">
    <div class="inquiry-size d-flex align-items-center gap-1 border rounded px-1 py-1 bg-body-tertiary" data-size style="max-width:12rem">
        <input type="text" class="form-control form-control-sm js-size-label border-0" placeholder="Size" maxlength="20" style="width:4.5rem">
        <input type="number" min="0" class="form-control form-control-sm js-size-qty" placeholder="Qty" style="width:4.5rem">
        <button type="button" class="btn btn-sm btn-outline-danger js-remove-size"><i class="bi bi-x"></i></button>
    </div>
</template>

<template id="tpl-bom">
    <div class="row g-1 align-items-end mb-1 inquiry-bom-row" data-bom-row data-is-custom="1">
        <div class="col-md-4">
            <input type="text" class="form-control form-control-sm js-bom-name" placeholder="Component" maxlength="200">
        </div>
        <div class="col-md-2">
            <input type="number" step="0.0001" min="0" class="form-control form-control-sm js-bom-qty" placeholder="Qty/pc" value="1">
        </div>
        <div class="col-md-2">
            <input type="text" class="form-control form-control-sm js-bom-unit" placeholder="Unit" maxlength="20">
        </div>
        <div class="col-md-3">
            <input type="text" class="form-control form-control-sm js-bom-remarks" placeholder="Remarks" maxlength="500">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-sm btn-outline-danger w-100 js-remove-bom"><i class="bi bi-x"></i></button>
        </div>
    </div>
</template>

<template id="tpl-followup">
    <div class="inquiry-followup d-flex gap-2 mb-2" data-followup>
        <input type="date" class="form-control form-control-sm js-followup-date" style="max-width:11rem">
        <input type="text" class="form-control form-control-sm js-followup-comment" placeholder="Comment…">
        <button type="button" class="btn btn-sm btn-outline-danger js-remove-followup"><i class="bi bi-x"></i></button>
    </div>
</template>
TAIL;

$out = implode('', array_slice($lines, 0, $keepUntil + 1)).$tail;
file_put_contents($f, $out);
echo "OK kept=".($keepUntil + 1)." lines, new_total=".substr_count($out, "\n")."\n";
