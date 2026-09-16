{{--
  Category + Order Format header, then Excel-style item table
  (BOM and Inquiry Format final.xlsx + 09-Sep meeting).
--}}
@php
    $isGroupTemplate = $isGroupTemplate ?? false;
    $groupItems = $groupItems ?? [];
    $gCategory = $gCategory ?? '';
    $gFormat = $gFormat ?? '';
@endphp

<div class="inquiry-item-group card border shadow-sm mb-4" data-item-group>
    <div class="card-header bg-body-tertiary py-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
            <div>
                <span class="fw-semibold js-group-label">Category block</span>
                <div class="form-text mb-0">One category + order format for every row in this table.</div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger js-remove-group" title="Remove this category block">
                <i class="bi bi-trash me-1"></i>Remove block
            </button>
        </div>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label small mb-1">Category <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm js-group-category">
                    <option value="">— Select —</option>
                    @foreach($categories as $id => $label)
                        <option value="{{ $id }}" @selected((string) $gCategory === (string) $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Order Format <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm js-group-format">
                    <option value="">— Select —</option>
                    @foreach($formats as $format)
                        <option value="{{ $format->id }}" @selected((string) $gFormat === (string) $format->id)>{{ $format->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Default FOB Value type</label>
                <select class="form-select form-select-sm js-group-fob">
                    <option value="">— Select —</option>
                    @foreach($fobValues as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <div class="form-text mb-0">Applied to every new row in this block — no need to repeat it per line.</div>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 inquiry-items-table" style="min-width:92rem">
                <thead class="table-light">
                    <tr class="small text-nowrap">
                        <th style="width:2.2rem">#</th>
                        <th style="width:9rem">Supplier</th>
                        <th style="width:8rem">Design no / name</th>
                        <th style="width:12rem">Product</th>
                        <th style="width:6rem">Colour</th>
                        <th style="width:4.5rem">Qty</th>
                        <th style="width:5rem">Unit</th>
                        <th style="width:6.5rem" title="Buyer-facing price — this is what prints on the Inquiry document sent to the buyer.">Price (Buyer) <i class="bi bi-info-circle text-body-secondary"></i></th>
                        <th style="width:7rem" title="Your trading cost per unit — internal only, never printed on the buyer document. Cost + BOM cost = Final cost.">Cost (Internal) <i class="bi bi-info-circle text-body-secondary"></i></th>
                        <th style="width:11rem">BOM cost</th>
                        <th style="width:7rem" title="Cost (Internal) + BOM cost">Final cost</th>
                        <th style="width:7rem">FOB / unit</th>
                        <th style="width:8rem">Total FOB</th>
                        <th style="width:8.5rem">Total cost</th>
                        <th style="width:7.5rem">Status</th>
                        <th style="width:5rem"></th>
                    </tr>
                </thead>
                <tbody class="js-group-rows">
                    @unless($isGroupTemplate)
                        @foreach($groupItems as $item)
                            @include('sales.inquiries._item_row', ['item' => $item])
                        @endforeach
                    @endunless
                </tbody>
            </table>
        </div>
        <div class="p-2 border-top d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-primary js-add-group-row">
                <i class="bi bi-plus-lg me-1"></i>Add item row
            </button>
            <span class="form-text align-self-center mb-0">Pick a BOM cost from the dropdown — Final cost / FOB fill automatically. Colour and Qty stay on the row. <i class="bi bi-arrow-left-right"></i> Scroll the table sideways to see all costing columns.</span>
        </div>
    </div>
</div>
