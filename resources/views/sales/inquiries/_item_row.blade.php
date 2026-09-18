{{-- One Excel-style inquiry line + costing (BOM Format sheet). --}}
@php
    $isTemplate = $isTemplate ?? false;

    if ($isTemplate) {
        $iDesign = $iDesc = $iProduct = $iSupplier = $iUnit = $iFob = $iPrice = $iCostPrice = $iRemarks = '';
        $iBomCost = $iFobUnit = '';
        $iStatus = 'draft';
        $iColours = [];
        $iProductLabel = $iSupplierLabel = null;
        $iCustom = [];
        $iBom = [];
        $iCategory = '';
        $iFormat = '';
        $openCosting = false;
        $quickColour = '';
        $quickQty = 0;
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
        $iBomCost = $isArr ? ($item['bom_cost'] ?? '') : $item->bom_cost;
        $iFobUnit = $isArr ? ($item['fob_unit'] ?? '') : $item->fob_unit;
        $iStatus = $isArr ? ($item['status'] ?? 'draft') : $item->status;
        $iRemarks = $isArr ? ($item['remarks'] ?? '') : $item->remarks;
        $iColours = $isArr ? ($item['colours'] ?? []) : $item->colours;
        $iProductLabel = $isArr ? null : $item->product?->name;
        $iSupplierLabel = $isArr ? null : $item->supplier?->label;
        $iCustom = $isArr ? ($item['custom'] ?? []) : ($item->custom_values ?? []);
        $iBom = $isArr ? ($item['bom'] ?? []) : ($item->bomLines ?? []);
        $iCategory = $isArr ? ($item['category_id'] ?? '') : $item->category_id;
        $iFormat = $isArr ? ($item['document_format_id'] ?? '') : $item->document_format_id;

        $quickColour = '';
        $quickQty = 0;
        foreach ($iColours as $colour) {
            $quickColour = is_array($colour) ? ($colour['colour'] ?? '') : ($colour->colour ?? '');
            $sizes = is_array($colour) ? ($colour['sizes'] ?? []) : ($colour->sizes ?? []);
            foreach ($sizes as $size) {
                $quickQty += (int) (is_array($size) ? ($size['qty'] ?? 0) : ($size->qty ?? 0));
            }
            break;
        }

        $openCosting = false;
    }

    $costNum = is_numeric($iCostPrice) ? (float) $iCostPrice : 0;
    $bomNum = is_numeric($iBomCost) ? (float) $iBomCost : 0;
    $finalCost = ($costNum || $bomNum) ? number_format($costNum + $bomNum, 2, '.', '') : '';
    $fobNum = is_numeric($iFobUnit) ? (float) $iFobUnit : 0;
    $totalFob = ($fobNum && $quickQty) ? number_format($fobNum * $quickQty, 2, '.', '') : '';
    $totalCost = (($costNum + $bomNum) && $quickQty) ? number_format(($costNum + $bomNum) * $quickQty, 2, '.', '') : '';
    $bomTemplates = $bomTemplates ?? collect();
    $matchedBomKey = '';
    foreach ($bomTemplates as $template) {
        if (abs(((float) ($template['total'] ?? 0)) - $bomNum) < 0.005 && $bomNum > 0) {
            $matchedBomKey = $template['key'];
            break;
        }
    }
@endphp

<tr class="inquiry-item align-middle" data-item
    @unless($isTemplate)
        data-product-id="{{ $iProduct }}" data-product-label="{{ $iProductLabel }}"
        data-supplier-id="{{ $iSupplier }}" data-supplier-label="{{ $iSupplierLabel }}"
        data-unit="{{ $iUnit }}" data-custom-values="{{ json_encode($iCustom) }}"
        data-category-id="{{ $iCategory }}" data-format-id="{{ $iFormat }}"
    @endunless>
    <td class="text-body-secondary small item-index-label">#</td>
    <td data-column="supplier" style="min-width:8rem">
        <select class="form-select form-select-sm js-field js-supplier-select" data-field="supplier_id" data-upgrade-searchable="true" data-placeholder="Search suppliers…"><option value="">—</option></select>
    </td>
    <td data-column="design_no" style="min-width:7rem">
        <input type="text" class="form-control form-control-sm js-field" data-field="design_no" maxlength="150" value="{{ $iDesign }}">
    </td>
    <td data-column="product" style="min-width:9rem">
        <div class="d-flex align-items-center gap-1">
            <img class="js-product-thumb rounded border bg-body-tertiary d-none flex-shrink-0" alt=""
                 style="width:28px;height:28px;object-fit:cover">
            <select class="form-select form-select-sm js-field js-product-select" data-field="product_id" data-upgrade-searchable="true" data-placeholder="Search by code or name…"><option value="">—</option></select>
        </div>
    </td>
    <td style="min-width:6rem">
        <input type="text" class="form-control form-control-sm js-quick-colour" maxlength="60" value="{{ $quickColour }}">
    </td>
    <td>
        <input type="number" min="0" class="form-control form-control-sm js-quick-qty" value="{{ $quickQty ?: '' }}">
    </td>
    <td data-column="unit">
        <select class="form-select form-select-sm js-field js-unit-select" data-field="unit"><option value="">—</option></select>
    </td>
    <td data-column="price">
        <input type="number" step="0.01" min="0" class="form-control form-control-sm js-field js-price" data-field="price" value="{{ $iPrice }}" title="Buyer-facing price — prints on the Inquiry document.">
        {{-- H-07: silently accepting price < cost let a 0.01 price against a
             300.00 cost save without any signal — flipped on/off from
             recalcItem() whenever price, cost or BOM cost change. --}}
        <div class="text-danger small js-margin-warning" style="display:none">Below cost</div>
    </td>
    <td>
        <input type="number" step="0.01" min="0" class="form-control form-control-sm js-field js-cost-price" data-field="cost_price" value="{{ $iCostPrice }}" title="Your trading cost / unit — internal only, not printed on the buyer document.">
    </td>
    <td style="min-width:11rem">
        <select class="form-select form-select-sm js-bom-template" title="Select a BOM cost pack">
            <option value="">— Select BOM —</option>
            @foreach($bomTemplates as $template)
                <option value="{{ $template['key'] }}" @selected($matchedBomKey === $template['key'])>
                    {{ $template['name'] }} (₹{{ number_format((float) $template['total'], 2) }})
                </option>
            @endforeach
            <option value="custom" class="js-bom-custom-opt" @selected($bomNum > 0 && $matchedBomKey === '') @if($bomNum <= 0 || $matchedBomKey !== '') hidden @endif>
                Custom (₹{{ $bomNum ? number_format($bomNum, 2) : '0.00' }})
            </option>
        </select>
        <input type="hidden" class="js-field js-bom-cost" data-field="bom_cost" value="{{ $iBomCost }}">
    </td>
    <td>
        <input type="text" class="form-control form-control-sm js-final-cost bg-body-tertiary" readonly tabindex="-1" value="{{ $finalCost }}">
    </td>
    <td>
        <input type="text" class="form-control form-control-sm js-fob-unit bg-body-tertiary" readonly tabindex="-1" value="{{ $iFobUnit }}" data-field="fob_unit" title="From cost + markup (margin hidden)">
    </td>
    <td>
        <input type="text" class="form-control form-control-sm js-total-fob bg-body-tertiary" readonly tabindex="-1" value="{{ $totalFob }}">
    </td>
    <td>
        <input type="text" class="form-control form-control-sm js-total-cost bg-body-tertiary" readonly tabindex="-1" value="{{ $totalCost }}">
        <input type="hidden" class="js-qty-display" value="{{ $quickQty }}">
        <input type="hidden" class="js-amount-display" value="0">
    </td>
    <td>
        <select class="form-select form-select-sm js-field" data-field="status">
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected($iStatus === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </td>
    <td class="text-nowrap">
        <button type="button" class="btn btn-sm btn-outline-secondary js-toggle-costing {{ $openCosting ? 'is-open' : '' }}" title="FOB type / item notes" aria-expanded="{{ $openCosting ? 'true' : 'false' }}">
            <i class="bi bi-list-check"></i>
        </button>
        <button type="button" class="btn btn-sm btn-outline-danger js-remove-item" title="Remove row"><i class="bi bi-trash"></i></button>
        <input type="hidden" class="js-field js-item-category" data-field="category_id" value="{{ $iCategory }}">
        <input type="hidden" class="js-field js-item-format" data-field="document_format_id" value="{{ $iFormat }}">
        <input type="hidden" class="js-field" data-field="description" value="{{ $iDesc }}">
        <input type="hidden" class="js-field" data-field="remarks" value="{{ $iRemarks }}">
        <input type="hidden" class="js-field" data-field="fob_value_id" value="{{ $iFob }}">
    </td>
</tr>
<tr class="inquiry-item-costing-row {{ $openCosting ? '' : 'd-none' }}" data-item-costing>
    <td colspan="16" class="bg-body-tertiary p-0">
        <div class="costing-panel p-3 border-top">
            <div class="row g-2 mb-3">
                <div class="col-md-7">
                    <label class="form-label small mb-1">Description</label>
                    <input type="text" class="form-control form-control-sm js-desc-mirror" maxlength="500" value="{{ $iDesc }}">
                </div>
                <div class="col-md-5">
                    <label class="form-label small mb-1">Item remarks</label>
                    <input type="text" class="form-control form-control-sm js-remarks-mirror" maxlength="500" value="{{ $iRemarks }}">
                </div>
            </div>

            <div class="inquiry-bom border rounded p-2 mb-0 bg-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <img class="js-product-thumb rounded border bg-body-tertiary d-none flex-shrink-0" alt=""
                             style="width:32px;height:32px;object-fit:cover">
                        <div>
                            <span class="fw-semibold small">BOM trims</span>
                            <span class="text-body-secondary small">— hidden on the main row. Open only if you need to change a pack.</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary js-toggle-bom-editor">
                        View / edit trims
                    </button>
                </div>
                <div class="js-bom-editor d-none mt-2">
                <div class="d-flex flex-wrap align-items-center justify-content-end gap-1 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-primary js-add-bom"><i class="bi bi-plus-lg me-1"></i>Add line</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr class="small text-nowrap">
                                <th>Trim / Accessory</th>
                                <th style="width:6rem">Size</th>
                                <th>Description</th>
                                <th style="width:6rem">Consumption</th>
                                <th style="width:6rem">Rate</th>
                                <th style="width:6rem">Total</th>
                                <th style="width:2.5rem"></th>
                            </tr>
                        </thead>
                        <tbody class="bom-wrap">
                            @foreach($iBom as $bom)
                                @php
                                    $bIsArr = is_array($bom);
                                    $bName = $bIsArr ? ($bom['component_name'] ?? '') : $bom->component_name;
                                    $bSize = $bIsArr ? ($bom['size'] ?? '') : ($bom->size ?? '');
                                    $bQty = $bIsArr ? ($bom['qty'] ?? 1) : $bom->qty;
                                    $bRate = $bIsArr ? ($bom['rate'] ?? '') : ($bom->rate ?? '');
                                    $bRemarks = $bIsArr ? ($bom['remarks'] ?? '') : $bom->remarks;
                                    $bCustom = $bIsArr ? (bool) ($bom['is_custom'] ?? true) : (bool) $bom->is_custom;
                                    $bTotal = is_numeric($bQty) && is_numeric($bRate) ? number_format((float)$bQty * (float)$bRate, 2, '.', '') : '';
                                @endphp
                                <tr class="inquiry-bom-row" data-bom-row data-is-custom="{{ $bCustom ? '1' : '0' }}">
                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            <img class="js-trim-thumb rounded border bg-body-tertiary d-none flex-shrink-0" alt=""
                                                 style="width:22px;height:22px;object-fit:cover">
                                            <input type="text" class="form-control form-control-sm js-bom-name" maxlength="200" value="{{ $bName }}">
                                        </div>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm js-bom-size" maxlength="60" value="{{ $bSize }}"></td>
                                    <td><input type="text" class="form-control form-control-sm js-bom-remarks" maxlength="500" value="{{ $bRemarks }}"></td>
                                    <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm js-bom-qty" value="{{ $bQty }}"></td>
                                    <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm js-bom-rate" value="{{ $bRate }}"></td>
                                    <td><input type="text" class="form-control form-control-sm js-bom-total bg-body-tertiary" readonly tabindex="-1" value="{{ $bTotal }}"></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger js-remove-bom"><i class="bi bi-x"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                </div>
            </div>

            <div class="inquiry-incentives border rounded p-2 mb-3 bg-body d-none mt-3" data-incentives-box>
                <div class="fw-semibold small mb-1">Export incentives (estimate)</div>
                <div class="small js-incentive-estimate text-body-secondary">Select a product with schemes to estimate.</div>
            </div>

            <div class="colours-wrap d-none" aria-hidden="true">
                @foreach($iColours as $colour)
                    @php
                        $cIsArr = is_array($colour);
                        $cName = $cIsArr ? ($colour['colour'] ?? '') : $colour->colour;
                        $cSizes = $cIsArr ? ($colour['sizes'] ?? []) : $colour->sizes;
                    @endphp
                    <div class="inquiry-colour border rounded p-2 mb-2 bg-body" data-colour>
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
                                <div class="inquiry-size d-flex align-items-center gap-1 border rounded px-1 py-1 bg-body-tertiary" data-size style="max-width:12rem">
                                    <input type="text" class="form-control form-control-sm js-size-label border-0" maxlength="20" style="width:4.5rem" value="{{ $sLabel }}">
                                    <input type="number" min="0" class="form-control form-control-sm js-size-qty" style="width:4.5rem" value="{{ $sQty }}">
                                    <button type="button" class="btn btn-sm btn-outline-danger js-remove-size"><i class="bi bi-x"></i></button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary js-add-size"><i class="bi bi-plus-lg me-1"></i>Add size</button>
                    </div>
                @endforeach
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary js-add-colour mt-1 d-none"><i class="bi bi-plus-lg me-1"></i>Add colour</button>
            <div class="row g-2 mt-2 custom-fields-wrap"></div>
        </div>
    </td>
</tr>
