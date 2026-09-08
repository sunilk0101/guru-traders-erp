<?php

namespace App\Http\Requests\Masters;

use App\Models\PaymentTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared rules for creating and updating a buyer.
 *
 * Store and Update differ in exactly two ways — the permission checked, and
 * whether the uniqueness rule ignores the current row — so those are the two
 * things the subclasses supply. Same shape as ProductRequest.
 */
abstract class BuyerRequest extends FormRequest
{
    abstract protected function permission(): string;

    public function authorize(): bool
    {
        return $this->user()->can($this->permission());
    }

    protected function prepareForValidation(): void
    {
        // Same treatment as Supplier's gst_number — a GSTIN is fixed by law,
        // not house style, so the case is normalised before the format check.
        if ($this->filled('gst_vat_no')) {
            $this->merge(['gst_vat_no' => strtoupper(trim($this->string('gst_vat_no')->toString()))]);
        }

        // An empty commission value with a type selected is not a commission.
        // Blanking both keeps agent_commission_label() from rendering "%".
        if (blank($this->input('agent_commission_value'))) {
            $this->merge(['agent_commission_type' => null]);
        }

        /*
         * A child with no parent is dropped rather than rejected. Clearing the
         * country is a deliberate act, and answering it with "that state does
         * not belong to the selected country" would be blaming the user for a
         * field the cascade had already emptied in front of them.
         */
        if (blank($this->input('country_id'))) {
            $this->merge(['state_id' => null, 'city_id' => null]);
        }

        if (blank($this->input('state_id'))) {
            $this->merge(['city_id' => null]);
        }

        /*
         * The advance / at-sight split only means something on a payment term
         * that has one. Clearing it here rather than validating it away means a
         * buyer moved off "part advance" does not keep a 30/70 split nothing
         * reads — and does not have to be told about a field the form had
         * already hidden.
         */
        if (! $this->paymentTermHasSplit()) {
            $this->merge(['advance_percent' => null, 'sight_percent' => null]);
        }

        /*
         * The default has to be inside the accepted set, or the OC screen would
         * pre-select a currency the buyer is not invoiced in. Adding it is the
         * quiet fix; rejecting the save would be blaming the user for a set the
         * form builds from the same two widgets.
         */
        foreach ([
            'currency_id'         => 'currency_ids',
            'incoterm_id'         => 'incoterm_ids',
            'shipment_method_id'  => 'shipment_method_ids',
        ] as $default => $set) {
            $value = $this->input($default);

            if (blank($value)) {
                continue;
            }

            $chosen = array_map('strval', (array) $this->input($set, []));

            if (! in_array((string) $value, $chosen, true)) {
                $chosen[] = (string) $value;
                $this->merge([$set => $chosen]);
            }
        }
    }

    /**
     * Does the selected payment term carry an advance / at-sight split?
     *
     * Read off `payment_terms.has_split`, not off the name. The same table
     * already carries `days` rather than parsing "30 Days", for the reason its
     * seeder gives — and name-sniffing would be worse here than there, because
     * "Advance" and "50% Advance, 50% on Delivery" both contain the word and
     * only one of them has a split.
     */
    protected function paymentTermHasSplit(): bool
    {
        return (bool) PaymentTerm::whereKey($this->input('payment_term_id'))->value('has_split');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Col A is not here — display_code is server-assigned (see
            // BuyerService::create()), same reasoning as StoreCategoryRequest
            // omitting `code`: accepting one from the request would let a
            // crafted POST choose its own.

            // Cols B, C
            'company_name'           => ['required', 'string', 'max:200'],
            'name_on_export_invoice' => ['nullable', 'string', 'max:200'],

            // Col D — multi-select, connected to the category master
            'category_ids'           => ['nullable', 'array'],
            'category_ids.*'         => ['integer', Rule::exists('categories', 'id')],

            // Cols E, F, G
            'contact_person'         => ['nullable', 'string', 'max:120'],
            'contact_designation_id' => ['nullable', 'integer', Rule::exists('designations', 'id')],
            'email'                  => ['nullable', 'email', 'max:150'],
            'mobile'                 => ['nullable', 'string', 'max:30'],

            /*
             * Col H — still "not required" on the sheet, but no longer kept off
             * the form; the client asked for it alongside the contact's
             * designation. Format checked only when filled, same GSTIN pattern
             * SupplierRequest uses.
             */
            'gst_vat_no'             => ['nullable', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/'],

            /*
             * Change request #3 — "up to 3 additional contacts per record".
             * Same shape as SupplierRequest's `contacts`: a row with details
             * but no name is the one combination worth rejecting; a wholly
             * blank row is dropped by the service, not reported.
             */
            // Change request #3 (revised — CEO approved "no limit"). No cap.
            'contacts'                  => ['nullable', 'array'],
            'contacts.*.name'           => ['required_with:contacts.*.mobile,contacts.*.email,contacts.*.designation_id', 'nullable', 'string', 'max:120'],
            'contacts.*.designation_id' => ['nullable', 'integer', Rule::exists('designations', 'id')->where('status', 'active')],
            'contacts.*.mobile'         => ['nullable', 'string', 'max:30'],
            'contacts.*.email'          => ['nullable', 'email', 'max:150'],

            // Cols I–N
            'address'                => ['nullable', 'string', 'max:255'],
            'country_id'             => ['nullable', 'integer', Rule::exists('countries', 'id')],

            /*
             * Cols K and J. Each is checked against its parent, not just against
             * its own table: the cascade narrows the list in the browser, but a
             * hand-posted state_id from a different country would otherwise save
             * a buyer in "Tamil Nadu, United Kingdom".
             *
             * There is no "city requires a state" rule — prepareForValidation
             * has already nulled an orphaned city by this point, so such a rule
             * could never fire.
             */
            'state_id'               => [
                'nullable', 'integer',
                Rule::exists('states', 'id')->where(
                    fn ($q) => $q->where('country_id', $this->input('country_id'))
                ),
            ],
            'city_id'                => [
                'nullable', 'integer',
                Rule::exists('cities', 'id')->where(
                    fn ($q) => $q->where('state_id', $this->input('state_id'))
                ),
            ],

            'pincode'                => ['nullable', 'string', 'max:20'],
            'port_id'                => ['nullable', 'integer', Rule::exists('ports', 'id')],

            /*
             * Col O. The exists rule re-applies the "buyer side only" filter the
             * dropdown shows. Without it, a supplier-side agent id posted by
             * hand would save perfectly happily — the select is a UI filter, not
             * a constraint.
             */
            'agent_id'               => [
                'nullable', 'integer',
                Rule::exists('agents', 'id')
                    ->where('agent_type', 'buyer')
                    ->whereNull('deleted_at'),
            ],

            // Col P
            'agent_commission_type'  => ['nullable', 'required_with:agent_commission_value', Rule::in(['percent', 'amount'])],
            'agent_commission_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],

            // Cols Q–T
            'payment_term_id'        => ['nullable', 'integer', Rule::exists('payment_terms', 'id')],

            /*
             * The part-advance split. Only meaningful when the chosen payment
             * term is a part advance; prepareForValidation nulls both otherwise,
             * so these rules cannot fire on a term that has no split. The
             * "must total 100" check is in withValidator, where it can name both
             * fields at once.
             */
            'advance_percent'        => ['nullable', 'numeric', 'min:0.01', 'max:99.99'],
            'sight_percent'          => ['nullable', 'numeric', 'min:0.01', 'max:99.99'],

            /*
             * Default plus accepted set. The default must be one of the set —
             * checked in withValidator, because Rule::in against another field's
             * array is not expressible here.
             */
            'incoterm_id'            => ['nullable', 'integer', Rule::exists('incoterms', 'id')],
            'currency_id'            => ['nullable', 'integer', Rule::exists('currencies', 'id')],
            'shipment_method_id'     => ['nullable', 'integer', Rule::exists('shipment_methods', 'id')],

            'currency_ids'           => ['nullable', 'array'],
            'currency_ids.*'         => ['integer', Rule::exists('currencies', 'id')],
            'incoterm_ids'           => ['nullable', 'array'],
            'incoterm_ids.*'         => ['integer', Rule::exists('incoterms', 'id')],
            'shipment_method_ids'    => ['nullable', 'array'],
            'shipment_method_ids.*'  => ['integer', Rule::exists('shipment_methods', 'id')],

            // Cols U–W
            'bank_name'              => ['nullable', 'string', 'max:120'],
            'account_number'         => ['nullable', 'string', 'max:40'],
            'swift_code'             => ['nullable', 'string', 'max:20'],

            // Col X — repeatable carton mark lines
            'carton_markings'        => ['nullable', 'array', 'max:20'],
            'carton_markings.*.label' => ['required_with:carton_markings.*.value', 'nullable', 'string', 'max:60'],
            'carton_markings.*.value' => ['nullable', 'string', 'max:120'],

            // Cols Y, Z
            'status'                 => ['required', Rule::in(['active', 'inactive'])],
            'remarks'                => ['nullable', 'string', 'max:1000'],
            'comments'               => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Cross-field rules that no single-field rule can express.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateAdvanceSplit($validator);
            $this->validateContactsReachable($validator);
        });
    }

    /**
     * A secondary contact with a name but neither a mobile nor an email is
     * not reachable — the row required_with rule below only catches the
     * opposite gap (details with no name), so this is the other half.
     */
    private function validateContactsReachable(Validator $validator): void
    {
        foreach ((array) $this->input('contacts', []) as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            if (blank($row['mobile'] ?? null) && blank($row['email'] ?? null)) {
                $validator->errors()->add(
                    "contacts.{$index}.mobile",
                    'Add a mobile number or email so this contact can be reached.'
                );
            }
        }
    }

    /**
     * On a term that splits the payment, both halves are required and they have
     * to total 100.
     *
     * A 60/30 split is not "mostly right" — it is 10% of the order value with
     * no instruction attached, and the gap only surfaces when someone is
     * chasing the balance. prepareForValidation has already cleared both fields
     * on a term with no split, so nothing here can fire on one.
     */
    private function validateAdvanceSplit(Validator $validator): void
    {
        if (! $this->paymentTermHasSplit()) {
            return;
        }

        $advance = $this->input('advance_percent');
        $sight   = $this->input('sight_percent');

        if (blank($advance) || blank($sight)) {
            $validator->errors()->add(
                blank($advance) ? 'advance_percent' : 'sight_percent',
                'This payment term splits the payment, so both the advance and the at-sight percentage are needed.'
            );

            return;
        }

        // Compared in whole hundredths — 33.33 + 66.67 is exactly 100 there,
        // and 0.01 apart is a typo rather than a rounding artefact.
        if (round(((float) $advance + (float) $sight) * 100) !== 10000) {
            $validator->errors()->add(
                'sight_percent',
                'The advance and at-sight percentages must total 100 — they currently total '.
                rtrim(rtrim(number_format((float) $advance + (float) $sight, 2, '.', ''), '0'), '.').'.'
            );
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_name'           => 'company name',
            'name_on_export_invoice' => 'company name on export invoice',
            'category_ids'           => 'category of items',
            'contact_designation_id' => 'designation',
            'gst_vat_no'             => 'GST/VAT number',
            'country_id'             => 'country',
            'state_id'               => 'state',
            'city_id'                => 'city',
            'port_id'                => 'port',
            'agent_id'               => 'agent',
            'advance_percent'        => 'advance percentage',
            'sight_percent'          => 'at-sight percentage',
            'currency_ids'           => 'accepted currencies',
            'incoterm_ids'           => 'accepted incoterms',
            'shipment_method_ids'    => 'accepted shipment methods',
            'agent_commission_type'  => 'commission type',
            'agent_commission_value' => 'agent commission',
            'payment_term_id'        => 'payment terms',
            'incoterm_id'            => 'default inco term',
            'shipment_method_id'     => 'default shipment method',
            'currency_id'            => 'default currency',
            'swift_code'             => 'swift code',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agent_id.exists'     => 'That agent is not marked as a buyer-side agent.',
            'state_id.exists'     => 'That state does not belong to the selected country.',
            'city_id.exists'      => 'That city does not belong to the selected state.',
            'gst_vat_no.regex'    => 'That is not a valid GSTIN — expected 15 characters, e.g. 33ABCDE1234F1Z5.',
            'gst_vat_no.size'     => 'A GSTIN is exactly 15 characters.',
            'agent_commission_type.required_with' => 'Choose whether the commission is a percentage or an amount.',
            'contacts.*.name.required_with'       => 'Enter a name for this contact, or clear the row.',
        ];
    }
}
