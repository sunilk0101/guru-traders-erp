<?php

namespace App\Http\Requests\Masters;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrimAccessoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('trim-accessory.edit');
    }

    public function rules(): array
    {
        $trimAccessoryId = $this->route('trim_accessory')?->id ?? $this->route('trim_accessory');

        return [
            'name'         => ['required', 'string', 'max:150', Rule::unique('trim_accessories', 'name')->ignore($trimAccessoryId)],
            'status'       => ['required', Rule::in(['active', 'inactive'])],
            'remarks'      => ['nullable', 'string', 'max:1000'],
            'image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A Trim / Accessory with this name already exists.',
        ];
    }
}
