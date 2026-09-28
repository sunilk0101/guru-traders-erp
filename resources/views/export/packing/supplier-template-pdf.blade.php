<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Supplier Packing List Form</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 6px; }
        .muted { color: #555; font-size: 10px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #333; padding: 6px 5px; vertical-align: top; }
        th { background: #eee; font-size: 10px; text-align: left; }
        .meta td { border: none; padding: 3px 0; }
        .line { height: 22px; }
        .note { margin-top: 12px; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Supplier Packing List Form</h1>
    <div class="muted">
        Fill this form (or the matching Excel template) and return PDF / Excel.
        We will upload it against the export shipment — no double entry in office.
    </div>

    <table class="meta">
        @if(!empty($document))
            <tr><td width="30%"><strong>Export Doc</strong></td><td>{{ $document->doc_num }}</td></tr>
            <tr><td><strong>Buyer</strong></td><td>{{ $document->buyer?->company_name }}</td></tr>
        @endif
        <tr><td width="30%"><strong>Supplier name</strong></td><td>________________________________</td></tr>
        <tr><td><strong>Supplier code</strong></td><td>________________________________</td></tr>
        <tr><td><strong>PO / Reference</strong></td><td>________________________________</td></tr>
        <tr><td><strong>Pack date</strong></td><td>________________________________</td></tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width:8%">Ctn No</th>
                <th style="width:10%">Net kg</th>
                <th style="width:10%">Gross kg</th>
                <th style="width:14%">Dimensions</th>
                <th style="width:28%">Description / Style</th>
                <th style="width:8%">Unit</th>
                <th style="width:8%">Qty</th>
                <th style="width:14%">Colour / Size</th>
            </tr>
        </thead>
        <tbody>
            @for($i = 0; $i < 18; $i++)
                <tr class="line">
                    <td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="note">
        <strong>Instructions:</strong> One row per line inside a carton. Repeat the same Carton No for multiple lines in the same carton.
        Units: PCS / SET / MTRS. Prefer Excel template for large lists.
    </div>
</body>
</html>
