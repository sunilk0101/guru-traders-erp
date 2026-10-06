<?php

namespace App\Http\Requests\Masters;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bom.create');
    }

    public function rules(): array
    {
        return $this->bomRules();
    }

    /**
     * @return array<string, mixed>
     */
    protected function bomRules(?int $ignoreId = null): array
    {
        return [
            'name'    => ['required', 'string', 'max:150', Rule::unique('boms', 'name')->ignore($ignoreId)],
            'status'  => ['required', Rule::in(['active', 'inactive'])],
            'remarks' => ['nullable', 'string', 'max:1000'],

            'lines'                      => ['nullable', 'array', 'max:200'],
            'lines.*.component_name'     => ['nullable', 'string', 'max:200'],
            'lines.*.size'               => ['nullable', 'string', 'max:60'],
            'lines.*.qty'                => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'lines.*.rate'               => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'lines.*.unit'               => ['nullable', 'string', 'max:20'],
            'lines.*.remarks'            => ['nullable', 'string', 'max:500'],
            'lines.*.image'              => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'lines.*.existing_image_path'=> ['nullable', 'string', 'max:255'],
            'lines.*.remove_image'       => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A BOM with this name already exists.',
        ];
    }
}
