<x-app-layout>
    <x-slot name="header">Buyer Details</x-slot>

    <x-ui.card title="{{ $buyer->display_code }} — {{ $buyer->company_name }}" variant="primary">
        <x-slot name="actions">
            @can('buyer.edit')
                <a href="{{ route('masters.buyers.edit', $buyer) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan
            <a href="{{ route('masters.buyers.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </x-slot>

        <div class="row g-4">
            <div class="col-lg-7">
                <dl class="row mb-0">
                    <dt class="col-sm-5 text-body-secondary fw-normal">Display Code</dt>
                    <dd class="col-sm-7"><span class="badge text-bg-light border font-monospace">{{ $buyer->display_code }}</span></dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Company Name</dt>
                    <dd class="col-sm-7 fw-semibold">{{ $buyer->company_name }}</dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Name on Export Invoice</dt>
                    <dd class="col-sm-7">{{ $buyer->name_on_export_invoice ?: '—' }}</dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Category of Items</dt>
                    <dd class="col-sm-7">
                        @forelse($buyer->categories as $category)
                            <span class="badge text-bg-light border me-1 mb-1">{{ $category->name }}</span>
                        @empty
                            —
                        @endforelse
                    </dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Status</dt>
                    <dd class="col-sm-7"><x-ui.status-badge :status="$buyer->status === 'active'" /></dd>

                    <dt class="col-sm-12 pt-3"><hr class="my-2"></dt>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Contact Person</dt>
                    <dd class="col-sm-7">{{ $buyer->contact_person ?: '—' }}</dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Designation</dt>
                    <dd class="col-sm-7">{{ $buyer->contactDesignation?->name ?: '—' }}</dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Email</dt>
                    <dd class="col-sm-7">
                        @if($buyer->email)
                            <a href="mailto:{{ $buyer->email }}">{{ $buyer->email }}</a>
                        @else
                            —
                        @endif
                    </dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Mobile</dt>
                    <dd class="col-sm-7">{{ $buyer->mobile ?: '—' }}</dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">GST / VAT No.</dt>
                    <dd class="col-sm-7 font-monospace">{{ $buyer->gst_vat_no ?: '—' }}</dd>

                    <dt class="col-sm-12 pt-3"><hr class="my-2"></dt>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Address</dt>
                    <dd class="col-sm-7">{{ $buyer->address ?: '—' }}</dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">City / State</dt>
                    <dd class="col-sm-7">{{ collect([$buyer->city?->name, $buyer->state?->name])->filter()->implode(', ') ?: '—' }}</dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Country</dt>
                    <dd class="col-sm-7">{{ $buyer->country?->name ?: '—' }}</dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">PIN / ZIP Code</dt>
                    <dd class="col-sm-7">{{ $buyer->pincode ?: '—' }}</dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Destination Port</dt>
                    <dd class="col-sm-7">{{ $buyer->port?->label ?: '—' }}</dd>

                    <dt class="col-sm-12 pt-3"><hr class="my-2"></dt>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Agent</dt>
                    <dd class="col-sm-7">{{ $buyer->agent?->label ?: '—' }}</dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Agent Commission</dt>
                    <dd class="col-sm-7">{{ $buyer->agent_commission_label ?: '—' }}</dd>

                    <dt class="col-sm-12 pt-3"><hr class="my-2"></dt>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Payment Terms</dt>
                    <dd class="col-sm-7">
                        {{ $buyer->paymentTerm?->name ?: '—' }}
                        @if(filled($buyer->advance_percent) && filled($buyer->sight_percent))
                            <div class="small text-body-secondary">
                                {{ rtrim(rtrim(number_format((float) $buyer->advance_percent, 2), '0'), '.') }}% advance
                                + {{ rtrim(rtrim(number_format((float) $buyer->sight_percent, 2), '0'), '.') }}% at sight
                            </div>
                        @endif
                    </dd>

                    {{-- Default first, then the full accepted set. The default is
                         what a new order pre-selects; the set is what is allowed. --}}
                    <dt class="col-sm-5 text-body-secondary fw-normal">Inco Terms</dt>
                    <dd class="col-sm-7">
                        {{ $buyer->incoterm?->label ?: '—' }}
                        @if($buyer->incoterms->count() > 1)
                            <div class="small text-body-secondary">
                                Also accepts:
                                {{ $buyer->incoterms->where('id', '!=', $buyer->incoterm_id)->pluck('code')->implode(', ') }}
                            </div>
                        @endif
                    </dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Shipment Methods</dt>
                    <dd class="col-sm-7">
                        {{ $buyer->shipmentMethod?->name ?: '—' }}
                        @if($buyer->shipmentMethods->count() > 1)
                            <div class="small text-body-secondary">
                                Also accepts:
                                {{ $buyer->shipmentMethods->where('id', '!=', $buyer->shipment_method_id)->pluck('name')->implode(', ') }}
                            </div>
                        @endif
                    </dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Currency of Payment</dt>
                    <dd class="col-sm-7">
                        {{ $buyer->currency?->label ?: '—' }}
                        @if($buyer->currencies->count() > 1)
                            <div class="small text-body-secondary">
                                Also accepts:
                                {{ $buyer->currencies->where('id', '!=', $buyer->currency_id)->pluck('iso_code')->implode(', ') }}
                            </div>
                        @endif
                    </dd>

                    <dt class="col-sm-12 pt-3"><hr class="my-2"></dt>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Bank Name</dt>
                    <dd class="col-sm-7">{{ $buyer->bank_name ?: '—' }}</dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Account Number</dt>
                    <dd class="col-sm-7 font-monospace">{{ $buyer->account_number ?: '—' }}</dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">SWIFT Code</dt>
                    <dd class="col-sm-7 font-monospace">{{ $buyer->swift_code ?: '—' }}</dd>

                    <dt class="col-sm-12 pt-3"><hr class="my-2"></dt>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Remarks</dt>
                    <dd class="col-sm-7">{{ $buyer->remarks ?: '—' }}</dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Comments</dt>
                    <dd class="col-sm-7">{{ $buyer->comments ?: '—' }}</dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Created</dt>
                    <dd class="col-sm-7 text-body-secondary small">
                        {{ $buyer->created_at?->format('d M Y, H:i') }}
                        @if($buyer->creator) by {{ $buyer->creator->name }} @endif
                    </dd>

                    <dt class="col-sm-5 text-body-secondary fw-normal">Last updated</dt>
                    <dd class="col-sm-7 text-body-secondary small">
                        {{ $buyer->updated_at?->format('d M Y, H:i') }}
                        @if($buyer->updater) by {{ $buyer->updater->name }} @endif
                    </dd>
                </dl>
            </div>

            {{-- Sheet col X. Shown as it prints, not as a field list — the point
                 of the marks is the shape they come out in. --}}
            <div class="col-lg-5">
                <div class="carton-preview">
                    <div class="carton-preview-head">Carton marking details</div>
                    @if($buyer->cartonMarkings->isEmpty())
                        <pre class="carton-preview-body is-empty mb-0">No carton marks recorded.</pre>
                    @else
                        <pre class="carton-preview-body mb-0">{{ $buyer->carton_marking_preview ?: 'All lines are blank.' }}</pre>
                    @endif
                </div>

                @if($buyer->cartonMarkings->isNotEmpty())
                    <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:50px">#</th>
                                    <th>Label</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($buyer->cartonMarkings as $line)
                                    <tr>
                                        <td class="text-body-secondary">{{ $line->line_no }}</td>
                                        <td class="small text-body-secondary text-uppercase">{{ $line->label }}</td>
                                        <td class="font-monospace small">{{ $line->value ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </x-ui.card>
</x-app-layout>
