<?php

namespace App\Http\Requests\Masters;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('category.edit');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:120', Rule::unique('categories', 'name')->ignore($this->route('category')->id)],

            // Several formats, not one — see the 000300 category_format
            // migration. An absent key clears the set, same as any other sync.
            'format_ids'   => ['nullable', 'array'],
            'format_ids.*' => ['integer', Rule::exists('document_formats', 'id')],

            'status'      => ['required', Rule::in(['active', 'inactive'])],
            'remarks'     => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A category with this name already exists (including deleted). Restore the deleted category instead of creating a new one.',
        ];
    }
}
