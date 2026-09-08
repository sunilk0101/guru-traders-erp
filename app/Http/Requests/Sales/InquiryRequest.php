<?php

namespace App\Http\Requests\Sales;

use App\Models\DocumentFormat;
use App\Models\Inquiry;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared rules for creating and updating an inquiry.
 *
 * `mode` (posted as a hidden field, set by the Save Draft / Submit buttons in
 * _form.blade.php's script) decides how strict this pass is: Save Draft
 * always forces `status=draft` on the client before submit, and lets the
 * header/format/currency fields go in blank so a half-filled inquiry can
 * still be saved. Submit requires them.
 */
abstract class InquiryRequest extends FormRequest
{
    abstract protected function permission(): string;

    public function authorize(): bool
    {
        return $this->user()->can($this->permission());
    }

    protected function prepareForValidation(): void
    {
        // Header category/format remain the inquiry summary — if the user only
        // filled them on item lines, lift the first line up so list/show/OC
        // still have a header value and existing required_unless:draft rules pass.
        $items = array_values(array_filter((array) $this->input('items', [])));
        $first = $items[0] ?? null;
        if (! is_array($first)) {
            return;
        }

        $merge = [];
        if (blank($this->input('category_id')) && filled($first['category_id'] ?? null)) {
            $merge['category_id'] = $first['category_id'];
        }
        if (blank($this->input('document_format_id')) && filled($first['document_format_id'] ?? null)) {
            $merge['document_format_id'] = $first['document_format_id'];
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $requiredUnlessDraft = 'required_unless:mode,draft';

        return [
            'mode' => ['required', Rule::in(['draft', 'submit'])],

            'inquiry_date'   => ['required', 'date'],
            'buyer_ref'      => ['nullable', 'string', 'max:100'],
            // Change request #8 — quick-add lookup, replacing the fixed list.
            'source_id'      => [$requiredUnlessDraft, 'nullable', 'integer', Rule::exists('inquiry_sources', 'id')],
            'buyer_id'       => [$requiredUnlessDraft, 'nullable', 'integer', Rule::exists('buyers', 'id')],
            'category_id'    => [$requiredUnlessDraft, 'nullable', 'integer', Rule::exists('categories', 'id')],
            'document_format_id' => [$requiredUnlessDraft, 'nullable', 'integer', Rule::exists('document_formats', 'id')],

            'agent_id'               => ['nullable', 'integer', Rule::exists('agents', 'id')],
            'agent_commission_type'  => ['nullable', 'required_with:agent_commission_value', Rule::in(['percent', 'flat'])],
            'agent_commission_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],

            'currency_id'    => [$requiredUnlessDraft, 'nullable', 'integer', Rule::exists('currencies', 'id')],
            'exchange_rate'  => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],

            'expected_shipment_date' => ['nullable', 'date'],

            'delivery_details' => [$requiredUnlessDraft, 'nullable', 'string'],
            'packing_details'  => [$requiredUnlessDraft, 'nullable', 'string'],
            'remarks'          => ['nullable', 'string', 'max:2000'],

            'status' => ['required', Rule::in(array_keys(Inquiry::STATUSES))],

            'items'                        => ['nullable', 'array', 'max:200'],
            'items.*.category_id'          => [$requiredUnlessDraft, 'nullable', 'integer', Rule::exists('categories', 'id')],
            'items.*.document_format_id'   => [$requiredUnlessDraft, 'nullable', 'integer', Rule::exists('document_formats', 'id')],
            'items.*.design_no'            => ['nullable', 'string', 'max:150'],
            'items.*.description'          => ['nullable', 'string', 'max:2000'],
            'items.*.product_id'           => ['nullable', 'integer', Rule::exists('products', 'id')],
            'items.*.supplier_id'          => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'items.*.unit'                 => ['nullable', 'string', 'max:20'],
            'items.*.fob_value_id'         => ['nullable', 'integer', Rule::exists('fob_values', 'id')],
            'items.*.price'                => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'items.*.cost_price'           => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'items.*.status'               => ['nullable', Rule::in(array_keys(Inquiry::STATUSES))],
            'items.*.remarks'              => ['nullable', 'string', 'max:1000'],

            'items.*.colours'                    => ['nullable', 'array', 'max:50'],
            'items.*.colours.*.colour'           => ['nullable', 'string', 'max:60'],
            'items.*.colours.*.sizes'            => ['nullable', 'array', 'max:50'],
            'items.*.colours.*.sizes.*.size'     => ['nullable', 'string', 'max:20'],
            'items.*.colours.*.sizes.*.qty'      => ['nullable', 'integer', 'min:0', 'max:999999'],

            // Order Format custom columns — keyed by DocumentFormatColumn::key,
            // one value per column the format defines beyond the standard set.
            'items.*.custom'   => ['nullable', 'array', 'max:20'],
            'items.*.custom.*' => ['nullable', 'string', 'max:255'],

            // Task 12 — BOM snapshot (qty per finished piece), FOB-style costing.
            'items.*.bom'                     => ['nullable', 'array', 'max:100'],
            'items.*.bom.*.component_name'    => ['nullable', 'string', 'max:200'],
            'items.*.bom.*.qty'               => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'items.*.bom.*.unit'              => ['nullable', 'string', 'max:20'],
            'items.*.bom.*.is_custom'         => ['nullable', 'boolean'],
            'items.*.bom.*.remarks'           => ['nullable', 'string', 'max:500'],

            'followups'                => ['nullable', 'array', 'max:100'],
            'followups.*.id'           => ['nullable', 'integer'],
            'followups.*.date'         => ['nullable', 'date', 'required_with:followups.*.comment'],
            'followups.*.comment'      => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * A Submit with no item rows is an inquiry about nothing — Save Draft is
     * the button for "not ready yet", and no single-field rule can express
     * "required, but only on the other button".
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('mode') === 'submit' && blank(array_filter((array) $this->input('items', [])))) {
                $validator->errors()->add('items', 'Add at least one item before submitting the inquiry.');
            }

            $this->validateItemUnits($validator);
        });
    }

    /**
     * Unit is a dropdown of Order Format chips, not free text — but Product
     * Master can also supply unit_export / unit_po. Accept either source so
     * auto-fill from the product does not fail validation when that unit is
     * missing from the format's chip list.
     *
     * On update, also allow units already saved on this inquiry: the form's
     * ensureUnitOption keeps those visible when a chip is later removed from
     * the Order Format, and re-saving must not reject them.
     */
    private function validateItemUnits(Validator $validator): void
    {
        $items = (array) $this->input('items', []);
        if ($items === []) {
            return;
        }

        $formatIds = collect($items)->pluck('document_format_id')->filter()->unique()->values();
        $headerFormatId = $this->input('document_format_id');
        if (filled($headerFormatId)) {
            $formatIds = $formatIds->push($headerFormatId)->unique()->values();
        }

        $formats = $formatIds->isEmpty()
            ? collect()
            : DocumentFormat::query()->whereIn('id', $formatIds)->with('units')->get()->keyBy('id');

        $productIds = collect($items)->pluck('product_id')->filter()->unique()->values();
        $products = $productIds->isEmpty()
            ? collect()
            : Product::query()->whereIn('id', $productIds)->get(['id', 'unit_po', 'unit_export'])->keyBy('id');

        $legacyUnits = [];
        $inquiry = $this->route('inquiry');
        if ($inquiry instanceof Inquiry) {
            $legacyUnits = $inquiry->items()
                ->whereNotNull('unit')
                ->pluck('unit')
                ->unique()
                ->values()
                ->all();
        }

        foreach ($items as $index => $item) {
            $unit = $item['unit'] ?? null;
            if (blank($unit)) {
                continue;
            }

            $formatId = $item['document_format_id'] ?? $headerFormatId;
            $formatUnits = filled($formatId)
                ? ($formats->get($formatId)?->units->pluck('name')->all() ?? [])
                : [];

            $allowed = $formatUnits;
            $product = isset($item['product_id']) ? $products->get($item['product_id']) : null;
            if ($product) {
                $allowed = array_values(array_unique(array_filter([
                    ...$allowed,
                    $product->unit_export,
                    $product->unit_po,
                ])));
            }

            if ($legacyUnits !== []) {
                $allowed = array_values(array_unique(array_filter([
                    ...$allowed,
                    ...$legacyUnits,
                ])));
            }

            if ($allowed !== [] && ! in_array($unit, $allowed, true)) {
                $validator->errors()->add(
                    "items.{$index}.unit",
                    'The selected unit must match the order format or the product\'s unit.'
                );
            }
        }
    }

    public function attributes(): array
    {
        return [
            'source_id'                      => 'source',
            'buyer_id'                       => 'buyer',
            'category_id'                    => 'default category',
            'document_format_id'             => 'default order format',
            'items.*.category_id'            => 'item category',
            'items.*.document_format_id'     => 'item order format',
            'currency_id'                    => 'currency',
            'delivery_details'               => 'delivery details',
            'packing_details'                => 'packing details',
        ];
    }
}
