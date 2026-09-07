<?php

namespace App\Http\Requests\Masters;

use App\Models\DocumentFormatColumn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared rules for the Order Format master. The Store and Update subclasses
 * differ only in the permission they check and the row uniqueness ignores.
 */
abstract class DocumentFormatRequest extends FormRequest
{
    abstract protected function permission(): string;

    /** The row being edited, so unique rules can ignore it. */
    abstract protected function ignoreId(): ?int;

    public function authorize(): bool
    {
        return $this->user()->can($this->permission());
    }

    /**
     * Units are typed by hand, so they arrive however the user typed them.
     * Upper-casing and de-duplicating here rather than in the service means the
     * unique rule below compares what will actually be stored — "pcs" and
     * "PCS" must collide, not both save.
     */
    protected function prepareForValidation(): void
    {
        $units = collect($this->input('units', []))
            ->map(fn ($unit) => strtoupper(trim((string) $unit)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'units'                  => $units,
            'allow_multiple_colours' => $this->boolean('allow_multiple_colours'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('document_formats', 'name')->ignore($this->ignoreId()),
            ],
            'description'            => ['nullable', 'string', 'max:1000'],
            'status'                 => ['required', Rule::in(['active', 'inactive'])],
            'allow_multiple_colours' => ['required', 'boolean'],

            /*
             * A format with no unit cannot price a row: the Price column's
             * header is built from the selected unit, and a PO line has to be
             * priced per something.
             */
            'units'   => ['required', 'array', 'min:1', 'max:20'],
            'units.*' => ['string', 'max:20', 'regex:/^[A-Z0-9 .\/-]+$/'],

            /*
             * Columns arrive keyed by a client-side key — the real key for a
             * standard column, a throwaway generated id for a custom one
             * (its real key is re-derived from its label on save). Every
             * standard key is posted whether ticked or not, so a column
             * switched off keeps the label the user typed against it.
             */
            'columns'               => ['required', 'array'],
            'columns.*.label'       => ['required', 'string', 'max:60'],
            'columns.*.enabled'     => ['nullable', 'boolean'],
            'columns.*.mandatory'   => ['nullable', 'boolean'],
            'columns.*.is_custom'   => ['nullable', 'boolean'],
            'columns.*.print_only'  => ['nullable', 'boolean'],
            // Comma-separated Size tags (e.g. "S,M,L,XL") — only meaningful on
            // the `size` row, built client-side from its chip list.
            'columns.*.sub_columns' => ['nullable', 'string', 'max:500'],

            // The drag-reordered sequence of column keys — standard and
            // custom interleaved as the user arranged them.
            'column_order'   => ['required', 'array', 'min:1'],
            'column_order.*' => ['required', 'string', 'max:60'],

            'delivery_details' => ['nullable', 'string', 'max:2000'],
            'packing_details'  => ['nullable', 'string', 'max:2000'],

            // "Shown on print / PDF / Excel export only" — packing diagrams and
            // label samples, so raster formats only and a size a PDF can carry.
            'images'   => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],

            // Ids of already-saved images the user did not remove.
            'keep_images'   => ['nullable', 'array'],
            'keep_images.*' => ['integer'],

            'image_captions'   => ['nullable', 'array'],
            'image_captions.*' => ['nullable', 'string', 'max:500'],
            'new_image_captions'   => ['nullable', 'array'],
            'new_image_captions.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique'    => 'A format with this name already exists.',
            'units.required' => 'Add at least one unit — a price column needs something to be priced per.',
            'units.*.regex'  => 'A unit may contain letters, numbers, spaces and . / - only.',
            'images.*.max'   => 'Each reference image must be 4 MB or smaller.',
        ];
    }

    /**
     * Two things the rule array cannot express.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $columns = (array) $this->input('columns', []);

            /*
             * A key that is neither a STANDARD key nor flagged as a custom
             * column did not come from our form — posting one would create a
             * column no renderer knows how to fill.
             */
            foreach ($columns as $key => $column) {
                $isStandard = array_key_exists($key, DocumentFormatColumn::STANDARD);
                $isCustom   = filled($column['is_custom'] ?? null);

                if (! $isStandard && ! $isCustom) {
                    $validator->errors()->add('columns', 'Unknown column: '.e($key));
                }
            }

            /*
             * Every column switched off leaves an item table with nothing in
             * it but the serial number, quantity and amount that are always
             * drawn.
             */
            $enabled = collect($columns)->filter(fn ($column) => filled($column['enabled'] ?? null))->count();

            if ($enabled === 0) {
                $validator->errors()->add('columns', 'Keep at least one column — the item table would otherwise be empty.');
            }
        });
    }
}
