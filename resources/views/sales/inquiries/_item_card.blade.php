{{--
  Task 11 — Inquiry item card layout (approved design proposal).
  Same field hooks as before (js-field, colours, sizes) so save / Task 10 unit
  auto-fill stay untouched. Markup only.
--}}
@php
    $isTemplate = $isTemplate ?? false;

    if ($isTemplate) {
        $iDesign = $iDesc = $iProduct = $iSupplier = $iUnit = $iFob = $iPrice = $iCostPrice = $iRemarks = '';
        $iStatus = 'draft';
        $iColours = [];
        $iProductLabel = $iSupplierLabel = null;
        $iCustom = [];
        $iAmount = '0.00';
        $iBom = [];
        $openCosting = false;
    } else {
        $isArr = is_array($item);
        $iDesign = $isArr ? ($item['design_no'] ?? '') : $item->design_no;
        $iDesc = $isArr ? ($item['description'] ?? '') : $item->description;
        $iProduct = $isArr ? ($item['product_id'] ?? '') : $item->product_id;
        $iSupplier = $isArr ? ($item['supplier_id'] ?? '') : $item->supplier_id;
        $iUnit = $isArr ? ($item['unit'] ?? '') : $item->unit;
        $iFob = $isArr ? ($item['fob_value_id'] ?? '') : $item->fob_value_id;
        $iPrice = $isArr ? ($item['price'] ?? '') : $item->price;
        $iCostPrice = $isArr ? ($item['cost_price'] ?? '') : $item->cost_price;
        $iStatus = $isArr ? ($item['status'] ?? 'draft') : $item->status;
        $iRemarks = $isArr ? ($item['remarks'] ?? '') : $item->remarks;
        $iColours = $isArr ? ($item['colours'] ?? []) : $item->colours;
        $iProductLabel = $isArr ? null : $item->product?->name;
        $iSupplierLabel = $isArr ? null : $item->supplier?->label;
        $iCustom = $isArr ? ($item['custom'] ?? []) : ($item->custom_values ?? []);
        $iAmount = $isArr ? '0.00' : number_format((float) $item->amount, 2);
        $iBom = $isArr ? ($item['bom'] ?? []) : ($item->bomLines ?? []);

        // Proposal rule: open costing on edit when the item already has
        // colour/size or BOM data; keep collapsed for empty / new rows.
        $openCosting = collect($iBom)->contains(fn ($b) => filled(is_array($b) ? ($b['component_name'] ?? '') : ($b->component_name ?? '')))
            || collect($iColours)->contains(function ($colour) {
            $sizes = is_array($colour) ? ($colour['sizes'] ?? []) : ($colour->sizes ?? []);
            foreach ($sizes as $size) {
                $qty = is_array($size) ? (int) ($size['qty'] ?? 0) : (int) ($size->qty ?? 0);
                if ($qty > 0) {
                    return true;
                }
            }
            $name = is_array($colour) ? ($colour['colour'] ?? '') : ($colour->colour ?? '');

            return filled($name);
        });
    }
@endphp

<div class="inquiry-item card border shadow-sm mb-3" data-item
     @unless($isTemplate)
         data-product-id="{{ $iProduct }}" data-product-label="{{ $iProductLabel }}"
         data-supplier-id="{{ $iSupplier }}" data-supplier-label="{{ $iSupplierLabel }}"
         data-unit="{{ $iUnit }}" data-custom-values="{{ json_encode($iCustom) }}"
     @endunless>
    <div class="card-header bg-body-tertiary py-2 px-3 d-flex flex-wrap align-items-center gap-2">
        <span class="fw-semibold item-index-label text-primary">Item</span>
        <span class="badge rounded-pill text-bg-light border js-item-qty-badge">Qty 0</span>
        <span class="badge rounded-pill text-bg-primary js-item-amount-badge">Amt {{ $iAmount }}</span>
        <div class="ms-auto d-flex align-items-center gap-2">
            <select class="form-select form-select-sm js-field" data-field="status" style="width:auto;min-width:7.5rem">
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" @selected($iStatus === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-sm btn-outline-danger js-remove-item" title="Remove item">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>

    <div class="card-body pt-3">
        <div class="row g-2">
            <div class="col-md-3" data-column="design_no">
                <label class="form-label small js-column-label mb-1">Design No. / Name</label>
                <input type="text" class="form-control form-control-sm js-field" data-field="design_no" maxlength="150" value="{{ $iDesign }}">
            </div>
            <div class="col-md-3" data-column="product">
                <label class="form-label small js-column-label mb-1">Product</label>
                <select class="form-select form-select-sm js-field js-product-select" data-field="product_id"><option value="">— Select —</option></select>
            </div>
            <div class="col-md-3" data-column="supplier">
                <label class="form-label small js-column-label mb-1">Supplier</label>
                <select class="form-select form-select-sm js-field js-supplier-select" data-field="supplier_id"><option value="">— Select —</option></select>
            </div>
            <div class="col-md-3" data-column="unit">
                <label class="form-label small js-column-label mb-1">Unit</label>
                <select class="form-select form-select-sm js-field js-unit-select" data-field="unit"><option value="">—</option></select>
            </div>
        </div>

        <div class="row g-2 mt-2">
            <div class="col-md-8">
                <label class="form-label small mb-1">Description</label>
                <input type="text" class="form-control form-control-sm js-field" data-field="description" maxlength="500" value="{{ $iDesc }}">
            </div>
            <div class="col-md-4" data-column="price">
                <label class="form-label small js-column-label mb-1">Price</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm js-field js-price" data-field="price" value="{{ $iPrice }}">
            </div>
        </div>

        <div class="row g-2 mt-2 custom-fields-wrap"></div>

        {{-- Hidden mirrors so existing recalc / compile JS keep working --}}
        <input type="hidden" class="js-qty-display" value="0">
        <input type="hidden" class="js-amount-display" value="{{ $iAmount }}">

        <div class="inquiry-costing border rounded mt-3 overflow-hidden">
            <button type="button"
                    class="js-toggle-costing btn btn-light border-0 rounded-0 w-100 text-start d-flex align-items-center gap-2 px-3 py-2 {{ $openCosting ? 'is-open' : '' }}"
                    aria-expanded="{{ $openCosting ? 'true' : 'false' }}">
                <i class="bi bi-chevron-right js-costing-chevron text-body-secondary"></i>
                <span class="fw-semibold small">Costing</span>
                <span class="text-body-secondary small d-none d-md-inline">— FOB, BOM, cost price, colour &amp; size qty</span>
            </button>

            <div class="costing-panel border-top p-3 bg-body {{ $openCosting ? '' : 'd-none' }}">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">FOB Value</label>
                        <select class="form-select form-select-sm js-field" data-field="fob_value_id">
                            <option value="">— Select —</option>
                            @foreach($fobValues as $id => $name)
                                <option value="{{ $id }}" @selected((string) $iFob === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Cost Price / Unit (₹)</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm js-field" data-field="cost_price" value="{{ $iCostPrice }}">
                        <div class="form-text">Internal — feeds PO, not shown to buyer</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Item Remarks</label>
                        <input type="text" class="form-control form-control-sm js-field" data-field="remarks" maxlength="500" value="{{ $iRemarks }}">
                    </div>
                </div>

                {{-- Task 12: BOM below FOB, same costing panel, qty per piece --}}
                <div class="inquiry-bom border rounded p-2 mb-3 bg-body-tertiary">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <span class="fw-semibold small">BOM</span>
                            <span class="text-body-secondary small">— components per finished piece</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary js-add-bom">
                            <i class="bi bi-plus-lg me-1"></i>Add
                        </button>
                    </div>
                    <div class="bom-wrap">
                        @foreach($iBom as $bom)
                            @php
                                $bIsArr = is_array($bom);
                                $bName = $bIsArr ? ($bom['component_name'] ?? '') : $bom->component_name;
                                $bQty = $bIsArr ? ($bom['qty'] ?? 1) : $bom->qty;
                                $bUnit = $bIsArr ? ($bom['unit'] ?? '') : $bom->unit;
                                $bRemarks = $bIsArr ? ($bom['remarks'] ?? '') : $bom->remarks;
                                $bCustom = $bIsArr ? (bool) ($bom['is_custom'] ?? true) : (bool) $bom->is_custom;
                            @endphp
                            <div class="row g-1 align-items-end mb-1 inquiry-bom-row" data-bom-row data-is-custom="{{ $bCustom ? '1' : '0' }}">
                                <div class="col-md-4">
                                    <input type="text" class="form-control form-control-sm js-bom-name" placeholder="Component" maxlength="200" value="{{ $bName }}">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" step="0.0001" min="0" class="form-control form-control-sm js-bom-qty" placeholder="Qty/pc" value="{{ $bQty }}">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" class="form-control form-control-sm js-bom-unit" placeholder="Unit" maxlength="20" value="{{ $bUnit }}">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control form-control-sm js-bom-remarks" placeholder="Remarks" maxlength="500" value="{{ $bRemarks }}">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-sm btn-outline-danger w-100 js-remove-bom"><i class="bi bi-x"></i></button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="inquiry-incentives border rounded p-2 mb-3 bg-body-tertiary d-none" data-incentives-box>
                    <div class="fw-semibold small mb-1">Export incentives (estimate)</div>
                    <div class="form-text mb-1">Claim = min(Rate% × FOB amount, Cap × PCS). FOB amount = Price × Qty.</div>
                    <div class="small js-incentive-estimate text-body-secondary">Select a product with schemes to estimate.</div>
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
                                <span class="badge text-bg-light border js-colour-subtotal ms-auto">Qty 0</span>
                                <button type="button" class="btn btn-sm btn-outline-danger js-remove-colour"><i class="bi bi-trash"></i></button>
                            </div>
                            <div class="sizes-wrap d-flex flex-wrap gap-2 mb-2">
                                @foreach($cSizes as $size)
                                    @php
                                        $sIsArr = is_array($size);
                                        $sLabel = $sIsArr ? ($size['size'] ?? '') : $size->size;
                                        $sQty = $sIsArr ? ($size['qty'] ?? 0) : $size->qty;
                                    @endphp
                                    <div class="inquiry-size d-flex align-items-center gap-1 border rounded px-1 py-1 bg-body" data-size style="max-width:12rem">
                                        <input type="text" class="form-control form-control-sm js-size-label border-0" placeholder="Size" maxlength="20" style="width:4.5rem" value="{{ $sLabel }}">
                                        <input type="number" min="0" class="form-control form-control-sm js-size-qty" placeholder="Qty" style="width:4.5rem" value="{{ $sQty }}">
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
                <button type="button" class="btn btn-sm btn-outline-primary js-add-colour mt-1">
                    <i class="bi bi-plus-lg me-1"></i>Add colour
                </button>
            </div>
        </div>
    </div>
</div>
