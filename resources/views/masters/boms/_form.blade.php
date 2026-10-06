@php
    $bom = $bom ?? null;
    $trimThumbs = $trimThumbs ?? collect();
    $lineRows = old('lines', $bom?->lines?->toArray() ?? []);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label required">BOM Name</label>
        <input type="text"
               name="name"
               id="name"
               value="{{ old('name', $bom->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror"
               placeholder="e.g. Standard garment BOM"
               required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="status" class="form-label required">Status</label>
        <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
            <option value="active" @selected(old('status', $bom->status ?? 'active') === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $bom->status ?? 'active') === 'inactive')>Inactive</option>
        </select>
        @error('status')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="remarks" class="form-label">Remarks</label>
        <textarea name="remarks"
                  id="remarks"
                  rows="2"
                  class="form-control @error('remarks') is-invalid @enderror"
                  placeholder="Optional internal remarks">{{ old('remarks', $bom->remarks ?? '') }}</textarea>
        @error('remarks')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mt-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <h6 class="mb-0">Lines</h6>
            <div class="text-body-secondary small">Photo column works like Excel — Buttons, Thread, Zip each keep their own image.</div>
        </div>
        <button type="button" id="add-bom-line" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-plus-lg me-1"></i>Add line
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead class="table-light">
                <tr class="small text-nowrap">
                    <th style="width:72px">Photo</th>
                    <th>Trim / Accessory</th>
                    <th style="width:7rem">Size</th>
                    <th>Description</th>
                    <th style="width:6.5rem">Qty</th>
                    <th style="width:6.5rem">Rate</th>
                    <th style="width:2.5rem"></th>
                </tr>
            </thead>
            <tbody id="bom-lines-wrap">
                @foreach($lineRows as $index => $row)
                    @include('masters.boms._line', ['index' => $index, 'row' => $row])
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<template id="tpl-bom-line">
    @include('masters.boms._line', ['index' => '__INDEX__', 'row' => []])
</template>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const wrap = document.getElementById('bom-lines-wrap');
    const template = document.getElementById('tpl-bom-line');
    const trimThumbs = @json($trimThumbs);

    function nextIndex() {
        let max = -1;
        wrap.querySelectorAll('[data-bom-line]').forEach(function (row) {
            const i = parseInt(row.dataset.index, 10);
            if (! isNaN(i) && i > max) max = i;
        });
        return max + 1;
    }

    function applyTrimPreview(row) {
        const name = (row.querySelector('.js-bom-line-name')?.value || '').trim().toLowerCase();
        const img = row.querySelector('.js-bom-line-preview');
        const file = row.querySelector('.js-bom-line-file');
        if (! img) return;
        if (file && file.files && file.files[0]) return;
        if (img.dataset.own === '1') return;
        const url = trimThumbs[name] || '';
        if (url) {
            img.src = url;
            img.classList.remove('d-none');
        } else if (! img.dataset.own) {
            img.src = '';
            img.classList.add('d-none');
        }
    }

    document.getElementById('add-bom-line')?.addEventListener('click', function () {
        const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex()));
        wrap.insertAdjacentHTML('beforeend', html);
    });

    wrap.addEventListener('click', function (e) {
        if (e.target.closest('.js-remove-bom-line')) {
            e.target.closest('[data-bom-line]')?.remove();
        }
    });

    wrap.addEventListener('input', function (e) {
        if (e.target.classList.contains('js-bom-line-name')) {
            applyTrimPreview(e.target.closest('[data-bom-line]'));
        }
    });

    wrap.addEventListener('change', function (e) {
        if (! e.target.classList.contains('js-bom-line-file')) return;
        const row = e.target.closest('[data-bom-line]');
        const img = row.querySelector('.js-bom-line-preview');
        const file = e.target.files && e.target.files[0];
        if (! file) return;
        img.src = URL.createObjectURL(file);
        img.classList.remove('d-none');
        img.dataset.own = '1';
    });

    wrap.querySelectorAll('[data-bom-line]').forEach(applyTrimPreview);
});
</script>
@endpush
