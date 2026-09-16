@php($trimAccessory = $trimAccessory ?? null)
<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label required">Trim / Accessory Name</label>
        <input type="text"
               name="name"
               id="name"
               value="{{ old('name', $trimAccessory->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror"
               placeholder="e.g. Main Label, Size Label, Zip, Buttons"
               required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="status" class="form-label required">Status</label>
        <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
            <option value="active" @selected(old('status', $trimAccessory->status ?? 'active') === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $trimAccessory->status ?? 'active') === 'inactive')>Inactive</option>
        </select>
        @error('status')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{--
      "I need a small image against every line which will be added while
      making the bom cost ... a separate photo per trim type" (16-Sep call).
      Same upload pattern as Product Master's Photo field.
    --}}
    <div class="col-md-6">
        <label for="image" class="form-label">Photo</label>
        <input type="file"
               name="image"
               id="image"
               accept="image/png,image/jpeg,image/webp"
               class="form-control @error('image') is-invalid @enderror">
        <div class="form-text">Reference photo shown on the Inquiry BOM trims panel. JPG, PNG or WEBP, up to 2MB.</div>
        @error('image')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror

        @if(! empty($trimAccessory?->image_url))
            <div class="d-flex align-items-center gap-2 mt-2">
                <img src="{{ $trimAccessory->image_url }}" alt="{{ $trimAccessory->name }}"
                     class="rounded border" style="width:56px;height:56px;object-fit:cover">
                <div class="form-check mb-0">
                    <input type="checkbox" name="remove_image" id="remove_image" value="1" class="form-check-input">
                    <label for="remove_image" class="form-check-label small">Remove current photo</label>
                </div>
            </div>
        @endif
    </div>

    <div class="col-12">
        <label for="remarks" class="form-label">Remarks</label>
        <textarea name="remarks"
                  id="remarks"
                  rows="3"
                  class="form-control @error('remarks') is-invalid @enderror"
                  placeholder="Optional internal remarks or notes">{{ old('remarks', $trimAccessory->remarks ?? '') }}</textarea>
        @error('remarks')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
