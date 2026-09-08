@props([
    'supplier' => null,
    'partyTypes' => [],
    'deliveryModes' => [],
    'supplierTypes' => [],
    'supplierTypeFlags' => [],
    'designations' => [],
    'categories' => [],
    'products' => [],
    'buyers' => [],
    'agents' => [],
    'countries' => [],
    // Change request #4 — "Default the Country to India".
    'indiaCountryId' => null,
    'states' => [],
    'cities' => [],
])

@php
    $selectedCategories = $supplier?->categories->pluck('id')->all() ?? [];
    // Change request #1 (revised) — multiple products, same shape as categories.
    $selectedProducts = $supplier?->products->pluck('id')->all() ?? [];
    // Change request #5, revised — the jobwork client is now one or more
    // linked buyers, same shape as products/categories above.
    $selectedBuyers = $supplier?->buyers->pluck('id')->all() ?? [];
    $primary = $supplier?->contacts->firstWhere('is_primary', true);
    $extraContacts = old('contacts');

    if ($extraContacts === null) {
        $extraContacts = $supplier
            ? $supplier->contacts->where('is_primary', false)->map(fn ($c) => [
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
     * Whether the Secondary Contact Person section starts open. Same
     * reasoning as the Buyer/Supplier form's $secondaryContactsVisible.
     */
    $secondaryContactsVisible = old('contacts') !== null
        || ($supplier?->contacts->where('is_primary', false)->isNotEmpty() ?? false);

    $selectedType = old('supplier_type_id', $supplier?->supplier_type_id);

    $gstVisible = ($supplierTypeFlags[$selectedType] ?? false) === true
        || $errors->has('gst_number');

    $msmeVisible = (bool) old('is_msme', $supplier?->is_msme)
        || $errors->has('msme_registration_no');

    $jobworkVisible = true; // Jobber master always shows jobwork section
@endphp

<input type="hidden" name="party_type" id="party_type" value="jobber">

{{-- ================== A–C · IDENTIFICATION ======================= --}}
<x-ui.form-section title="Identification" icon="bi-tools"
                   subtitle="Who the jobber is and their basic details.">
    <div class="form-stack">

        <div class="row form-line">
            <label for="display_code" class="col-sm-4 col-lg-3 col-form-label fw-semibold required">
                Display Code
            </label>
            <div class="col-sm-8 col-lg-9">
                <input type="text" id="display_code" name="display_code"
                       value="{{ old('display_code', $supplier?->display_code) }}"
                       class="form-control font-monospace text-uppercase @error('display_code') is-invalid @enderror"
                       style="max-width:220px" required placeholder="JOB01"
                       data-check-url="{{ route('masters.jobbers.check-code') }}">
                @error('display_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text js-code-feedback">Short code used on orders and reports. Unique across all suppliers and jobbers.</div>
            </div>
        </div>

        <x-ui.field name="company_name" label="Company Name" required :value="$supplier?->company_name"
                    horizontal maxlength="150" placeholder="e.g. Tirupur Jobwork Crafts" />

        <x-ui.field name="name_on_bill" label="Name on Bill" :value="$supplier?->name_on_bill"
                    horizontal maxlength="150" placeholder="Name as it appears on bills"
                    hint="Leave blank if identical to company name." />

        {{-- Was a raw multi-select missing the data-searchable attribute the
             TomSelect init script looks for, so it never upgraded and rendered
             as a bare listbox. Switched to the same component every other
             searchable field on this form uses. --}}
        <x-ui.select name="category_ids" label="Product Category" :options="$categories"
                     :selected="$selectedCategories" horizontal searchable multiple
                     required placeholder="Search and select categories…" />

        {{-- Change request #1 (revised) — a jobber can be linked to more than
             one product, same as the categories above. --}}
        <x-ui.select name="product_ids" label="Product" :options="$products"
                     :selected="$selectedProducts" horizontal searchable multiple
                     placeholder="Search products…" />

    </div>
</x-ui.form-section>

{{-- ================== D–G · REGISTRATION ========================= --}}
<x-ui.form-section title="Registration" icon="bi-card-heading">
    <div class="form-stack">

        {{-- Typing a name not already in the list adds it — same quick-add
             pattern as the Buyer form's Payment Terms / Designation fields. --}}
        <x-ui.select name="supplier_type_id" label="Jobber Type" required horizontal searchable
                     :options="$supplierTypes" :selected="$selectedType"
                     placeholder="Search or type to add a new type…"
                     data-create-url="{{ route('masters.suppliers.supplier-types.store') }}" />

        <div id="gst-row" class="row form-line {{ $gstVisible ? '' : 'd-none' }}">
            <label for="gst_number" class="col-sm-4 col-lg-3 col-form-label fw-semibold">GST Number</label>
            <div class="col-sm-8 col-lg-9">
                <input type="text" id="gst_number" name="gst_number"
                       value="{{ old('gst_number', $supplier?->gst_number) }}"
                       class="form-control font-monospace text-uppercase @error('gst_number') is-invalid @enderror"
                       style="max-width:260px" maxlength="15" placeholder="33AAAAA0000A1Z5">
                @error('gst_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>

        <x-ui.field name="pan_number" label="PAN Number" :value="$supplier?->pan_number"
                    horizontal maxlength="10" placeholder="AAAAA0000A"
                    class="font-monospace text-uppercase" />

        <div class="row form-line">
            <label class="col-sm-4 col-lg-3 col-form-label fw-semibold">MSME</label>
            <div class="col-sm-8 col-lg-9 pt-2">
                <div class="form-check">
                    <input type="hidden" name="is_msme" value="0">
                    <input type="checkbox" class="form-check-input" id="is_msme" name="is_msme" value="1"
                           @checked(old('is_msme', $supplier?->is_msme))>
                    <label class="form-check-label" for="is_msme">Registered under MSME / Udyam</label>
                </div>
            </div>
        </div>

        {{-- Mandatory once MSME is ticked — same required treatment as the
             Supplier form's equivalent field (SupplierRequest validates both
             the same way: msme_registration_no is required_if is_msme,true). --}}
        <div class="row form-line @unless($msmeVisible) d-none @endunless" id="msme-row">
            <label for="msme_registration_no" class="col-sm-4 col-lg-3 col-form-label fw-semibold">
                MSME Registration No <span class="req">*</span>
            </label>
            <div class="col-sm-8 col-lg-9">
                <input type="text" id="msme_registration_no" name="msme_registration_no"
                       value="{{ old('msme_registration_no', $supplier?->msme_registration_no) }}"
                       class="form-control font-monospace text-uppercase @error('msme_registration_no') is-invalid @enderror"
                       style="max-width:260px" placeholder="UDYAM-TN-33-0001234" autocomplete="off">
                @error('msme_registration_no')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">MSME dues carry a statutory 45-day payment limit — this drives that check later.</div>
            </div>
        </div>

    </div>
</x-ui.form-section>

{{-- ================== H–L · CONTACT PERSONS ======================= --}}
<x-ui.form-section title="Contact Persons" icon="bi-people">
    <div class="form-stack">

        <div class="p-3 bg-body-tertiary rounded border mb-3">
            <div class="small fw-semibold text-body-secondary text-uppercase mb-2">Primary Contact</div>
            <div class="row g-2">
                <div class="col-md-3">
                    <label for="primary_name" class="form-label small text-body-secondary mb-1 required">Name</label>
                    <input type="text" id="primary_name" name="primary_contact[name]"
                           value="{{ old('primary_contact.name', $primary?->name) }}"
                           class="form-control form-control-sm @error('primary_contact.name') is-invalid @enderror"
                           required placeholder="Contact Name">
                    @error('primary_contact.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="primary_designation" class="form-label small text-body-secondary mb-1">Designation</label>
                    <select id="primary_designation" name="primary_contact[designation_id]"
                            class="form-select form-select-sm @error('primary_contact.designation_id') is-invalid @enderror">
                        <option value="">Select…</option>
                        @foreach($designations as $id => $name)
                            <option value="{{ $id }}" @selected((string) old('primary_contact.designation_id', $primary?->designation_id) === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('primary_contact.designation_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="primary_mobile" class="form-label small text-body-secondary mb-1 required">Mobile</label>
                    <input type="text" id="primary_mobile" name="primary_contact[mobile]"
                           value="{{ old('primary_contact.mobile', $primary?->mobile) }}"
                           class="form-control form-control-sm @error('primary_contact.mobile') is-invalid @enderror"
                           required placeholder="9876543210">
                    @error('primary_contact.mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="primary_email" class="form-label small text-body-secondary mb-1">Email</label>
                    <input type="email" id="primary_email" name="primary_contact[email]"
                           value="{{ old('primary_contact.email', $primary?->email) }}"
                           class="form-control form-control-sm @error('primary_contact.email') is-invalid @enderror"
                           placeholder="contact@jobber.com">
                    @error('primary_contact.email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

    </div>
</x-ui.form-section>

{{-- ========= SECONDARY CONTACT PERSON (change request #3) ============ --}}
<x-ui.form-section title="Secondary Contact Person" icon="bi-people"
                   subtitle="Optional — add as many as needed, beyond the primary contact above.">

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

{{-- ================== M–P · LOCATION ============================= --}}
<x-ui.form-section title="Address & Location" icon="bi-geo-alt">
    <div class="form-stack">
        <x-ui.textarea name="address" label="Address" :value="$supplier?->address"
                       horizontal rows="2" placeholder="Factory / Office address" />

        <x-ui.select name="country_id" label="Country" :options="$countries"
                     :selected="$supplier?->country_id ?? $indiaCountryId" horizontal searchable
                     placeholder="Search country…"
                     data-cascade-child="#state_id" />

        <x-ui.select name="state_id" label="State" :options="$states"
                     :selected="$supplier?->state_id" horizontal searchable
                     placeholder="Search state…"
                     data-cascade-child="#city_id"
                     data-cascade-parent="#country_id"
                     :data-cascade-url="route('masters.geo.states')"
                     data-cascade-key="country_id"
                     data-cascade-empty="Select a country first" />

        <x-ui.select name="city_id" label="City" :options="$cities"
                     :selected="$supplier?->city_id" horizontal searchable
                     placeholder="Search city…"
                     data-cascade-parent="#state_id"
                     :data-cascade-url="route('masters.geo.cities')"
                     data-cascade-key="state_id"
                     data-cascade-empty="Select a state first" />

        <x-ui.field name="pincode" label="PIN Code" :value="$supplier?->pincode"
                    horizontal maxlength="10" placeholder="641601"
                    class="font-monospace" />
    </div>
</x-ui.form-section>

{{-- =================== S–T · TRADE TERMS ======================== --}}
<x-ui.form-section title="Trade Terms" icon="bi-percent">
    <div class="form-stack">
        <div class="row form-line">
            <label for="discount_percent" class="col-sm-4 col-lg-3 col-form-label fw-semibold">Discount %</label>
            <div class="col-sm-8 col-lg-9">
                <div class="input-group" style="max-width:220px">
                    <input type="number" step="0.01" min="0" max="99.99"
                           id="discount_percent" name="discount_percent"
                           value="{{ old('discount_percent', $supplier?->discount_percent) }}"
                           class="form-control @error('discount_percent') is-invalid @enderror"
                           placeholder="0.00">
                    <span class="input-group-text">%</span>
                </div>
                @error('discount_percent')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="row form-line">
            <label for="credit_days" class="col-sm-4 col-lg-3 col-form-label fw-semibold">Credit Terms</label>
            <div class="col-sm-8 col-lg-9">
                <div class="input-group" style="max-width:220px">
                    <input type="number" step="1" min="0" max="999"
                           id="credit_days" name="credit_days"
                           value="{{ old('credit_days', $supplier?->credit_days) }}"
                           class="form-control @error('credit_days') is-invalid @enderror"
                           placeholder="45">
                    <span class="input-group-text">days</span>
                </div>
                @error('credit_days')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</x-ui.form-section>

{{-- ================ JOBWORK (schema §5) ==================== --}}
<x-ui.form-section title="Jobwork Options" icon="bi-tools" id="jobwork-section">
    <div class="form-stack">
        <div class="row form-line">
            <label class="col-sm-4 col-lg-3 col-form-label fw-semibold">Material</label>
            <div class="col-sm-8 col-lg-9 pt-2">
                <div class="form-check">
                    <input type="hidden" name="we_supply_material" value="0">
                    <input type="checkbox" class="form-check-input" id="we_supply_material"
                           name="we_supply_material" value="1"
                           @checked(old('we_supply_material', $supplier?->we_supply_material ?? true))>
                    <label class="form-check-label" for="we_supply_material">
                        We supply the material — fabric, trims, labels, packing
                    </label>
                </div>
            </div>
        </div>

        <div class="row form-line">
            <label class="col-sm-4 col-lg-3 col-form-label fw-semibold">Sample</label>
            <div class="col-sm-8 col-lg-9 pt-2">
                <div class="form-check">
                    <input type="hidden" name="requires_sample_approval" value="0">
                    <input type="checkbox" class="form-check-input" id="requires_sample_approval"
                           name="requires_sample_approval" value="1"
                           @checked(old('requires_sample_approval', $supplier?->requires_sample_approval))>
                    <label class="form-check-label" for="requires_sample_approval">
                        A sample must be approved before a PO is raised
                    </label>
                </div>
            </div>
        </div>

        {{-- Change request #5, revised — "link the Client Name to the Buyer
             master instead of a Buyer Name link in a dropdown, and allow more
             than one buyer to be added here." Same multi-select shape as
             Product Category / Product above. --}}
        <x-ui.select name="buyer_ids" label="Buyer(s)" :options="$buyers"
                     :selected="$selectedBuyers" horizontal searchable multiple
                     placeholder="Search and select buyers…"
                     hint="Who this jobwork is ultimately for — one or more buyers from the Buyer master." />

        <x-ui.textarea name="client_details" label="Buyer Details" :value="$supplier?->client_details"
                       horizontal rows="3" placeholder="Optional — address, contact, or anything else worth recording" />
    </div>
</x-ui.form-section>

<x-ui.form-section title="Delivery" icon="bi-box-arrow-right">
    <div class="form-stack">
        <x-ui.select name="default_delivery_mode" label="Default Delivery Mode" required horizontal
                     :options="$deliveryModes"
                     :selected="$supplier?->default_delivery_mode ?? 'direct_to_port'"
                     :placeholder="false" />
    </div>
</x-ui.form-section>

{{-- =================== U–W · BANK DETAILS ======================= --}}
<x-ui.form-section title="Bank Details" icon="bi-bank">
    <div class="form-stack">
        <x-ui.field name="bank_name" label="Bank Name" :value="$supplier?->bank_name"
                    horizontal maxlength="120" placeholder="HDFC Bank" />

        <x-ui.field name="account_number" label="Account Number" :value="$supplier?->account_number"
                    horizontal maxlength="40" placeholder="50100123456789"
                    class="font-monospace" />

        <x-ui.field name="ifsc_code" label="IFSC Code" :value="$supplier?->ifsc_code"
                    horizontal maxlength="11" placeholder="HDFC0001234"
                    class="font-monospace text-uppercase" />
    </div>
</x-ui.form-section>

{{-- ====================== X–Y · AGENT =========================== --}}
<x-ui.form-section title="Agent" icon="bi-person-badge">
    <div class="form-stack">
        <x-ui.select name="agent_id" label="Agent" :options="$agents"
                     :selected="$supplier?->agent_id" horizontal searchable
                     placeholder="Search agent…"
                     data-cascade-parent="#party_type"
                     :data-cascade-url="route('masters.jobbers.agents')"
                     data-cascade-key="party_type" />

        {{-- Was missing entirely on this form — the Supplier form has had
             it all along. Without it there was no way to enter this
             jobber's negotiated rate, only to pick the agent. Feeds change
             request #6's commission calculation on the Purchase Order. --}}
        <div class="row form-line">
            <label for="agent_commission_value" class="col-sm-4 col-lg-3 col-form-label fw-semibold">
                Agent Commission
            </label>
            <div class="col-sm-8 col-lg-9">
                <div class="input-group">
                    <input type="number" step="0.0001" min="0"
                           id="agent_commission_value" name="agent_commission_value"
                           value="{{ old('agent_commission_value', $supplier?->agent_commission_value) }}"
                           class="form-control @error('agent_commission_value') is-invalid @enderror"
                           placeholder="0.0000">
                    <select name="agent_commission_type" style="max-width:140px"
                            class="form-select @error('agent_commission_type') is-invalid @enderror">
                        @foreach(['percent' => '% Percent', 'amount' => 'Fixed amount / piece'] as $value => $text)
                            <option value="{{ $value }}"
                                @selected(old('agent_commission_type', $supplier?->agent_commission_type) === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
                @error('agent_commission_value')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @error('agent_commission_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">
                    "Fixed amount / piece" feeds the per-piece × quantity commission shown on this jobber's Purchase Orders.
                </div>
            </div>
        </div>
    </div>
</x-ui.form-section>

{{-- ====================== Z, AA · OTHER ========================= --}}
<x-ui.form-section title="Other Details" icon="bi-card-text">
    <div class="form-stack">
        <x-ui.select name="status" label="Status" required horizontal
                     :options="['active' => 'Active', 'inactive' => 'Inactive']"
                     :selected="$supplier?->status ?? 'active'"
                     :placeholder="false" />

        <x-ui.textarea name="remarks" label="Remarks" :value="$supplier?->remarks"
                       horizontal rows="2" placeholder="Optional notes" />

        <x-ui.textarea name="comments" label="Comments" :value="$supplier?->comments"
                       horizontal rows="2" placeholder="Optional comments" />
    </div>
</x-ui.form-section>

<div class="form-actions">
    <button type="submit" class="btn btn-primary px-4">
        <i class="bi bi-check-lg me-1"></i>{{ $supplier ? 'Update' : 'Save' }} Jobber
    </button>
    <a href="{{ route('masters.jobbers.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    /* ------------------------------------------------------------------ *
     * Jobber Type — reveal the GST Number field live when a registered type
     * is chosen. This form had no such wiring either; the field only ever
     * appeared after a full page reload. Same behaviour as the Supplier
     * form's applyGst(). A type quick-added through the field's create-url
     * is not in this dict (it does not exist yet when the page rendered) and
     * so is treated as unregistered, matching the server-side default in
     * SupplierController::storeSupplierType().
     * ------------------------------------------------------------------ */
    const registeredTypes = @json($supplierTypeFlags);
    const supplierType    = document.getElementById('supplier_type_id');
    const gstRow          = document.getElementById('gst-row');
    const gstInput        = document.getElementById('gst_number');

    function applyGst() {
        const registered = registeredTypes[supplierType.value] === true;

        gstRow.classList.toggle('d-none', ! registered);

        if (! registered && ! gstInput.classList.contains('is-invalid')) {
            gstInput.value = '';
        }
    }

    supplierType?.addEventListener('change', applyGst);

    /* ------------------------------------------------------------------ *
     * MSME — reveal the Registration No field live when the checkbox is
     * ticked. This form had no such wiring at all; the field only ever
     * appeared after a full page reload. Same behaviour as the Supplier
     * form's applyMsme().
     * ------------------------------------------------------------------ */
    const msmeToggle = document.getElementById('is_msme');
    const msmeRow     = document.getElementById('msme-row');

    function applyMsme() {
        msmeRow.classList.toggle('d-none', ! msmeToggle.checked);
    }

    msmeToggle?.addEventListener('change', applyMsme);

    /* ------------------------------------------------------------------ *
     * Other Contact Persons (change request #3, revised — no limit).
     * Repeatable rows, same shape as the Supplier form's own.
     * ------------------------------------------------------------------ */
    const rows     = document.getElementById('contact-rows');
    const template = document.getElementById('contact-row-template');
    const addBtn   = document.getElementById('add-contact');

    if (! rows || ! template || ! addBtn) return;

    function reindex() {
        rows.querySelectorAll('[data-contact-row]').forEach(function (row, index) {
            row.querySelectorAll('[name^="contacts"]').forEach(function (field) {
                field.name = field.name.replace(/contacts\[\d+\]/, 'contacts[' + index + ']');
            });
        });
    }

    rows.addEventListener('click', function (event) {
        const remove = event.target.closest('.js-remove-contact');
        if (! remove) return;

        const all = rows.querySelectorAll('[data-contact-row]');

        if (all.length === 1) {
            all[0].querySelectorAll('input').forEach(function (field) { field.value = ''; });

            const select = all[0].querySelector('select');
            select?.tomselect ? select.tomselect.clear(true) : (select.value = '');
        } else {
            remove.closest('[data-contact-row]').remove();
        }

        reindex();
    });

    addBtn.addEventListener('click', function () {
        const index = rows.querySelectorAll('[data-contact-row]').length;
        const row = template.content.cloneNode(true).querySelector('tr');

        row.querySelectorAll('[name*="__INDEX__"]').forEach(function (field) {
            field.name = field.name.replace('__INDEX__', index);
        });

        rows.appendChild(row);

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

        rows.querySelectorAll('[data-contact-row]').forEach(function (row, index) {
            if (index === 0) {
                row.querySelectorAll('input').forEach(function (field) { field.value = ''; });

                const select = row.querySelector('select');
                select?.tomselect ? select.tomselect.clear(true) : (select && (select.value = ''));
            } else {
                row.remove();
            }
        });
    });
});
</script>
@endpush
