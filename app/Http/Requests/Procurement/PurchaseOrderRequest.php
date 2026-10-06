<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class PurchaseOrderRequest extends FormRequest
{
    abstract protected function permission(): string;

    public function authorize(): bool
    {
        return $this->user()->can($this->permission());
    }

    public function rules(): array
    {
        $requiredUnlessDraft = 'required_unless:status,draft';

        return [
            'order_confirmation_id' => ['required', 'integer', Rule::exists('order_confirmations', 'id')],
            'supplier_id'           => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'po_date'               => ['required', 'date'],
            'dispatch_date'         => ['nullable', 'date'],
            'remarks'               => ['nullable', 'string', 'max:2000'],

            'delivery_details' => [$requiredUnlessDraft, 'nullable', 'string'],
            'packing_details'  => [$requiredUnlessDraft, 'nullable', 'string'],

            // 'partial'/'received' are Goods Inward's to set, once that
            // module exists — not reachable from this screen.
            'status' => ['required', Rule::in(['draft', 'raised'])],

            'items'                        => ['nullable', 'array', 'max:200'],
            'items.*.order_confirmation_item_id' => ['nullable', 'integer', Rule::exists('order_confirmation_items', 'id')],
            'items.*.design_no'            => ['nullable', 'string', 'max:150'],
            'items.*.description'          => ['nullable', 'string', 'max:2000'],
            'items.*.product_id'           => ['nullable', 'integer', Rule::exists('products', 'id')],
            'items.*.unit'                 => ['nullable', 'string', 'max:20'],
            'items.*.cost_price'           => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'items.*.remarks'              => ['nullable', 'string', 'max:1000'],
            'items.*.image'                => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'items.*.existing_image_path'  => ['nullable', 'string', 'max:255'],

            'items.*.colours'                => ['nullable', 'array', 'max:50'],
            'items.*.colours.*.colour'       => ['nullable', 'string', 'max:60'],
            'items.*.colours.*.sizes'        => ['nullable', 'array', 'max:50'],
            'items.*.colours.*.sizes.*.size' => ['nullable', 'string', 'max:20'],
            'items.*.colours.*.sizes.*.qty'  => ['nullable', 'integer', 'min:0', 'max:999999'],

            'items.*.custom'   => ['nullable', 'array', 'max:20'],
            'items.*.custom.*' => ['nullable', 'string', 'max:255'],

            'timeline'             => ['nullable', 'array', 'max:100'],
            'timeline.*.date'      => ['nullable', 'date', 'required_with:timeline.*.note'],
            'timeline.*.note'      => ['nullable', 'string', 'max:255'],
            'timeline.*.qty'       => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('status') === 'raised' && blank(array_filter((array) $this->input('items', [])))) {
                $validator->errors()->add('items', 'Add at least one item before raising the PO.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'order_confirmation_id' => 'contract (OC)',
            'supplier_id'           => 'supplier',
            'delivery_details'      => 'delivery details',
            'packing_details'       => 'packing details',
        ];
    }
}
