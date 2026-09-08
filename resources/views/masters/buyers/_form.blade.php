@props([
    'buyer' => null,
    // Preview of the code a new buyer will get — see BuyerController::create().
    'nextCode' => null,
    'categories' => [],
    'agents' => [],
    'countries' => [],
    // Change request #4 — "Default the Country to India".
    'indiaCountryId' => null,
    // Rendered server-side for the country/state already chosen; the cascade
    // reloads them from GeoController once a parent changes.
    'states' => [],
    'cities' => [],
    'ports' => [],
    'designations' => [],
    'paymentTerms' => [],
    'incoterms' => [],
    'currencies' => [],
    // Payment term ids that split the payment, so the JS toggle and
    // BuyerRequest read the same `has_split` column rather than two lists.
    'splitTermIds' => [],
])

@php
    use App\Models\Buyer;

    /**
     * Buyer form — the client's Buyer Master sheet in the sheet's own order,
     * plus the fields their working prototype carries and the sheet did not:
     * the advance / at-sight split, and the accepted sets beside the default
     * currency and incoterm. See the 000200 expand migration for why each
     * one exists.
     *
     * Laid out one field per line, label on the left, grouped into sections —
     * same as the Category and Product masters. 26 columns in a flat grid is
     * unreadable.
     *
     * "GST/VAT No" (sheet col H) was originally left off the form — the sheet
     * marks it "not required" and highlights it, and the column stayed nullable
     * so it could be switched on later without a migration. It has since been
     * asked for, alongside a Designation for the contact (col E had none,
     * unlike Supplier/Jobber's per-contact designation) — both added below.
     */

    // Categories already linked, for the multi-select.
    $selectedCategories = $buyer?->categories->pluck('id')->all() ?? [];

    /*
     * The accepted sets. On a buyer saved before these pivots existed the
     * relation is empty, so the single default stands in — otherwise editing
     * such a buyer would show an empty multi-select and saving would clear a
     * default the user was never shown.
     */
    $selectedCurrencies = $buyer?->currencies->pluck('id')->all()
        ?: array_filter([$buyer?->currency_id]);

    $selectedIncoterms = $buyer?->incoterms->pluck('id')->all()
        ?: array_filter([$buyer?->incoterm_id]);

    /*
     * Whether the advance / at-sight boxes start open. Resolved server-side so
     * a failed validation round-trip does not flash them shut before the
     * script runs — the errors are rendered inside that block.
     */
    $splitTermVisible = in_array(
        (int) old('payment_term_id', $buyer?->payment_term_id),
        array_map('intval', $splitTermIds),
        true,
    );

    /**
     * Carton mark rows to render (sheet col X).
     *
     * On an edit, whatever was saved. On a new buyer — or on a buyer saved
     * before any marks were entered — the five lines the sheet draws, as a
     * starting point. Labels stay editable either way.
     */
    $cartonLines = old('carton_markings');

    if ($cartonLines === null) {
        $cartonLines = $buyer?->cartonMarkings->isNotEmpty()
            ? $buyer->cartonMarkings->map(fn ($line) => [
                'label' => $line->label,
                'value' => $line->value,
            ])->all()
            : collect(Buyer::DEFAULT_CARTON_LINES)
                ->map(fn ($line) => ['label' => $line['label'], 'value' => null])
                ->all();
    }

    $cartonPlaceholders = collect(Buyer::DEFAULT_CARTON_LINES)->pluck('placeholder')->all();

    /**
     * Change request #3 — "up to 3 additional contacts per record", beyond
     * the single Contact Person above. Same pattern as Supplier/Jobber's
     * col L "other contact persons".
     */
    $extraContacts = old('contacts');

    if ($extraContacts === null) {
        $extraContacts = $buyer
            ? $buyer->contacts->map(fn ($c) => [
                'name'           => $c->name,
                'designation_id' => $c->designation_id,
                'mobile'         => $c->mobile,
                'email'          => $c->email,
            ])->values()->all()
            : [];
    }

    if ($extraContacts === []) {
        $extraContacts = [['name' => null, 'designation_id' => null, 'mobile' => null, 'email' => null]];
    }

    /**
     * Whether the Secondary Contact Person section starts open.
     *
     * Open on an edit that already has one saved, or on a round-trip that
     * failed validation — closing it then would hide the row the error
     * belongs to. Closed on a brand new buyer: an empty table with one blank
     * row is clutter nobody asked to see, and the toggle is the "I have one
     * to add" affordance instead.
     */
    $secondaryContactsVisible = old('contacts') !== null
        || ($buyer?->contacts->isNotEmpty() ?? false);
@endphp

{{-- ===================== A–D · IDENTIFICATION ===================== --}}
<x-ui.form-section title="Identification" icon="bi-globe-asia-australia"
                   subtitle="Who the buyer is, here and on the export invoice.">
    <div class="form-stack">

        {{-- Col A — sheet originally asked for a typed code with a duplicate
             check; the client has since asked for BUY01-style auto-generated
             codes instead, same treatment as Category's CAT001. Not submitted
             (no name= attribute) — the real code is assigned at insert time,
             see BuyerService::create(). --}}
        <div class="row form-line">
            <label class="col-sm-4 col-lg-3 col-form-label fw-semibold">Display Code</label>
            <div class="col-sm-8 col-lg-9">
                <div class="input-group">
                    <span class="input-group-text bg-body-secondary"><i class="bi bi-hash"></i></span>
                    <input type="text" class="form-control font-monospace readonly-bg"
                           value="{{ $buyer?->display_code ?? $nextCode ?? 'Auto' }}" readonly>
                </div>
                <div class="form-text">
                    {{ $buyer
                        ? 'Codes never change — they appear on documents already sent.'
                        : 'Assigned automatically when you save.' }}
                </div>
            </div>
        </div>

        {{-- Col B --}}
        <x-ui.field name="company_name" label="Company Name" :value="$buyer?->company_name" required
                    horizontal maxlength="200" placeholder="ABC Fashion Ltd" />

        {{-- Col C — the legal name on the invoice is not always the trading name --}}
        <x-ui.field name="name_on_export_invoice" label="Name on Export Invoice"
                    :value="$buyer?->name_on_export_invoice" horizontal maxlength="200"
                    placeholder="Exactly as it must print on the invoice"
                    hint="Leave blank to use the company name." />

        {{-- Col D — "allow multiple selection in a drop down menu which is
             connected from the category master" --}}
        <x-ui.select name="category_ids" label="Category of Items"
                     :options="$categories" :selected="$selectedCategories"
                     horizontal searchable multiple
                     placeholder="Search categories…"
                     hint="Pick every category this buyer orders." />

    </div>
</x-ui.form-section>

{{-- ======================= E–G · CONTACT ========================== --}}
<x-ui.form-section title="Contact" icon="bi-person-lines-fill">
    <div class="form-stack">

        {{-- Col E --}}
        <x-ui.field name="contact_person" label="Contact Person" :value="$buyer?->contact_person"
                    horizontal maxlength="120" placeholder="John Smith" />

        {{-- Same lookup Supplier/Jobber contacts use. Typing a name not already
             in the list adds it, same as Payment Terms above — see
             BuyerController::storeDesignation(). --}}
        <x-ui.select name="contact_designation_id" label="Designation" :options="$designations"
                     :selected="old('contact_designation_id', $buyer?->contact_designation_id)"
                     horizontal searchable placeholder="Search designation…"
                     data-create-url="{{ route('masters.buyers.designations.store') }}" />

        {{-- Col F --}}
        <x-ui.field name="email" label="Email" type="email" :value="$buyer?->email"
                    horizontal maxlength="150" placeholder="john@abcfashion.com" />

        {{-- Col G --}}
        <x-ui.field name="mobile" label="Mobile" :value="$buyer?->mobile"
                    horizontal maxlength="30" placeholder="+44 987654321" />

        {{-- Col H — see the docblock above for why this is now rendered. --}}
        <x-ui.field name="gst_vat_no" label="GST / VAT No." :value="old('gst_vat_no', $buyer?->gst_vat_no)"
                    horizontal maxlength="15" placeholder="33ABCDE1234F1Z5"
                    class="font-monospace text-uppercase" />

    </div>
</x-ui.form-section>

{{-- ============= SECONDARY CONTACT PERSON (change request #3) ============ --}}
<x-ui.form-section title="Secondary Contact Person" icon="bi-people"
                   subtitle="Optional — add as many as needed, beyond the contact person above.">

    <div class="form-check mb-3">
        <input type="checkbox" class="form-check-input" id="toggle-secondary-contacts"
               @checked($secondaryContactsVisible)>
        <label class="form-check-label" for="toggle-secondary-contacts">Add a secondary contact person</label>
    </div>

    <div id="secondary-contacts-wrap" class="{{ $secondaryContactsVisible ? '' : 'd-none' }}">
    <div class="table-responsive">
        <table class="table grid-table align-middle mb-0">
            <thead>
                <tr>
                    <th style="min-width:180px">Name</th>
                    <th style="min-width:180px">Designation</th>
                    <th style="min-width:150px">Mobile</th>
                    <th style="min-width:200px">Email</th>
                    <th style="width:40px"></th>
                </tr>
            </thead>
            <tbody id="contact-rows">
                @foreach($extraContacts as $index => $row)
                    <tr data-contact-row>
                        <td>
                            <input type="text" maxlength="120"
                                   name="contacts[{{ $index }}][name]"
                                   value="{{ $row['name'] ?? '' }}"
                                   class="form-control form-control-sm @error("contacts.{$index}.name") is-invalid @enderror"
                                   placeholder="Full name" aria-label="Contact name">
                            @error("contacts.{$index}.name")<div class="cell-error">{{ $message }}</div>@enderror
                        </td>
                        <td>
                            <select name="contacts[{{ $index }}][designation_id]"
                                    class="form-select form-select-sm @error("contacts.{$index}.designation_id") is-invalid @enderror"
                                    data-searchable data-placeholder="Designation…" aria-label="Designation">
                                <option value="">— Select —</option>
                                @foreach($designations as $id => $name)
                                    <option value="{{ $id }}" @selected((string) ($row['designation_id'] ?? '') === (string) $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error("contacts.{$index}.designation_id")<div class="cell-error">{{ $message }}</div>@enderror
                        </td>
                        <td>
                            <input type="text" maxlength="30"
                                   name="contacts[{{ $index }}][mobile]"
                                   value="{{ $row['mobile'] ?? '' }}"
                                   class="form-control form-control-sm @error("contacts.{$index}.mobile") is-invalid @enderror"
                                   placeholder="9876543210" aria-label="Mobile">
                            @error("contacts.{$index}.mobile")<div class="cell-error">{{ $message }}</div>@enderror
                        </td>
                        <td>
                            <input type="email" maxlength="150"
                                   name="contacts[{{ $index }}][email]"
                                   value="{{ $row['email'] ?? '' }}"
                                   class="form-control form-control-sm @error("contacts.{$index}.email") is-invalid @enderror"
                                   placeholder="name@company.com" aria-label="Email">
                            @error("contacts.{$index}.email")<div class="cell-error">{{ $message }}</div>@enderror
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 js-remove-contact"
                                    aria-label="Remove contact" data-bs-toggle="tooltip" title="Remove contact">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="add-contact">
        <i class="bi bi-plus-lg me-1"></i>Add contact
    </button>
    <div class="form-text">Rows with no name are not saved.</div>
    </div>{{-- /#secondary-contacts-wrap --}}

    <template id="contact-row-template">
        <tr data-contact-row>
            <td>
                <input type="text" maxlength="120" name="contacts[__INDEX__][name]"
                       class="form-control form-control-sm" placeholder="Full name" aria-label="Contact name">
            </td>
            <td>
                <select name="contacts[__INDEX__][designation_id]" class="form-select form-select-sm"
                        data-searchable data-placeholder="Designation…" aria-label="Designation">
                    <option value="">— Select —</option>
                    @foreach($designations as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <input type="text" maxlength="30" name="contacts[__INDEX__][mobile]"
                       class="form-control form-control-sm" placeholder="9876543210" aria-label="Mobile">
            </td>
            <td>
                <input type="email" maxlength="150" name="contacts[__INDEX__][email]"
                       class="form-control form-control-sm" placeholder="name@company.com" aria-label="Email">
            </td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-link text-danger p-0 js-remove-contact"
                        aria-label="Remove contact">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>
        </tr>
    </template>
</x-ui.form-section>

{{-- ==================== I–N · ADDRESS & PORT ====================== --}}
<x-ui.form-section title="Address & Destination" icon="bi-geo-alt"
                   subtitle="Prints on the export invoice and the packing list.">
    <div class="form-stack">

        {{-- Col I --}}
        <x-ui.textarea name="address" label="Address" :value="$buyer?->address"
                       horizontal rows="2" maxlength="255" placeholder="12 Fashion Street" />

        {{-- Cols L, K, J — Country, State, City.

             Ordered parent-first, which is the reverse of the sheet's own
             J, K, L. A dependent dropdown that sits above the field it depends
             on reads as broken: the user tabs into "City", finds it empty, and
             has no way to tell that the answer is three rows further down.

             The three cascade. State lists only states in the chosen country,
             City only cities in the chosen state, and both are re-checked
             against their parent on submit — see BuyerRequest. --}}
        <x-ui.select name="country_id" label="Country" :options="$countries"
                     :selected="$buyer?->country_id ?? $indiaCountryId" horizontal searchable
                     placeholder="Search country…"
                     data-cascade-child="#state_id" />

        {{-- Col K --}}
        <x-ui.select name="state_id" label="State" :options="$states"
                     :selected="$buyer?->state_id" horizontal searchable
                     placeholder="Search state…"
                     data-cascade-child="#city_id"
                     data-cascade-parent="#country_id"
                     :data-cascade-url="route('masters.geo.states')"
                     data-cascade-key="country_id"
                     data-cascade-empty="Select a country first"
                     :data-create-url="route('masters.geo.states.store')"
                     hint="Missing a state? Type the name and press Enter to add it." />

        {{-- Col J --}}
        <x-ui.select name="city_id" label="City" :options="$cities"
                     :selected="$buyer?->city_id" horizontal searchable
                     placeholder="Search city…"
                     data-cascade-parent="#state_id"
                     :data-cascade-url="route('masters.geo.cities')"
                     data-cascade-key="state_id"
                     data-cascade-empty="Select a state first"
                     :data-create-url="route('masters.geo.cities.store')"
                     hint="Missing a city? Type the name and press Enter to add it." />

        {{-- Col M --}}
        <x-ui.field name="pincode" label="PIN / ZIP Code" :value="$buyer?->pincode"
                    horizontal maxlength="20" placeholder="EC1A1AA" />

        {{-- Col N — same reasoning as Country --}}
        <x-ui.select name="port_id" label="Destination Port" :options="$ports"
                     :selected="$buyer?->port_id" horizontal searchable
                     placeholder="Search port…" />

    </div>
</x-ui.form-section>

{{-- ====================== O–P · AGENT ============================= --}}
<x-ui.form-section title="Agent" icon="bi-person-badge"
                   subtitle="Select only — list shows agents marked Buyer-side in Agent Master. New agents default to Buyer-side.">
    <div class="form-stack">

        {{-- Col O — select from Agent Master only (no free typing). --}}
        <x-ui.select name="agent_id" label="Agent" :options="$agents"
                     :selected="$buyer?->agent_id" horizontal searchable
                     placeholder="Search agent…"
                     hint="Missing an agent? Open Agent Master and set Agent Type = Buyer-side." />

        {{-- Col P — auto-filled from Agent Master; unlock to override. --}}
        <div class="row form-line">
            <label for="agent_commission_value" class="col-sm-4 col-lg-3 col-form-label fw-semibold">
                Agent Commission
            </label>
            <div class="col-sm-8 col-lg-9">
                <div class="input-group">
                    <input type="number" step="0.0001" min="0"
                           id="agent_commission_value" name="agent_commission_value"
                           value="{{ old('agent_commission_value', $buyer?->agent_commission_value) }}"
                           class="form-control @error('agent_commission_value') is-invalid @enderror"
                           placeholder="0.0000" readonly>
                    <select id="agent_commission_type" style="max-width:140px"
                            class="form-select @error('agent_commission_type') is-invalid @enderror" disabled>
                        @foreach(['percent' => '% Percent', 'amount' => 'Fixed amount'] as $value => $text)
                            <option value="{{ $value }}"
                                @selected(old('agent_commission_type', $buyer?->agent_commission_type) === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="agent_commission_type" id="agent_commission_type_hidden"
                       value="{{ old('agent_commission_type', $buyer?->agent_commission_type ?? 'percent') }}">
                @error('agent_commission_value')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @error('agent_commission_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="override-agent-commission">
                    <label class="form-check-label small" for="override-agent-commission">
                        Override commission for this buyer (otherwise taken from Agent Master)
                    </label>
                </div>
            </div>
        </div>

    </div>
</x-ui.form-section>

{{-- =================== Q–T · TRADE TERMS ========================== --}}
<x-ui.form-section title="Trade Terms" icon="bi-file-earmark-text"
                   subtitle="Defaults copied onto this buyer's quotations and order confirmations.">
    <div class="form-stack">

        {{-- Col Q — "drop down menu, add more in the future". Typing a name
             that is not in the list yet adds it, rather than sending the user
             off to a separate screen to create one first. --}}
        <x-ui.select name="payment_term_id" label="Payment Terms" :options="$paymentTerms"
                     :selected="$buyer?->payment_term_id" horizontal searchable
                     placeholder="Search terms…"
                     data-create-url="{{ route('masters.buyers.payment-terms.store') }}" />

        {{-- Opens only on a term that splits the payment. The two halves must
             total 100 — BuyerRequest checks it; this is the affordance. --}}
        <div id="advance-split-row" class="@unless($splitTermVisible) d-none @endunless">
            <div class="row form-line">
                <label class="col-sm-4 col-lg-3 col-form-label fw-semibold">
                    Advance / At Sight Split <span class="req">*</span>
                </label>
                <div class="col-sm-8 col-lg-9">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto" style="width:130px">
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.01" min="0.01" max="99.99"
                                       id="advance_percent" name="advance_percent"
                                       value="{{ old('advance_percent', $buyer?->advance_percent) }}"
                                       class="form-control @error('advance_percent') is-invalid @enderror"
                                       aria-label="Advance percentage">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-auto text-body-secondary">advance</div>
                        <div class="col-auto text-body-secondary">+</div>
                        <div class="col-auto" style="width:130px">
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.01" min="0.01" max="99.99"
                                       id="sight_percent" name="sight_percent"
                                       value="{{ old('sight_percent', $buyer?->sight_percent) }}"
                                       class="form-control @error('sight_percent') is-invalid @enderror"
                                       aria-label="At-sight percentage">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-auto text-body-secondary">at sight</div>
                        <div class="col-auto"><span class="small" id="split-total"></span></div>
                    </div>
                    @error('advance_percent')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @error('sight_percent')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Col R. The single value is the default pre-selected on a new order;
             the multi-select below is everything this buyer accepts. --}}
        <x-ui.select name="incoterm_id" label="Default Inco Term" :options="$incoterms"
                     :selected="$buyer?->incoterm_id" horizontal searchable
                     placeholder="Search incoterm…" />

        <x-ui.select name="incoterm_ids" label="Accepted Inco Terms" :options="$incoterms"
                     :selected="$selectedIncoterms" horizontal searchable multiple
                     placeholder="Search incoterms…"
                     hint="The default above is added automatically if you leave it out." />

        {{-- Col S — dropdown from Shipment Method lookup (client: not free text). --}}
        <x-ui.select name="shipment_method" label="Shipment Method" :options="$shipmentMethods"
                     :selected="$buyer?->shipment_method" horizontal searchable
                     placeholder="Search method…"
                     data-create-url="{{ route('masters.buyers.shipment-methods.store') }}"
                     hint="Type a new method to add it for next time." />

        {{-- Col T — a buyer invoiced in USD on one order and AED on the next is
             one buyer, so the accepted set is separate from the default. --}}
        <x-ui.select name="currency_id" label="Default Currency" :options="$currencies"
                     :selected="$buyer?->currency_id" horizontal searchable
                     placeholder="Search currency…" />

        <x-ui.select name="currency_ids" label="Accepted Currencies" :options="$currencies"
                     :selected="$selectedCurrencies" horizontal searchable multiple
                     placeholder="Search currencies…" />

    </div>
</x-ui.form-section>

{{-- ===================== U–W · BANK DETAILS ======================= --}}
<x-ui.form-section title="Bank Details" icon="bi-bank"
                   subtitle="Where this buyer remits payment from.">
    <div class="form-stack">

        {{-- Col U --}}
        <x-ui.field name="bank_name" label="Bank Name" :value="$buyer?->bank_name"
                    horizontal maxlength="120" placeholder="HSBC UK" />

        {{-- Col V --}}
        <x-ui.field name="account_number" label="Account Number" :value="$buyer?->account_number"
                    horizontal maxlength="40" placeholder="12345678" />

        {{-- Col W --}}
        <x-ui.field name="swift_code" label="SWIFT Code" :value="$buyer?->swift_code"
                    horizontal maxlength="20" placeholder="HBUKGB4B"
                    class="text-uppercase" />

    </div>
</x-ui.form-section>

{{-- ================== X · CARTON MARKING DETAILS ================== --}}
<x-ui.form-section title="Carton Marking Details" icon="bi-box-seam"
                   subtitle="Enter each line as it should appear on the carton / shipping marks.">
    <div class="row g-4">
        <div class="col-lg-7">
            <div id="carton-lines">
                @foreach($cartonLines as $index => $line)
                    <div class="carton-line" data-carton-line>
                        <div class="carton-line-head">
                            <input type="text" maxlength="60"
                                   name="carton_markings[{{ $index }}][label]"
                                   value="{{ $line['label'] ?? '' }}"
                                   class="carton-label-input" placeholder="LINE LABEL"
                                   aria-label="Line label">
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 js-remove-line"
                                    aria-label="Remove line" data-bs-toggle="tooltip" title="Remove line">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <input type="text" maxlength="120"
                               name="carton_markings[{{ $index }}][value]"
                               value="{{ $line['value'] ?? '' }}"
                               class="form-control js-carton-value"
                               placeholder="{{ $cartonPlaceholders[$index] ?? 'e.g. MADE IN INDIA' }}"
                               aria-label="Line value">
                        @error("carton_markings.{$index}.value")
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                @endforeach
            </div>

            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="add-carton-line">
                <i class="bi bi-plus-lg me-1"></i>Add line
            </button>
            <div class="form-text">
                Blank lines are not saved. Drag is not supported — use Add line and retype to reorder.
            </div>
        </div>

        {{-- The sheet shows a live preview beside the inputs. It is display
             only; what saves is the inputs. --}}
        <div class="col-lg-5">
            <div class="carton-preview">
                <div class="carton-preview-head">Live preview</div>
                <pre id="carton-preview-body" class="carton-preview-body mb-0"></pre>
            </div>
        </div>
    </div>
</x-ui.form-section>

{{-- ======================= Y, Z · OTHER =========================== --}}
<x-ui.form-section title="Other Details" icon="bi-card-text">
    <div class="form-stack">

        {{-- Col Z --}}
        <x-ui.select name="status" label="Status" required horizontal
                     :options="['active' => 'Active', 'inactive' => 'Inactive']"
                     :selected="$buyer?->status ?? 'active'"
                     :placeholder="false" />

        {{-- Col Y --}}
        <x-ui.textarea name="remarks" label="Remarks" :value="$buyer?->remarks"
                       horizontal rows="2" placeholder="Optional notes" />

        <x-ui.textarea name="comments" label="Comments" :value="$buyer?->comments"
                       horizontal rows="2" placeholder="Optional comments" />

    </div>
</x-ui.form-section>

<div class="form-actions">
    <button type="submit" class="btn btn-primary px-4">
        <i class="bi bi-check-lg me-1"></i>{{ $buyer ? 'Update' : 'Save' }} Buyer
    </button>
    <a href="{{ route('masters.buyers.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ------------------------------------------------------------------ *
     * Advance / at-sight split.
     *
     * Shown only on a payment term that has one. The id list comes from
     * payment_terms.has_split — the same column BuyerRequest validates
     * against, so the affordance and the rule cannot disagree.
     * ------------------------------------------------------------------ */
    const splitTermIds = @json(array_map('intval', $splitTermIds));
    const termSelect   = document.getElementById('payment_term_id');
    const splitRow     = document.getElementById('advance-split-row');
    const advanceInput = document.getElementById('advance_percent');
    const sightInput   = document.getElementById('sight_percent');
    const splitTotal   = document.getElementById('split-total');

    function renderSplitTotal() {
        const advance = parseFloat(advanceInput.value);
        const sight   = parseFloat(sightInput.value);

        if (isNaN(advance) || isNaN(sight)) {
            splitTotal.textContent = '';
            return;
        }

        const total = Math.round((advance + sight) * 100) / 100;
        const ok    = total === 100;

        splitTotal.textContent = ok ? '= 100% ✓' : '= ' + total + '% — must total 100';
        splitTotal.className = 'small ' + (ok ? 'text-success' : 'text-danger');
    }

    function applySplit() {
        // TomSelect keeps the underlying <select> in sync, so reading .value
        // works whether or not the widget upgraded this field.
        const visible = splitTermIds.includes(parseInt(termSelect?.value, 10));

        splitRow.classList.toggle('d-none', ! visible);

        if (! visible) {
            advanceInput.value = '';
            sightInput.value = '';
            splitTotal.textContent = '';
        } else {
            renderSplitTotal();
        }
    }

    termSelect?.addEventListener('change', applySplit);
    advanceInput?.addEventListener('input', renderSplitTotal);
    sightInput?.addEventListener('input', renderSplitTotal);
    renderSplitTotal();

    /* ------------------------------------------------------------------ *
     * Carton marking details (sheet col X) — repeatable lines with a live
     * preview. The preview is display only; the inputs are what save.
     * ------------------------------------------------------------------ */
    const lineWrap = document.getElementById('carton-lines');
    const preview  = document.getElementById('carton-preview-body');

    function renderPreview() {
        const lines = Array.from(lineWrap.querySelectorAll('.js-carton-value'))
            .map(input => input.value.trim())
            .filter(Boolean);

        preview.textContent = lines.length ? lines.join('\n') : 'Nothing entered yet.';
        preview.classList.toggle('is-empty', lines.length === 0);
    }

    /**
     * Field names carry their row index, so a row added after one was removed
     * must not reuse an index still in the DOM — two inputs sharing
     * carton_markings[2][value] would silently drop one on submit.
     */
    function reindex() {
        lineWrap.querySelectorAll('[data-carton-line]').forEach(function (row, index) {
            row.querySelectorAll('input[name^="carton_markings"]').forEach(function (input) {
                input.name = input.name.replace(/carton_markings\[\d+\]/, 'carton_markings[' + index + ']');
            });
        });
    }

    lineWrap.addEventListener('input', function (event) {
        if (event.target.classList.contains('js-carton-value')) renderPreview();
    });

    lineWrap.addEventListener('click', function (event) {
        const remove = event.target.closest('.js-remove-line');
        if (! remove) return;

        // Keep at least one row — an empty container has no "add" affordance
        // beyond the button, and clearing the last row is the same outcome.
        const rows = lineWrap.querySelectorAll('[data-carton-line]');
        if (rows.length === 1) {
            rows[0].querySelectorAll('input').forEach(input => input.value = '');
        } else {
            remove.closest('[data-carton-line]').remove();
        }

        reindex();
        renderPreview();
    });

    document.getElementById('add-carton-line').addEventListener('click', function () {
        const index = lineWrap.querySelectorAll('[data-carton-line]').length;
        const row = document.createElement('div');

        row.className = 'carton-line';
        row.setAttribute('data-carton-line', '');
        row.innerHTML =
            '<div class="carton-line-head">' +
                '<input type="text" maxlength="60" class="carton-label-input" ' +
                       'name="carton_markings[' + index + '][label]" placeholder="LINE LABEL" aria-label="Line label">' +
                '<button type="button" class="btn btn-sm btn-link text-danger p-0 js-remove-line" aria-label="Remove line">' +
                    '<i class="bi bi-x-lg"></i>' +
                '</button>' +
            '</div>' +
            '<input type="text" maxlength="120" class="form-control js-carton-value" ' +
                   'name="carton_markings[' + index + '][value]" placeholder="e.g. GROSS WT:" aria-label="Line value">';

        lineWrap.appendChild(row);
        row.querySelector('.carton-label-input').focus();
    });

    renderPreview();

    /* ------------------------------------------------------------------ *
     * Additional Contacts (change request #3, revised — no limit).
     * Same shape as the Supplier/Jobber form's "Other Contact Persons".
     * ------------------------------------------------------------------ */
    const contactRows     = document.getElementById('contact-rows');
    const contactTemplate = document.getElementById('contact-row-template');
    const addContactBtn   = document.getElementById('add-contact');

    function reindexContacts() {
        contactRows.querySelectorAll('[data-contact-row]').forEach(function (row, index) {
            row.querySelectorAll('[name^="contacts"]').forEach(function (field) {
                field.name = field.name.replace(/contacts\[\d+\]/, 'contacts[' + index + ']');
            });
        });
    }

    contactRows.addEventListener('click', function (event) {
        const remove = event.target.closest('.js-remove-contact');
        if (! remove) return;

        const all = contactRows.querySelectorAll('[data-contact-row]');

        if (all.length === 1) {
            all[0].querySelectorAll('input').forEach(function (field) { field.value = ''; });

            const select = all[0].querySelector('select');
            select?.tomselect ? select.tomselect.clear(true) : (select.value = '');
        } else {
            remove.closest('[data-contact-row]').remove();
        }

        reindexContacts();
    });

    addContactBtn.addEventListener('click', function () {
        const index = contactRows.querySelectorAll('[data-contact-row]').length;
        const row = contactTemplate.content.cloneNode(true).querySelector('tr');

        row.querySelectorAll('[name*="__INDEX__"]').forEach(function (field) {
            field.name = field.name.replace('__INDEX__', index);
        });

        contactRows.appendChild(row);

        const select = row.querySelector('select[data-searchable]');
        if (select && typeof window.upgradeSearchableSelect === 'function') {
            window.upgradeSearchableSelect(select);
        }

        row.querySelector('input')?.focus();
    });

    /* ------------------------------------------------------------------ *
     * Secondary Contact Person toggle — hidden until asked for. Unchecking
     * resets the table to one blank row rather than leaving typed values
     * sitting in a hidden section that would still post on submit.
     * ------------------------------------------------------------------ */
    const secondaryToggle = document.getElementById('toggle-secondary-contacts');
    const secondaryWrap   = document.getElementById('secondary-contacts-wrap');

    secondaryToggle?.addEventListener('change', function () {
        secondaryWrap.classList.toggle('d-none', ! secondaryToggle.checked);

        if (secondaryToggle.checked) return;

        contactRows.querySelectorAll('[data-contact-row]').forEach(function (row, index) {
            if (index === 0) {
                row.querySelectorAll('input').forEach(function (field) { field.value = ''; });

                const select = row.querySelector('select');
                select?.tomselect ? select.tomselect.clear(true) : (select && (select.value = ''));
            } else {
                row.remove();
            }
        });
    });

    /* ------------------------------------------------------------------ *
     * Agent commission — filled from Agent Master; locked until Override.
     * ------------------------------------------------------------------ */
    const agentSelect            = document.getElementById('agent_id');
    const agentCommissionValue   = document.getElementById('agent_commission_value');
    const agentCommissionType    = document.getElementById('agent_commission_type');
    const agentCommissionHidden  = document.getElementById('agent_commission_type_hidden');
    const overrideCommission     = document.getElementById('override-agent-commission');
    const agentCommissions       = @json($agentCommissions);

    function syncCommissionTypeHidden() {
        if (agentCommissionHidden && agentCommissionType) {
            agentCommissionHidden.value = agentCommissionType.value;
        }
    }

    function setCommissionLocked(locked) {
        if (! agentCommissionValue || ! agentCommissionType) return;
        agentCommissionValue.readOnly = locked;
        agentCommissionType.disabled = locked;
        if (locked) syncCommissionTypeHidden();
    }

    overrideCommission?.addEventListener('change', function () {
        setCommissionLocked(! overrideCommission.checked);
        if (! overrideCommission.checked) {
            // Re-apply master values when locking again.
            const id  = agentSelect?.value;
            const row = id ? agentCommissions[id] : null;
            if (row) {
                agentCommissionValue.value = row.value;
                agentCommissionType.value  = row.type;
                syncCommissionTypeHidden();
            }
        }
    });

    agentSelect?.addEventListener('change', function () {
        const id  = agentSelect.value;
        const row = id ? agentCommissions[id] : null;

        if (! row) return;

        agentCommissionValue.value = row.value;
        agentCommissionType.value  = row.type;
        syncCommissionTypeHidden();
        if (overrideCommission) {
            overrideCommission.checked = false;
            setCommissionLocked(true);
        }
    });

    agentCommissionType?.addEventListener('change', syncCommissionTypeHidden);
    setCommissionLocked(! (overrideCommission?.checked));
});
</script>
@endpush
