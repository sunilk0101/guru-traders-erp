<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
{{-- The Excel export renders this exact view too, and PhpSpreadsheet's HTML
     reader uses <title> as the sheet name — Excel sheet names can't contain
     "/", which inquiry_no always does (e.g. "INQ/2026-27/001"). --}}
<title>Inquiry {{ str_replace('/', '-', $inquiry->inquiry_no) }}</title>
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2430; }
    h1 { font-size: 16px; margin: 0 0 2px; }
    .muted { color: #6b7280; }
    table { border-collapse: collapse; width: 100%; }
    .header-table td { padding: 3px 6px 3px 0; vertical-align: top; width: 25%; }
    .header-table .lbl { display: block; font-size: 9px; text-transform: uppercase; letter-spacing: .03em; color: #8b95a5; }
    .items-table { margin-top: 14px; }
    .items-table th, .items-table td { border: 1px solid #d0d5dd; padding: 4px 6px; font-size: 10px; }
    .items-table th { background: #f4f6fb; text-align: left; }
    .text-end { text-align: right; }
    .text-center { text-align: center; }
    .totals { margin-top: 8px; text-align: right; font-size: 11px; }
    .totals strong { margin-left: 14px; }
    .terms { margin-top: 16px; }
    .terms h3 { font-size: 11px; margin: 0 0 3px; }
    .terms td { width: 50%; vertical-align: top; padding-right: 12px; }
</style>
</head>
<body>
    <table style="margin-bottom:6px">
        <tr>
            {{-- The Excel export renders this same view (see InquiryExport)
                 through PhpSpreadsheet's HTML reader, which only understands
                 HTML tags — an inline <svg> there just spams DOM warnings for
                 a mark a spreadsheet has no real use for. PDF only. --}}
            @unless($forExcel ?? false)
                <td style="width:52px;padding:0 10px 0 0;">
                    <x-brand-logo :size="44" />
                </td>
            @endunless
            <td style="padding:0;">
                <div style="font-size:13px;font-weight:700;color:#1d4ed8;">Guru Traders</div>
                <h1>Inquiry {{ $inquiry->inquiry_no }}</h1>
                <div class="muted">{{ $inquiry->statusLabel() }} &middot; {{ $inquiry->inquiry_date?->format('d M Y') }}</div>
            </td>
        </tr>
    </table>

    <table class="header-table" style="margin-top:10px">
        <tr>
            <td><span class="lbl">Buyer</span>{{ $inquiry->buyer?->company_name }} ({{ $inquiry->buyer?->display_code }})</td>
            <td><span class="lbl">Category</span>{{ $inquiry->category?->name ?? '—' }}</td>
            <td><span class="lbl">Order Format</span>{{ $inquiry->format?->name ?? '—' }}</td>
            <td><span class="lbl">Currency</span>{{ $inquiry->currency?->iso_code ?? '—' }}</td>
        </tr>
        <tr>
            <td><span class="lbl">Buyer's Ref / Season</span>{{ $inquiry->buyer_ref ?: '—' }}</td>
            <td><span class="lbl">Source</span>{{ $inquiry->sourceLabel() }}</td>
            <td><span class="lbl">Expected Shipment</span>{{ $inquiry->expected_shipment_date?->format('d M Y') ?? '—' }}</td>
            <td><span class="lbl">Exchange Rate</span>{{ $inquiry->exchange_rate ?? '—' }}</td>
        </tr>
    </table>

    @if($inquiry->remarks)
        <div style="margin-top:8px"><strong>Remarks:</strong> {{ $inquiry->remarks }}</div>
    @endif

    @php
        $columns  = $inquiry->format?->printColumns() ?? collect();
        $sizeTags = $columns->firstWhere('key', 'size')?->sub_columns ?? [];
        $firstUnit = $inquiry->items->first()?->unit;
    @endphp

    <table class="items-table">
        <thead>
            <tr>
                <th>#</th>
                @foreach($columns as $column)
                    @if($column->key === 'size' && filled($sizeTags))
                        @foreach($sizeTags as $tag)
                            <th class="text-center">{{ $tag }}</th>
                        @endforeach
                        <th class="text-center">Total</th>
                    @elseif($column->key !== 'image')
                        <th>{{ $column->key === 'price' ? $inquiry->format->priceLabel($firstUnit) : $column->label }}</th>
                        @if($column->key === 'unit' && blank($sizeTags))
                            <th class="text-center">Qty</th>
                        @endif
                    @endif
                @endforeach
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
            @php $sr = 0; @endphp
            @foreach($inquiry->items as $item)
                @forelse($item->colours as $colour)
                    @php
                        $sr++;
                        $colourQty = $colour->sizes->sum('qty');
                        $rowAmount = $colourQty * (float) $item->price;
                        $sizesByLabel = $colour->sizes->keyBy('size');
                    @endphp
                    <tr>
                        <td>{{ $sr }}</td>
                        @foreach($columns as $column)
                            @if($column->key === 'size' && filled($sizeTags))
                                @foreach($sizeTags as $tag)
                                    <td class="text-center">{{ $sizesByLabel[$tag]->qty ?? 0 }}</td>
                                @endforeach
                                <td class="text-center"><strong>{{ $colourQty }}</strong></td>
                            @elseif($column->key === 'image')
                                {{-- print-only placeholder column; no reference image attached to a line item --}}
                            @else
                                <td>
                                    @switch($column->key)
                                        @case('supplier') {{ $item->supplier?->label ?? '—' }} @break
                                        @case('design_no') {{ $item->design_no ?: '—' }} @break
                                        @case('product') {{ $item->product?->name ?? '—' }} @break
                                        @case('colour') {{ $colour->colour ?: '—' }} @break
                                        @case('unit') {{ $item->unit ?: '—' }} @break
                                        @case('price') {{ number_format((float) $item->price, 2) }} @break
                                        @default {{ $item->custom_values[$column->key] ?? '—' }}
                                    @endswitch
                                </td>
                                @if($column->key === 'unit' && blank($sizeTags))
                                    <td class="text-center">{{ $colourQty }}</td>
                                @endif
                            @endif
                        @endforeach
                        <td class="text-end">{{ number_format($rowAmount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td>{{ ++$sr }}</td>
                        <td colspan="{{ $columns->count() + (blank($sizeTags) ? 1 : 0) }}">{{ $item->design_no ?: '—' }} — no colour/size rows</td>
                        <td class="text-end">0.00</td>
                    </tr>
                @endforelse
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        Total Qty: <strong>{{ $inquiry->items->sum('qty') }}</strong>
        Total Amount: <strong>{{ number_format($inquiry->totalAmount(), 2) }}</strong>
    </div>

    @if($inquiry->delivery_details || $inquiry->packing_details)
        <table class="terms">
            <tr>
                <td>
                    <h3>Delivery Details</h3>
                    <div>{{ $inquiry->delivery_details ?: '—' }}</div>
                </td>
                <td>
                    <h3>Packing Details</h3>
                    <div>{{ $inquiry->packing_details ?: '—' }}</div>
                </td>
            </tr>
        </table>
    @endif

    @php $refImages = $inquiry->format?->images ?? collect(); @endphp
    @if($refImages->isNotEmpty())
        <h3 style="margin-top:18px;font-size:12px;">Reference Images</h3>
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:8px;">
            @foreach($refImages as $image)
                <div style="width:140px;text-align:center;">
                    <img src="{{ $image->url }}" alt="{{ $image->original_name }}"
                         style="width:140px;height:100px;object-fit:cover;border:1px solid #ccc;">
                    @if($image->caption)
                        <div style="font-size:10px;margin-top:4px;">{{ $image->caption }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</body>
</html>
