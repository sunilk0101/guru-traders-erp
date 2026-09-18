@props(['inquiry' => null])

@php
    $isEdit = (bool) $inquiry;
    $val = fn (string $field, $default = null) => old($field, $isEdit ? $inquiry->{$field} : $default);

    // Computed here rather than inline inside @json() below — Blade's
    // directive-argument parser truncates silently on a long multi-line
    // nested array expression, so the array is built as a plain variable
    // first and @json() is only ever given a simple variable to echo.
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
        // Standard toggleable columns — enabled/label per this format,
        // falling back to the STANDARD default for a format saved before a
        // column existed. print_only ones (image) never reach a data-entry
        // screen, so they're left out entirely — same filter
        // DocumentFormat::screenColumns() applies.
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
                // Only meaningful on 'size' — the fixed qty-per-tag grid on
                // the item row reads this off the Size column specifically.
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

<input type="hidden" name="mode" id="mode-input" value="{{ old('mode', 'submit') }}">

<x-ui.form-section title="Inquiry Identity" icon="bi-chat-square-text"
                   subtitle="→ Buyer Master · Agent Master · OC on confirmation">
    <div class="form-stack">
        <div class="row form-line">
            <label class="col-sm-4 col-lg-3 col-form-label fw-semibold">Inquiry No.</label>
            <div class="col-sm-8 col-lg-9">
                <input type="text" class="form-control bg-body-tertiary" readonly
                       value="{{ $isEdit ? $inquiry->inquiry_no : $numberPreview.' (auto)' }}">
                <div class="form-text">FY {{ $financialYear }}</div>
            </div>
        </div>

        <x-ui.field name="inquiry_date" label="Date" type="date" required horizontal
                    :value="$val('inquiry_date') instanceof \Carbon\CarbonInterface ? $val('inquiry_date')->format('Y-m-d') : $val('inquiry_date', now()->format('Y-m-d'))" />

        <x-ui.field name="buyer_ref" label="Buyer's Ref / Season" horizontal
                    :value="$val('buyer_ref')" placeholder="e.g. SS-2026" />

        {{-- Change request #8 — quick-add: typing a name not already in the
             list adds it, replacing the old "Other" + free-text box. --}}
        <x-ui.select name="source_id" label="Source" required horizontal searchable
                     :options="$sources" :selected="$val('source_id')"
                     placeholder="Search or type to add a new source…"
                     data-create-url="{{ route('sales.inquiries.sources.store') }}" />

        <x-ui.select name="buyer_id" label="Buyer" required horizontal searchable
                     :options="$buyers->pluck('label', 'id')" :selected="$val('buyer_id')" />
    </div>
</x-ui.form-section>

<x-ui.form-section title="Order Format & Terms" icon="bi-file-earmark-ruled"
                   subtitle="Defaults for new item lines — each line can use a different category / format.">
    <div class="form-stack">
        <x-ui.select name="category_id" label="Default Category" horizontal searchable
                     :options="$categories" :selected="$val('category_id')"
                     hint="Copied onto each new item line. Change per line below if needed." />

        <x-ui.select name="document_format_id" label="Default Order Format" horizontal searchable
                     :options="$formats->pluck('name', 'id')" :selected="$val('document_format_id')"
                     hint="Copied onto each new item line. Change per line below if needed." />

        <div class="row form-line">
            <label for="default_bom_template" class="col-sm-4 col-lg-3 col-form-label fw-semibold">Default BOM Cost</label>
            <div class="col-sm-8 col-lg-9">
                <select id="default_bom_template" class="form-select" data-searchable data-placeholder="— None —">
                    <option value="">— None —</option>
                    @foreach(($bomTemplates ?? collect()) as $template)
                        <option value="{{ $template['key'] }}">{{ $template['name'] }} (₹{{ number_format((float) $template['total'], 2) }})</option>
                    @endforeach
                </select>
                <div class="form-text">Applied automatically to every new item row's BOM cost — pick from the dropdown once instead of on every line. Change per row below if needed.</div>
            </div>
        </div>

        <div class="row form-line">
            <label class="col-sm-4 col-lg-3 col-form-label fw-semibold">Format Type</label>
            <div class="col-sm-8 col-lg-9">
                <input type="text" class="form-control bg-body-tertiary" id="format_type" readonly
                       placeholder="— From default format —">
            </div>
        </div>

        <x-ui.select name="agent_id" label="Agent" horizontal searchable
                     :options="$agents" :selected="$val('agent_id')" hint="Pre-fills from Buyer Master." />

        @php
            $commissionType = old('agent_commission_type', $val('agent_commission_type'));
            $commissionValue = old('agent_commission_value', $val('agent_commission_value'));
        @endphp
        <div class="row form-line">
            <label for="agent_commission_type" class="col-sm-4 col-lg-3 col-form-label fw-semibold">
                Commission Type
            </label>
            <div class="col-sm-8 col-lg-9">
                <select id="agent_commission_type" class="form-select" disabled>
                    <option value="">— Select —</option>
                    <option value="percent" @selected($commissionType === 'percent')>Percent</option>
                    <option value="flat" @selected($commissionType === 'flat')>Flat</option>
                </select>
                <input type="hidden" name="agent_commission_type" id="agent_commission_type_hidden"
                       value="{{ $commissionType }}">
                <div class="form-text">From Agent Master — not editable on Inquiry.</div>
            </div>
        </div>

        <div class="row form-line">
            <label for="agent_commission_value" class="col-sm-4 col-lg-3 col-form-label fw-semibold">
                Commission
            </label>
            <div class="col-sm-8 col-lg-9">
                <input type="number" id="agent_commission_value" name="agent_commission_value"
                       value="{{ $commissionValue }}" placeholder="0.00" readonly
                       class="form-control bg-body-tertiary">
                <div class="form-text">From Agent Master — not editable on Inquiry.</div>
            </div>
        </div>

        <x-ui.select name="currency_id" label="Currency" required horizontal searchable
                     :options="$currencies" :selected="$val('currency_id')" hint="Pre-fills from Buyer Master." />

        {{-- H-08: was labelled "Exchange Rate (₹)" as if the rate itself were a
             rupee amount, when it's a ratio (buyer currency → INR) — renamed
             to say exactly what it converts to and removed the misleading ₹. --}}
        <x-ui.field name="exchange_rate" label="Exchange Rate (to INR)" type="number" horizontal
                    :value="$val('exchange_rate')" placeholder="e.g. 88.50" />

        <x-ui.field name="expected_shipment_date" label="Expected Shipment Date" type="date" horizontal
                    :value="$val('expected_shipment_date') instanceof \Carbon\CarbonInterface ? $val('expected_shipment_date')->format('Y-m-d') : $val('expected_shipment_date')" />

        <x-ui.field name="remarks" label="Remarks" horizontal
                    :value="$val('remarks')" placeholder="General remarks…" />
    </div>
</x-ui.form-section>

<x-ui.form-section title="Items, Costing & Follow-ups" icon="bi-table"
                   subtitle="Add a category block, then fill many item rows in the table — like Excel. Different categories = separate blocks.">
    <div id="items-wrap">
        @php
            $existingItems = old('items', $isEdit ? $inquiry->items : []);
            $grouped = [];
            foreach ($existingItems as $item) {
                $isArr = is_array($item);
                $cid = (string) ($isArr ? ($item['category_id'] ?? '') : ($item->category_id ?? ''));
                $fid = (string) ($isArr ? ($item['document_format_id'] ?? '') : ($item->document_format_id ?? ''));
                $key = $cid.'|'.$fid;
                $grouped[$key]['category_id'] = $cid;
                $grouped[$key]['document_format_id'] = $fid;
                $grouped[$key]['items'][] = $item;
            }
        @endphp

        @forelse($grouped as $group)
            @include('sales.inquiries._item_group', [
                'gCategory' => $group['category_id'],
                'gFormat' => $group['document_format_id'],
                'groupItems' => $group['items'],
            ])
        @empty
            @include('sales.inquiries._item_group', [
                'gCategory' => old('category_id', $isEdit ? $inquiry->category_id : ''),
                'gFormat' => old('document_format_id', $isEdit ? $inquiry->document_format_id : ''),
                'groupItems' => [],
            ])
        @endforelse
    </div>

    @error('items')
        <div class="invalid-feedback d-block mb-2">{{ $message }}</div>
    @enderror

    <button type="button" id="add-item-group" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-plus-lg me-1"></i>Add category block
    </button>

    <hr class="my-4">

    <h6 class="fw-semibold mb-2">Buyer Follow-up</h6>
    <p class="text-body-secondary small">Track all buyer communication — dates &amp; comments.</p>

    <div id="followups-wrap">
        @php $existingFollowUps = old('followups', $isEdit ? $inquiry->followUps : []); @endphp

        @foreach($existingFollowUps as $followUp)
            @php
                $fIsArr = is_array($followUp);
                $fId = $fIsArr ? ($followUp['id'] ?? '') : $followUp->id;
                $fDate = $fIsArr ? ($followUp['date'] ?? '') : $followUp->follow_up_date?->format('Y-m-d');
                $fComment = $fIsArr ? ($followUp['comment'] ?? '') : $followUp->comment;
            @endphp
            <div class="inquiry-followup d-flex gap-2 mb-2" data-followup data-id="{{ $fId }}">
                <input type="date" class="form-control form-control-sm js-followup-date" style="max-width:11rem" value="{{ $fDate }}">
                <input type="text" class="form-control form-control-sm js-followup-comment" placeholder="Comment…" value="{{ $fComment }}">
                <button type="button" class="btn btn-sm btn-outline-danger js-remove-followup"><i class="bi bi-x"></i></button>
            </div>
        @endforeach
    </div>

    <button type="button" id="add-followup" class="btn btn-sm btn-outline-secondary w-100">
        <i class="bi bi-plus-lg me-1"></i>Add buyer follow-up
    </button>
</x-ui.form-section>

{{-- M-06: no longer required here — see InquiryRequest for why. Order
     Confirmation still requires both once the deal is actually confirmed. --}}
<x-ui.form-section title="Delivery & Packing Details" icon="bi-box-seam"
                   subtitle="Optional at Inquiry stage; pre-fills from Order Format when it has defaults, editable per inquiry. Required once confirmed to an Order Confirmation. Reference images come from the format (print defaults).">
    <div class="form-stack">
        <x-ui.textarea name="delivery_details" label="Delivery Details" horizontal
                       rows="3" :value="$val('delivery_details')" />

        <x-ui.textarea name="packing_details" label="Packing Details" horizontal
                       rows="3" :value="$val('packing_details')" />

        <div class="row form-line">
            <label class="col-sm-4 col-lg-3 col-form-label fw-semibold">Format reference images</label>
            <div class="col-sm-8 col-lg-9">
                <div id="format-reference-images" class="d-flex flex-wrap gap-3 text-body-secondary small">
                    Pick an Order Format to load its packing / marking reference images.
                </div>
            </div>
        </div>
    </div>
</x-ui.form-section>

<x-ui.form-section title="Module Connections" icon="bi-diagram-3"
                   subtitle="This inquiry feeds into the following modules.">
    <div class="d-flex flex-wrap gap-2">
        @foreach(['Buyer Master', 'Agent Master', 'Order Format', 'Order Confirmations (on confirmation)'] as $module)
            <span class="badge text-bg-light border fw-normal">{{ $module }}</span>
        @endforeach
    </div>
</x-ui.form-section>

<div class="form-actions d-flex flex-wrap gap-2 align-items-center">
    <div class="me-auto">
        <label class="form-label small text-body-secondary mb-1">Status</label>
        @php
            // H-06: 'Converted to OC' is set by OrderConfirmationService once
            // an actual Order Confirmation exists for every line (see
            // InquiryRequest::withValidator, which also rejects it
            // server-side) — it is not a stage a user picks by hand, or a
            // brand-new Draft inquiry could be saved as "Converted to OC"
            // with no OC behind it at all. Only offered here when the
            // inquiry is already in that state, so its own status line still
            // renders correctly on the Edit screen.
            $selectableStatuses = ($isEdit && $inquiry->status === 'converted_to_oc')
                ? $statuses
                : collect($statuses)->except('converted_to_oc');
        @endphp
        <select name="status" id="status-select" class="form-select form-select-sm">
            @foreach($selectableStatuses as $value => $label)
                <option value="{{ $value }}" @selected($val('status', 'draft') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <a href="{{ $isEdit ? route('sales.inquiries.show', $inquiry) : route('sales.inquiries.index') }}"
       class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" id="btn-save-draft" class="btn btn-outline-secondary">
        <i class="bi bi-save me-1"></i>Save Draft
    </button>
    <button type="submit" id="btn-submit" class="btn btn-primary px-4">
        <i class="bi bi-check-lg me-1"></i>Submit
    </button>
</div>

@push('styles')
<style>
    .js-toggle-costing .js-costing-chevron { transition: transform .15s ease; }
    .js-toggle-costing.is-open .js-costing-chevron { transform: rotate(90deg); }

    /* "I need the columns to be showcased properly so I can see what the
       values are" — the Excel-style item table is wide by nature (16
       columns), so instead of squeezing text it gets a visible, obviously
       scrollable strip and slightly tighter cell padding to fit more
       without truncating any value. */
    .inquiry-items-table th,
    .inquiry-items-table td {
        padding-left: .4rem;
        padding-right: .4rem;
    }
    .inquiry-items-table .form-control,
    .inquiry-items-table .form-select {
        font-size: .8125rem;
        padding-left: .4rem;
        padding-right: .4rem;
    }
    .inquiry-items-table input[readonly] {
        text-align: right;
    }
    .inquiry-item-group .table-responsive {
        scrollbar-width: thin;
        border-bottom: 1px solid var(--bs-border-color);
    }
    .inquiry-item-group .table-responsive::-webkit-scrollbar {
        height: 10px;
    }
    .inquiry-item-group .table-responsive::-webkit-scrollbar-thumb {
        background-color: var(--bs-secondary-color);
        border-radius: 6px;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('inquiry-form');

    /* ------------------------- Reference data from server ------------------------- */

    const buyers = @json($buyersJs);

    const formats = @json($formatsJs);

    const agentCommissions = @json($agentCommissions ?? []);

    const productsUrl  = "{{ route('sales.inquiries.products') }}";
    const suppliersUrl = "{{ route('sales.inquiries.suppliers') }}";
    const quoteFobUrl  = "{{ route('sales.inquiries.quote-fob') }}";
    const defaultBomLines = @json($defaultBomLines ?? []);
    const bomTemplates = @json(($bomTemplates ?? collect())->values());
    const trimAccessories = @json($trimAccessoriesJs ?? []);

    const buyerSelect    = document.getElementById('buyer_id');
    const categorySelect = document.getElementById('category_id');
    const formatSelect   = document.getElementById('document_format_id');
    const formatTypeEl   = document.getElementById('format_type');
    const deliveryEl     = document.getElementById('delivery_details');
    const packingEl      = document.getElementById('packing_details');
    const itemsWrap      = document.getElementById('items-wrap');
    const defaultBomTemplateEl = document.getElementById('default_bom_template');
    const agentSelect    = document.getElementById('agent_id');
    const commissionTypeEl = document.getElementById('agent_commission_type');
    const commissionTypeHidden = document.getElementById('agent_commission_type_hidden');
    const commissionValueEl = document.getElementById('agent_commission_value');

    /* ------------------------------ Cascades ------------------------------ */

    /**
     * Narrows which options show, and — only when clearIfInvalid is true —
     * wipes the selection if it's no longer among them.
     *
     * clearIfInvalid must stay false on the initial page load. An edit
     * screen opens with whatever category/format the record was actually
     * saved with, which may no longer match the *current* Category Master
     * links (the link can be added, removed or simply never configured
     * after the record was saved). Clearing it then would silently blank
     * out a real saved value the moment the page loads, before the user has
     * touched anything. The clear is only correct as the direct consequence
     * of the user themselves changing the parent dropdown — set true from
     * the buyer/category 'change' listeners below, never from init.
     */
    function filterOptionsByCategory(selectEl, allowedIds, clearIfInvalid) {
        // C-04: guard against a non-<select> match. TomSelect's rendered
        // wrapper <div> copies the original <select>'s class list onto
        // itself (so it keeps the same Bootstrap sizing/spacing classes),
        // which means a plain '.js-group-category' selector matches BOTH
        // the real (now-hidden) <select> and the wrapper div sitting next
        // to it. selectEl.options is undefined on the div, so
        // Array.from(undefined) used to throw "undefined is not iterable"
        // and aborted the whole buyer-change handler — this is what broke
        // Agent/Commission/Currency prefill on Buyer selection.
        if (! selectEl || ! selectEl.options) return;

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
        const allowed = buyer ? buyer.categories : null;
        filterOptionsByCategory(categorySelect, allowed, clearIfInvalid);
        // Scope to real <select> elements only — see the guard note in
        // filterOptionsByCategory() above for why '.js-group-category'
        // alone (without the `select` tag qualifier) is unsafe here.
        itemsWrap.querySelectorAll('select.js-group-category').forEach(function (sel) {
            filterOptionsByCategory(sel, allowed, clearIfInvalid);
        });
    }

    function applyCategoryFormatFilter(clearIfInvalid) {
        const categoryId = categorySelect.value ? Number(categorySelect.value) : null;
        Array.from(formatSelect.options).forEach(function (opt) {
            if (opt.value === '') return;
            const meta = formats[opt.value];
            opt.hidden = categoryId && meta ? ! meta.categories.includes(categoryId) : false;
        });
        if (clearIfInvalid && formatSelect.selectedOptions[0] && formatSelect.selectedOptions[0].hidden) {
            setSelectValue(formatSelect, '');
        }
    }

    function filterGroupFormatOptions(groupEl, clearIfInvalid) {
        const categorySel = groupEl.querySelector('.js-group-category');
        const formatSel = groupEl.querySelector('.js-group-format');
        const categoryId = categorySel && categorySel.value ? Number(categorySel.value) : null;
        Array.from(formatSel.options).forEach(function (opt) {
            if (opt.value === '') return;
            const meta = formats[opt.value];
            opt.hidden = categoryId && meta ? ! meta.categories.includes(categoryId) : false;
        });
        if (clearIfInvalid && formatSel.selectedOptions[0] && formatSel.selectedOptions[0].hidden) {
            formatSel.value = '';
        }
        // Auto-pick when only one format is visible for this category.
        const visible = Array.from(formatSel.options).filter(function (opt) {
            return opt.value !== '' && ! opt.hidden;
        });
        if (! formatSel.value && visible.length === 1) {
            formatSel.value = visible[0].value;
        }
    }

    function syncGroupMetaToRows(groupEl) {
        const categoryId = groupEl.querySelector('.js-group-category')?.value || '';
        const formatId = groupEl.querySelector('.js-group-format')?.value || '';
        groupEl.querySelectorAll('[data-item]').forEach(function (itemEl) {
            const cat = itemEl.querySelector('.js-item-category');
            const fmt = itemEl.querySelector('.js-item-format');
            if (cat) cat.value = categoryId;
            if (fmt) fmt.value = formatId;
        });
    }

    function itemCostingRow(itemEl) {
        const next = itemEl.nextElementSibling;
        return next && next.matches('[data-item-costing]') ? next : null;
    }

    function itemFromEventTarget(target) {
        return target.closest('[data-item]')
            || target.closest('[data-item-costing]')?.previousElementSibling
            || null;
    }

    function itemQuery(itemEl, selector) {
        const costing = itemCostingRow(itemEl);
        return itemEl.querySelector(selector) || (costing ? costing.querySelector(selector) : null);
    }

    function itemQueryAll(itemEl, selector) {
        const costing = itemCostingRow(itemEl);
        const a = Array.from(itemEl.querySelectorAll(selector));
        const b = costing ? Array.from(costing.querySelectorAll(selector)) : [];
        return a.concat(b);
    }

    function itemFormatMeta(itemEl) {
        const group = itemEl.closest('[data-item-group]');
        const formatSel = group?.querySelector('.js-group-format')
            || itemEl.querySelector('.js-item-format');
        const id = formatSel && formatSel.value ? formatSel.value : formatSelect.value;
        return formats[id] || null;
    }

    function applyFormatMeta() {
        const meta = formats[formatSelect.value];
        formatTypeEl.value = meta ? meta.module : '';
        // Header default format only drives delivery/packing/images — item
        // columns/units come from each line's own Order Format.
    }

    function populateItemUnits(itemEl, units) {
        const select = itemEl.querySelector('.js-unit-select');
        if (! select) return;
        const current = select.dataset.selected || select.value;
        select.innerHTML = '<option value="">—</option>';
        (units || []).forEach(function (unit) {
            const opt = document.createElement('option');
            opt.value = unit;
            opt.textContent = unit;
            if (unit === current) opt.selected = true;
            select.appendChild(opt);
        });
        ensureUnitOption(select, current);
        if (current) select.value = current;
    }

    /**
     * Reflects the selected Order Format's column settings onto one item row
     * — which fields are shown, what they're labelled, the Price column's
     * unit suffix (mirrors DocumentFormat::priceLabel() server-side), colour
     * naming, and any custom columns the format defines. Called both when
     * the format changes and when a new item row is added, so a row built
     * after the format was already picked starts in the right shape too.
     */
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

        // A single colour row always exists to carry the size breakdown even
        // when the format has multi-colour off — only the ability to name it
        // depends on the format's own colour column.
        const colourNamesEnabled = !! (meta && meta.allow_multiple_colours && (! columns.colour || columns.colour.enabled));
        itemQueryAll(itemEl, '.js-colour-name').forEach(function (input) {
            input.classList.toggle('d-none', ! colourNamesEnabled);
        });

        itemQueryAll(itemEl, '.inquiry-colour').forEach(function (colourEl) {
            applySizeGrid(colourEl, meta);
        });

        applyCustomColumns(itemEl, (meta && meta.customColumns) || []);

        const addColourBtn = itemQuery(itemEl, '.js-add-colour');
        if (addColourBtn) {
            addColourBtn.classList.toggle('d-none', ! (meta && meta.allow_multiple_colours));
        }
    }

    /**
     * The Size column's sub-columns (set on the Order Format) turn a colour
     * row's free-form "+ Add size" entry into a fixed grid — one qty box per
     * tag. Reuses the exact same .inquiry-size / .js-size-label / .js-size-qty
     * shape the free-form rows already use, so the submit handler's colour/size
     * compile loop needs no changes at all — it just sees rows either way.
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
            // Free-form mode — leave whatever rows already exist (typed by
            // the user, or loaded from a saved item); just make sure a row
            // built while a grid-format was selected is editable again.
            sizesWrap.querySelectorAll('.inquiry-size').forEach(function (row) {
                row.classList.remove('is-grid');
                row.querySelector('.js-size-label').readOnly = false;
                row.querySelector('.js-remove-size').classList.remove('d-none');
            });
            if (addSizeBtn) addSizeBtn.classList.remove('d-none');
            return;
        }

        // Grid mode — keep any qty already typed against a tag the format
        // still offers, drop rows for tags it no longer offers, add rows for
        // new ones. Keyed by label, same "keep what's there" idea
        // loadSelectOptions() uses elsewhere in this file.
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

    /**
     * One text input per custom column the format defines, appended after
     * the standard fields. Rebuilt on every format change — existing typed
     * values for a key are kept if that key is still offered, a saved
     * item's data-custom-values seeds the fields on first load.
     */
    function applyCustomColumns(itemEl, customColumns) {
        const wrap = itemQuery(itemEl, '.custom-fields-wrap');
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

    function ensureUnitOption(select, unit) {
        if (! unit) return;
        const exists = Array.from(select.options).some(function (o) { return o.value === unit; });
        if (exists) return;
        const opt = document.createElement('option');
        opt.value = unit;
        opt.textContent = unit;
        select.appendChild(opt);
    }

    /**
     * Inquiry is buyer/export-facing, so prefer Product.unit_export, then
     * unit_po. Only runs on user product change — never overwrites a saved
     * unit during initItem / page load.
     */
    function applyProductUnit(itemEl) {
        const productSelect = itemEl.querySelector('.js-product-select');
        const unitSelect = itemEl.querySelector('.js-unit-select');
        const opt = productSelect && productSelect.selectedOptions[0];
        if (! opt || ! opt.value) return;

        const preferred = opt.dataset.unitExport || opt.dataset.unitPo || '';
        if (! preferred) return;

        ensureUnitOption(unitSelect, preferred);
        unitSelect.value = preferred;
        unitSelect.dataset.selected = preferred;
        applyColumnsToItem(itemEl, itemFormatMeta(itemEl));
    }

    /**
     * "I need a small image against every line ... I need it to show up here
     * as well" — mirrors the selected product's photo onto every .js-product-thumb
     * in the row, which is both the thumbnail next to the Product select and
     * the one in the BOM trims panel header (itemQueryAll spans both).
     */
    function updateProductThumb(itemEl) {
        const select = itemEl.querySelector('.js-product-select');
        const opt = select && select.selectedOptions[0];
        const url = opt && opt.dataset.image ? opt.dataset.image : '';
        itemQueryAll(itemEl, '.js-product-thumb').forEach(function (thumb) {
            if (url) {
                thumb.src = url;
                thumb.classList.remove('d-none');
            } else {
                thumb.src = '';
                thumb.classList.add('d-none');
            }
        });
    }

    /**
     * "I need a small image against every line which will be added while
     * making the bom cost. I need it to show up here as well" — matches a
     * BOM trims row's typed name (Main Label, Zip, ...) against the Trim /
     * Accessory catalog (case-insensitive) and shows its reference photo.
     */
    function trimThumbUrl(name) {
        const key = (name || '').trim().toLowerCase();
        return key && trimAccessories && trimAccessories[key] ? trimAccessories[key] : '';
    }

    function updateTrimThumb(rowEl) {
        if (! rowEl) return;
        const nameInput = rowEl.querySelector('.js-bom-name');
        const thumb = rowEl.querySelector('.js-trim-thumb');
        if (! nameInput || ! thumb) return;
        const url = trimThumbUrl(nameInput.value);
        if (url) {
            thumb.src = url;
            thumb.classList.remove('d-none');
        } else {
            thumb.src = '';
            thumb.classList.add('d-none');
        }
    }

    /**
     * "the code should come immediately in the design number once I choose
     * the supplier ... AJC- (here I'll type the design number)". Seeds the
     * supplier's display code as a prefix the moment a supplier is picked,
     * and swaps the prefix if the supplier is changed again — but leaves any
     * other text the user actually typed alone.
     */
    function applySupplierCodeToDesign(itemEl, supplierSelect) {
        const designInput = itemEl.querySelector('[data-field="design_no"]');
        if (! designInput) return;

        const opt = supplierSelect.selectedOptions[0];
        const code = opt && opt.dataset.code ? opt.dataset.code : '';
        const current = designInput.value || '';
        const prevCode = designInput.dataset.autoSupplierCode || '';

        const isBlank = ! current;
        const isUntouchedAutoPrefix = prevCode && (current === prevCode + '-' || current.startsWith(prevCode + '-'));

        if (isBlank || isUntouchedAutoPrefix) {
            const rest = isUntouchedAutoPrefix ? current.slice(prevCode.length + 1) : '';
            designInput.value = code ? code + '-' + rest : rest;
        }
        designInput.dataset.autoSupplierCode = code;
    }

    function applyProductBom(itemEl) {
        // Product BOM trims stay in Product Master. Inquiry uses the BOM cost dropdown.
    }

    function findBomTemplate(key) {
        return (bomTemplates || []).find(function (row) { return row.key === key; }) || null;
    }

    function applyBomTemplate(itemEl, key) {
        const wrap = itemQuery(itemEl, '.bom-wrap');
        const select = itemEl.querySelector('.js-bom-template');
        if (! wrap) return;

        if (! key) {
            wrap.innerHTML = '';
            const customOpt = select?.querySelector('.js-bom-custom-opt');
            if (customOpt) customOpt.hidden = true;
            recalcItem(itemEl);
            return;
        }

        const template = findBomTemplate(key);
        if (! template) return;

        wrap.innerHTML = '';
        (template.lines || []).forEach(function (row) {
            wrap.appendChild(buildBomRow(Object.assign({}, row, { is_custom: true })));
        });
        if (select) select.value = key;
        const customOpt = select?.querySelector('.js-bom-custom-opt');
        if (customOpt) customOpt.hidden = true;
        recalcItem(itemEl);
    }

    function markBomCustom(itemEl) {
        const select = itemEl.querySelector('.js-bom-template');
        const customOpt = select?.querySelector('.js-bom-custom-opt');
        const cost = parseFloat(itemEl.querySelector('.js-bom-cost')?.value) || 0;
        if (! select || ! customOpt) return;
        customOpt.hidden = cost <= 0;
        customOpt.textContent = 'Custom (₹' + cost.toFixed(2) + ')';
        select.value = cost > 0 ? 'custom' : '';
    }

    function syncBomDropdown(itemEl) {
        const select = itemEl.querySelector('.js-bom-template');
        if (! select) return;
        const cost = parseFloat(itemEl.querySelector('.js-bom-cost')?.value) || 0;
        const match = (bomTemplates || []).find(function (row) {
            return Math.abs((parseFloat(row.total) || 0) - cost) < 0.005 && cost > 0;
        });
        const customOpt = select.querySelector('.js-bom-custom-opt');
        if (match) {
            select.value = match.key;
            if (customOpt) customOpt.hidden = true;
            return;
        }
        if (cost > 0 && customOpt) {
            customOpt.hidden = false;
            customOpt.textContent = 'Custom (₹' + cost.toFixed(2) + ')';
            select.value = 'custom';
        } else if (customOpt) {
            customOpt.hidden = true;
            if (select.value === 'custom') select.value = '';
        }
    }

    function renderIncentiveEstimate(itemEl) {
        const box = itemQuery(itemEl, '[data-incentives-box]');
        const host = itemQuery(itemEl, '.js-incentive-estimate');
        const productSelect = itemEl.querySelector('.js-product-select');
        const opt = productSelect && productSelect.selectedOptions[0];
        if (! box || ! host) return;

        let schemes = [];
        try { schemes = JSON.parse(opt?.dataset?.incentives || '[]') || []; } catch (e) { schemes = []; }

        if (! schemes.length) {
            box.classList.add('d-none');
            host.textContent = 'Select a product with schemes to estimate.';
            return;
        }

        box.classList.remove('d-none');
        const price = parseFloat(itemEl.querySelector('.js-price')?.value) || 0;
        const qty = parseFloat(itemEl.querySelector('.js-qty-display')?.value) || 0;
        const fobAmount = price * qty;

        host.innerHTML = schemes.map(function (row) {
            const ratePct = (parseFloat(row.percent_1) || 0) + (parseFloat(row.percent_2) || 0);
            const rateAmt = fobAmount * (ratePct / 100);
            const hasCap = row.cap_value !== null && row.cap_value !== undefined;
            const capAmt = hasCap ? qty * (parseFloat(row.cap_value) || 0) : null;
            const claim = capAmt === null ? rateAmt : Math.min(rateAmt, capAmt);
            const capText = hasCap ? ('Cap×PCS ₹' + capAmt.toFixed(2)) : 'no cap';
            return '<div><strong>' + (row.label || row.scheme) + '</strong>: '
                + 'Rate×FOB ₹' + rateAmt.toFixed(2) + ' · ' + capText
                + ' → <span class="text-success">Claim ₹' + claim.toFixed(2) + '</span></div>';
        }).join('');
    }

    function buildBomRow(data) {
        data = data || {};
        const node = bomTemplate.content.cloneNode(true);
        const row = node.querySelector('[data-bom-row]');
        const isCustom = data.is_custom === true || data.is_custom === 1 || data.is_custom === '1' || data.is_custom === undefined;
        row.dataset.isCustom = isCustom ? '1' : '0';
        row.querySelector('.js-bom-name').value = data.component_name || '';
        row.querySelector('.js-bom-size').value = data.size || '';
        row.querySelector('.js-bom-qty').value = data.qty != null ? data.qty : 1;
        row.querySelector('.js-bom-rate').value = data.rate != null ? data.rate : '';
        row.querySelector('.js-bom-remarks').value = data.remarks || '';
        const q = parseFloat(row.querySelector('.js-bom-qty').value) || 0;
        const r = parseFloat(row.querySelector('.js-bom-rate').value) || 0;
        row.querySelector('.js-bom-total').value = (q * r) ? (q * r).toFixed(2) : '';
        updateTrimThumb(row);
        return node;
    }

    function loadDefaultBom(itemEl) {
        const wrap = itemQuery(itemEl, '.bom-wrap');
        if (! wrap) return;
        wrap.innerHTML = '';
        (defaultBomLines || []).forEach(function (row) {
            wrap.appendChild(buildBomRow(Object.assign({}, row, { is_custom: true })));
        });
        recalcItem(itemEl);
    }

    function refreshItemProductsAndSuppliers(itemEl) {
        const targets = itemEl
            ? [itemEl]
            : Array.from(itemsWrap.querySelectorAll('[data-item]'));

        targets.forEach(function (el) {
            const group = el.closest('[data-item-group]');
            const categoryId = group?.querySelector('.js-group-category')?.value
                || el.querySelector('.js-item-category')?.value
                || categorySelect.value
                || '';
            loadSelectOptions(el.querySelector('.js-product-select'), productsUrl, categoryId);
            loadSelectOptions(el.querySelector('.js-supplier-select'), suppliersUrl, categoryId);
        });
    }

    function loadSelectOptions(selectEl, url, categoryId, presetValue, presetLabel, onLoaded) {
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
                    if (row.unit_export !== undefined) {
                        opt.dataset.unitExport = row.unit_export || '';
                        opt.dataset.unitPo = row.unit_po || '';
                    }
                    if (row.bom !== undefined) {
                        opt.dataset.bom = JSON.stringify(row.bom || []);
                    }
                    if (row.incentives !== undefined) {
                        opt.dataset.incentives = JSON.stringify(row.incentives || []);
                    }
                    // Product's small reference photo (task: "I need a small
                    // image against every line") and Supplier's display code
                    // (task: auto-prefix Design no with it) ride along here.
                    if (row.image_url !== undefined) {
                        opt.dataset.image = row.image_url || '';
                    }
                    if (row.code !== undefined) {
                        opt.dataset.code = row.code || '';
                    }
                    if (selected && String(row.id) === String(selected)) { opt.selected = true; found = true; }
                    selectEl.appendChild(opt);
                });

                // Saved value no longer in the category's list (category
                // changed after the item was saved) — keep it selectable so
                // the row still shows what was actually saved.
                if (selected && ! found) {
                    const opt = document.createElement('option');
                    opt.value = selected;
                    opt.textContent = (presetLabel !== undefined ? presetLabel : selectEl.dataset.selectedLabel) || ('#' + selected);
                    opt.selected = true;
                    selectEl.appendChild(opt);
                }

                if (selectEl.dataset.upgradeSearchable === 'true' && typeof window.upgradeSearchableSelect === 'function') {
                    if (selectEl.tomselect) {
                        // Reloaded (e.g. category changed) — refresh the
                        // already-built TomSelect's option list from the DOM
                        // instead of constructing a second instance on it.
                        selectEl.tomselect.sync();
                        selectEl.tomselect.setValue(selected || '', true);
                    } else {
                        // First load — the option list (including any
                        // pre-selected option) is already in the DOM, so
                        // TomSelect picks up the right initial value at
                        // construction time with no extra call needed.
                        window.upgradeSearchableSelect(selectEl);
                    }
                }

                if (onLoaded) onLoaded();
            });
    }

    /**
     * "all drop downs should have a search option" (inquiry format sheet,
     * B1) — every searchable <select> is wrapped by TomSelect, which keeps
     * its own rendered UI in sync with the hidden native <select> only
     * through its own API. Setting .value directly still updates the
     * hidden select (so submitted data is correct) but leaves the visible
     * control showing the old choice — so every place that sets a select's
     * value from JS (buyer's cascaded agent/currency, category/format
     * copied onto a new group, FOB default cascaded to a row) goes through
     * this instead of a bare `el.value = ...`.
     */
    function setSelectValue(el, value) {
        if (! el) return;
        value = value === null || value === undefined ? '' : String(value);
        if (el.tomselect) {
            el.tomselect.setValue(value, true); // true = silent, no change-event loop
        } else {
            el.value = value;
        }
    }

    function applyCommissionFromAgent(agentId) {
        const row = agentId ? agentCommissions[agentId] : null;
        const type = row ? (row.type || '') : '';
        const value = row ? row.value : '';

        if (commissionTypeEl) commissionTypeEl.value = type;
        if (commissionTypeHidden) commissionTypeHidden.value = type;
        if (commissionValueEl) commissionValueEl.value = value === null || value === undefined ? '' : value;
    }

    buyerSelect.addEventListener('change', function () {
        const buyer = buyers[buyerSelect.value];
        applyBuyerCategoryFilter(true);

        if (buyer) {
            if (agentSelect) setSelectValue(agentSelect, buyer.agent_id || '');
            applyCommissionFromAgent(buyer.agent_id || '');
            setSelectValue(document.getElementById('currency_id'), buyer.currency_id || '');
        } else {
            if (agentSelect) setSelectValue(agentSelect, '');
            applyCommissionFromAgent('');
        }
    });

    agentSelect?.addEventListener('change', function () {
        applyCommissionFromAgent(agentSelect.value || '');
    });

    // Edit / reload: keep commission locked to Agent Master. If Agent was
    // never saved (buyer linked later), inherit from Buyer Master once.
    if (agentSelect) {
        if (! agentSelect.value && buyerSelect?.value && buyers[buyerSelect.value]?.agent_id) {
            setSelectValue(agentSelect, buyers[buyerSelect.value].agent_id);
        }
        if (agentSelect.value) {
            applyCommissionFromAgent(agentSelect.value);
        }
    }

    categorySelect.addEventListener('change', function () {
        applyCategoryFormatFilter(true);
        // Default category change does not rewrite existing lines — only
        // filters the header format list. New lines pick up the new default.
    });

    formatSelect.addEventListener('change', function () {
        applyFormatMeta();

        // Only overwrite delivery/packing when still blank — a manual edit
        // on re-selecting the format must not be clobbered.
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

        let bomCost = 0;
        itemQueryAll(itemEl, '[data-bom-row]').forEach(function (row) {
            const q = parseFloat(row.querySelector('.js-bom-qty')?.value) || 0;
            const r = parseFloat(row.querySelector('.js-bom-rate')?.value) || 0;
            const total = q * r;
            const totalEl = row.querySelector('.js-bom-total');
            if (totalEl) totalEl.value = total ? total.toFixed(2) : '';
            bomCost += total;
        });

        const price = parseFloat(itemEl.querySelector('.js-price')?.value || '0') || 0;
        const cost = parseFloat(itemEl.querySelector('.js-cost-price')?.value || '0') || 0;
        const finalCost = cost + bomCost;
        const amount = (qty * price).toFixed(2);

        const qtyDisplay = itemEl.querySelector('.js-qty-display');
        const amountDisplay = itemEl.querySelector('.js-amount-display');
        const bomCostEl = itemEl.querySelector('.js-bom-cost');
        const finalCostEl = itemEl.querySelector('.js-final-cost');
        const totalCostEl = itemEl.querySelector('.js-total-cost');
        const totalFobEl = itemEl.querySelector('.js-total-fob');
        const fobUnitEl = itemEl.querySelector('.js-fob-unit');

        if (qtyDisplay) qtyDisplay.value = qty;
        if (amountDisplay) amountDisplay.value = amount;
        if (bomCostEl) bomCostEl.value = bomCost ? bomCost.toFixed(2) : '';
        if (finalCostEl) finalCostEl.value = finalCost ? finalCost.toFixed(2) : '';
        if (totalCostEl) totalCostEl.value = (finalCost && qty) ? (finalCost * qty).toFixed(2) : '';

        const fobUnit = parseFloat(fobUnitEl?.value) || 0;
        if (totalFobEl) totalFobEl.value = (fobUnit && qty) ? (fobUnit * qty).toFixed(2) : '';

        // H-07: no signal at all previously stopped a price below cost from
        // saving silently (e.g. price 0.01 against cost 300.00 — a 299.99
        // per-unit loss the pipeline reports never showed). This is a
        // warning, not a hard block: some lines are genuinely quoted at a
        // loss on purpose (loss-leader, sample, buyer-driven price), and a
        // silent submit-block with no override path would just get in the
        // sales team's way. Compared against finalCost (cost + BOM), the
        // true all-in cost, not just the raw Cost Price field alone.
        const priceInput = itemEl.querySelector('.js-price');
        const marginWarning = itemEl.querySelector('.js-margin-warning');
        const belowCost = price > 0 && finalCost > 0 && price < finalCost;
        if (priceInput) priceInput.classList.toggle('is-invalid', belowCost);
        if (marginWarning) {
            marginWarning.style.display = belowCost ? '' : 'none';
            marginWarning.textContent = belowCost
                ? 'Below cost by ' + (finalCost - price).toFixed(2) + '/unit'
                : '';
        }

        syncBomDropdown(itemEl);
        scheduleFobQuote(itemEl, finalCost);
    }

    let fobTimers = new WeakMap();
    function scheduleFobQuote(itemEl, finalCost) {
        if (fobTimers.has(itemEl)) clearTimeout(fobTimers.get(itemEl));
        fobTimers.set(itemEl, setTimeout(function () {
            refreshFobUnit(itemEl, finalCost);
        }, 280));
    }

    function refreshFobUnit(itemEl, finalCost) {
        const fobUnitEl = itemEl.querySelector('.js-fob-unit');
        const totalFobEl = itemEl.querySelector('.js-total-fob');
        const qty = parseFloat(itemEl.querySelector('.js-qty-display')?.value) || 0;
        if (! fobUnitEl) return;

        if (! finalCost || finalCost <= 0) {
            fobUnitEl.value = '';
            if (totalFobEl) totalFobEl.value = '';
            return;
        }

        const params = new URLSearchParams({
            final_cost: String(finalCost),
            buyer_id: buyerSelect.value || '',
            supplier_id: itemEl.querySelector('.js-supplier-select')?.value || '',
            exchange_rate: document.getElementById('exchange_rate')?.value || '',
            currency_id: document.getElementById('currency_id')?.value || '',
        });

        fetch(quoteFobUrl + '?' + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (data) {
                const fob = parseFloat(data.fob_unit) || 0;
                fobUnitEl.value = fob ? fob.toFixed(4).replace(/\.?0+$/, '') : '';
                if (totalFobEl) totalFobEl.value = (fob && qty) ? (fob * qty).toFixed(2) : '';
            })
            .catch(function () { /* keep last value */ });
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
            itemEl.dataset.productId || '', itemEl.dataset.productLabel || '',
            function () { updateProductThumb(itemEl); }
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

        // Sync FOB type select from hidden — falls back to this category
        // block's own default FOB type for a brand-new row so staff don't
        // have to repeat the same selection on every single line ("isn't
        // this repeating").
        const fobHidden = itemEl.querySelector('[data-field="fob_value_id"]');
        const fobSelect = itemQuery(itemEl, '.js-fob-value-select');
        if (fobHidden && fobSelect) {
            let fobValue = fobHidden.value || '';
            if (! fobValue) {
                const groupFobSelect = group?.querySelector('.js-group-fob');
                if (groupFobSelect && groupFobSelect.value) fobValue = groupFobSelect.value;
            }
            setSelectValue(fobSelect, fobValue);
            fobHidden.value = fobValue;
        }
        if (fobSelect && ! fobSelect.tomselect && typeof window.upgradeSearchableSelect === 'function') {
            window.upgradeSearchableSelect(fobSelect);
        }

        // Default BOM cost from the inquiry header — only for a brand-new
        // row with nothing chosen yet; never overrides a saved/custom value.
        const bomCostHidden = itemEl.querySelector('.js-bom-cost');
        if (bomCostHidden && ! bomCostHidden.value && defaultBomTemplateEl && defaultBomTemplateEl.value) {
            applyBomTemplate(itemEl, defaultBomTemplateEl.value);
        }

        itemQueryAll(itemEl, '[data-bom-row]').forEach(updateTrimThumb);

        recalcItem(itemEl);
        syncBreakdownToQuick(itemEl);
    }

    function initGroup(groupEl) {
        if (! groupEl.querySelector('.js-group-category').value && categorySelect.value) {
            setSelectValue(groupEl.querySelector('.js-group-category'), categorySelect.value);
        }
        if (! groupEl.querySelector('.js-group-format').value && formatSelect.value) {
            setSelectValue(groupEl.querySelector('.js-group-format'), formatSelect.value);
        }
        filterGroupFormatOptions(groupEl, false);
        syncGroupMetaToRows(groupEl);

        // "all drop downs should have a search option" — group-level selects
        // are rendered fresh per block (server-rendered at page load, or
        // cloned from <template> when "Add category block" is clicked), so
        // they need the same upgrade the page-load sweep gives static
        // selects, done explicitly here instead since it must run for both.
        ['.js-group-category', '.js-group-format', '.js-group-fob'].forEach(function (sel) {
            const el = groupEl.querySelector(sel);
            if (el && ! el.tomselect && typeof window.upgradeSearchableSelect === 'function') {
                window.upgradeSearchableSelect(el);
            }
        });

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
        if (e.target.closest('.js-toggle-bom-editor')) {
            const itemEl = itemFromEventTarget(e.target);
            const editor = itemQuery(itemEl, '.js-bom-editor');
            if (editor) editor.classList.toggle('d-none');
            return;
        }
        if (e.target.closest('.js-load-default-bom')) {
            loadDefaultBom(itemFromEventTarget(e.target));
            return;
        }
        if (e.target.closest('.js-add-bom')) {
            const itemEl = itemFromEventTarget(e.target);
            const wrap = itemQuery(itemEl, '.bom-wrap');
            if (wrap) wrap.appendChild(buildBomRow({ qty: 1, rate: 0, is_custom: true }));
            const editor = itemQuery(itemEl, '.js-bom-editor');
            if (editor) editor.classList.remove('d-none');
            recalcItem(itemEl);
            return;
        }
        if (e.target.closest('.js-remove-bom')) {
            const itemEl = itemFromEventTarget(e.target);
            const row = e.target.closest('[data-bom-row]');
            if (row) row.remove();
            if (itemEl) recalcItem(itemEl);
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
        if (e.target.classList.contains('js-size-qty') || e.target.classList.contains('js-price') || e.target.classList.contains('js-cost-price')
            || e.target.classList.contains('js-bom-qty') || e.target.classList.contains('js-bom-rate')) {
            recalcItem(itemEl);
            if (e.target.classList.contains('js-size-qty')) syncBreakdownToQuick(itemEl);
            renderIncentiveEstimate(itemEl);
        }
        if (e.target.classList.contains('js-colour-name')) {
            syncBreakdownToQuick(itemEl);
        }
        if (e.target.classList.contains('js-bom-name')) {
            updateTrimThumb(e.target.closest('[data-bom-row]'));
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
        if (e.target.classList.contains('js-group-fob')) {
            const group = e.target.closest('[data-item-group]');
            group.querySelectorAll('[data-item]').forEach(function (itemEl) {
                const hidden = itemEl.querySelector('[data-field="fob_value_id"]');
                const select = itemQuery(itemEl, '.js-fob-value-select');
                if (hidden && select && ! hidden.value) {
                    setSelectValue(select, e.target.value);
                    hidden.value = e.target.value;
                }
            });
            return;
        }
        if (e.target.classList.contains('js-product-select')) {
            const itemEl = itemFromEventTarget(e.target);
            applyProductUnit(itemEl);
            updateProductThumb(itemEl);
            renderIncentiveEstimate(itemEl);
            recalcItem(itemEl);
            return;
        }
        if (e.target.classList.contains('js-bom-template')) {
            const itemEl = itemFromEventTarget(e.target);
            const key = e.target.value;
            if (key === 'custom') return;
            applyBomTemplate(itemEl, key);
            return;
        }
        if (e.target.classList.contains('js-supplier-select')) {
            const itemEl = itemFromEventTarget(e.target);
            applySupplierCodeToDesign(itemEl, e.target);
            recalcItem(itemEl);
            return;
        }
        if (e.target.classList.contains('js-fob-value-select')) {
            const itemEl = itemFromEventTarget(e.target);
            const hidden = itemEl.querySelector('[data-field="fob_value_id"]');
            if (hidden) hidden.value = e.target.value;
            return;
        }
        if (e.target.classList.contains('js-unit-select')) {
            e.target.dataset.selected = e.target.value;
            const itemEl = itemFromEventTarget(e.target);
            applyColumnsToItem(itemEl, itemFormatMeta(itemEl));
        }
    });

    // "let's put a default BOM cost option here too" — fills any row that
    // doesn't already have a BOM cost chosen; never overwrites one that does.
    if (defaultBomTemplateEl) {
        defaultBomTemplateEl.addEventListener('change', function () {
            if (! defaultBomTemplateEl.value) return;
            itemsWrap.querySelectorAll('[data-item]').forEach(function (itemEl) {
                const bomCostHidden = itemEl.querySelector('.js-bom-cost');
                if (bomCostHidden && ! bomCostHidden.value) {
                    applyBomTemplate(itemEl, defaultBomTemplateEl.value);
                }
            });
        });
    }

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
            appendHidden('items[' + i + '][bom_cost]', itemEl.querySelector('.js-bom-cost')?.value || '');
            appendHidden('items[' + i + '][fob_unit]', itemEl.querySelector('.js-fob-unit')?.value || '');
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
                appendHidden('items[' + i + '][bom][' + b + '][size]', bomEl.querySelector('.js-bom-size')?.value || '');
                appendHidden('items[' + i + '][bom][' + b + '][qty]', bomEl.querySelector('.js-bom-qty').value || 0);
                appendHidden('items[' + i + '][bom][' + b + '][rate]', bomEl.querySelector('.js-bom-rate')?.value || '');
                appendHidden('items[' + i + '][bom][' + b + '][unit]', bomEl.querySelector('.js-bom-unit')?.value || '');
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
    <tr class="inquiry-bom-row" data-bom-row data-is-custom="1">
        <td>
            <div class="d-flex align-items-center gap-1">
                <img class="js-trim-thumb rounded border bg-body-tertiary d-none flex-shrink-0" alt=""
                     style="width:22px;height:22px;object-fit:cover">
                <input type="text" class="form-control form-control-sm js-bom-name" maxlength="200" placeholder="Trim / accessory">
            </div>
        </td>
        <td><input type="text" class="form-control form-control-sm js-bom-size" maxlength="60" placeholder="Size"></td>
        <td><input type="text" class="form-control form-control-sm js-bom-remarks" maxlength="500" placeholder="Description"></td>
        <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm js-bom-qty" value="1"></td>
        <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm js-bom-rate" value="0"></td>
        <td><input type="text" class="form-control form-control-sm js-bom-total bg-body-tertiary" readonly tabindex="-1"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger js-remove-bom"><i class="bi bi-x"></i></button></td>
    </tr>
</template>

<template id="tpl-followup">
    <div class="inquiry-followup d-flex gap-2 mb-2" data-followup>
        <input type="date" class="form-control form-control-sm js-followup-date" style="max-width:11rem">
        <input type="text" class="form-control form-control-sm js-followup-comment" placeholder="Comment…">
        <button type="button" class="btn btn-sm btn-outline-danger js-remove-followup"><i class="bi bi-x"></i></button>
    </div>
</template>