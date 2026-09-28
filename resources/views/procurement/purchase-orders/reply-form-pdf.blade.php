<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PO Reply Form — {{ $purchaseOrder->po_num }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        .muted { color: #555; font-size: 10px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 5px; vertical-align: top; }
        th { background: #eee; text-align: left; font-size: 10px; }
        .meta td { border: none; padding: 2px 0; }
        .blank { border-bottom: 1px solid #333; min-width: 120px; display: inline-block; }
        .section { margin-top: 12px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Purchase Order Reply Form</h1>
    <div class="muted">
        Supplier: fill confirmed dispatch / packing details and quantities, then return this PDF (scan or print).
        Office will OCR-upload it — avoid retyping.
    </div>

    <table class="meta">
        <tr><td width="28%"><strong>PO No.</strong></td><td>{{ $purchaseOrder->po_num }}</td></tr>
        <tr><td><strong>PO Date</strong></td><td>{{ $purchaseOrder->po_date?->format('d-m-Y') }}</td></tr>
        <tr><td><strong>Supplier</strong></td><td>{{ $purchaseOrder->supplier?->company_name }} ({{ $purchaseOrder->supplier?->display_code }})</td></tr>
        <tr><td><strong>Buyer (OC)</strong></td><td>{{ $purchaseOrder->orderConfirmation?->buyer?->company_name ?? '—' }}</td></tr>
        <tr><td><strong>OC No.</strong></td><td>{{ $purchaseOrder->orderConfirmation?->oc_num ?? '—' }}</td></tr>
    </table>

    <p class="section">Supplier to confirm</p>
    <table class="meta">
        <tr><td width="28%"><strong>Confirmed dispatch date</strong></td><td>____ / ____ / ________</td></tr>
        <tr><td><strong>Delivery details</strong></td><td>_______________________________________________</td></tr>
        <tr><td><strong>Packing details</strong></td><td>_______________________________________________</td></tr>
        <tr><td><strong>Remarks</strong></td><td>_______________________________________________</td></tr>
    </table>

    <p class="section">Lines — confirm qty</p>
    <table>
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:14%">Design No.</th>
                <th style="width:34%">Product / Description</th>
                <th style="width:8%">Unit</th>
                <th style="width:10%">PO Qty</th>
                <th style="width:12%">Confirmed Qty</th>
                <th style="width:17%">Colour / Size / Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchaseOrder->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->design_no ?: '—' }}</td>
                    <td>{{ $item->product?->name ?: ($item->description ?: '—') }}</td>
                    <td>{{ $item->unit ?: '—' }}</td>
                    <td>{{ $item->qty }}</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="muted" style="margin-top:14px">
        Supplier stamp / signature: ______________________ &nbsp;&nbsp; Date: ____________
    </p>
</body>
</html>
