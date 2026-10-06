@php
    $index = $index ?? 0;
    $row = $row ?? [];
    $name = $row['component_name'] ?? '';
    $size = $row['size'] ?? '';
    $qty = $row['qty'] ?? '1';
    $rate = $row['rate'] ?? '';
    $remarks = $row['remarks'] ?? '';
    $imagePath = $row['image_path'] ?? ($row['existing_image_path'] ?? '');
    $imageUrl = $row['image_url'] ?? ($imagePath ? \Illuminate\Support\Facades\Storage::disk('public')->url($imagePath) : null);
@endphp
<tr class="bom-line-row" data-bom-line data-index="{{ $index }}">
    <td>
        <img class="js-bom-line-preview rounded border bg-body-tertiary {{ $imageUrl ? '' : 'd-none' }}"
             src="{{ $imageUrl }}" alt=""
             @if($imageUrl) data-own="1" @endif
             style="width:40px;height:40px;object-fit:cover">
        <input type="file" name="lines[{{ $index }}][image]" accept="image/png,image/jpeg,image/webp"
               class="form-control form-control-sm mt-1 js-bom-line-file">
        @if($imagePath)
            <input type="hidden" name="lines[{{ $index }}][existing_image_path]" value="{{ $imagePath }}">
        @endif
    </td>
    <td>
        <input type="text" name="lines[{{ $index }}][component_name]" class="form-control form-control-sm js-bom-line-name"
               maxlength="200" value="{{ $name }}" placeholder="e.g. Buttons">
    </td>
    <td>
        <input type="text" name="lines[{{ $index }}][size]" class="form-control form-control-sm"
               maxlength="60" value="{{ $size }}" placeholder="28L">
    </td>
    <td>
        <input type="text" name="lines[{{ $index }}][remarks]" class="form-control form-control-sm"
               maxlength="500" value="{{ $remarks }}">
    </td>
    <td>
        <input type="number" step="0.0001" min="0" name="lines[{{ $index }}][qty]" class="form-control form-control-sm"
               value="{{ $qty }}">
    </td>
    <td>
        <input type="number" step="0.0001" min="0" name="lines[{{ $index }}][rate]" class="form-control form-control-sm"
               value="{{ $rate }}">
    </td>
    <td>
        <button type="button" class="btn btn-sm btn-outline-danger js-remove-bom-line" title="Remove">
            <i class="bi bi-x"></i>
        </button>
    </td>
</tr>
