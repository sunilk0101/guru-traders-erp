<?php

namespace App\Http\Requests\Masters;

class UpdateBomRequest extends StoreBomRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bom.edit');
    }

    public function rules(): array
    {
        $bomId = $this->route('bom')?->id ?? $this->route('bom');

        return $this->bomRules($bomId ? (int) $bomId : null);
    }
}
