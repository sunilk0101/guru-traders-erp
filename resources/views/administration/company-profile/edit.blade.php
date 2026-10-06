<x-app-layout>
    <x-slot name="header">Company Profile</x-slot>

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <x-ui.card title="Company Profile" variant="primary">
                <p class="text-body-secondary small mb-4">
                    Our own company's details — this is what prints on export invoices, bank documents and
                    every other export paperwork. GSTIN, IEC and bank details are optional here but required
                    before you generate a real Export Invoice.
                </p>

                <form action="{{ route('user-management.company-profile.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="company_name" class="form-label required">Company Name</label>
                            <input type="text" name="company_name" id="company_name"
                                   value="{{ old('company_name', $profile->company_name) }}"
                                   class="form-control @error('company_name') is-invalid @enderror" required>
                            @error('company_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- M-10: was col-md-4 with the thumbnail and file input side by
                             side in a plain d-flex — file inputs don't shrink to fit a flex
                             child the way other controls do, so at a normal 1024px width
                             (col-md-4 inside this col-lg-9 card is well under 300px) the row
                             ran wider than the card instead of wrapping. flex-wrap plus
                             min-width:0 on the input lets it shrink or drop to its own line
                             instead of forcing the card open. --}}
                        <div class="col-md-4">
                            <label class="form-label">Logo</label>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                @if($profile->hasLogo())
                                    <img src="{{ $profile->logoUrl() }}" alt="Logo" style="height:36px" class="border rounded p-1">
                                @endif
                                <input type="file" name="logo" accept="image/*" style="min-width:0"
                                       class="form-control form-control-sm flex-grow-1 @error('logo') is-invalid @enderror">
                            </div>
                            @error('logo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label for="tagline" class="form-label">Tagline</label>
                            <input type="text" name="tagline" id="tagline"
                                   value="{{ old('tagline', $profile->tagline) }}"
                                   class="form-control @error('tagline') is-invalid @enderror"
                                   placeholder="e.g. An Indian Govt. Recognised Export House">
                            @error('tagline') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label for="address" class="form-label">Address</label>
                            <textarea name="address" id="address" rows="3"
                                      class="form-control @error('address') is-invalid @enderror">{{ old('address', $profile->address) }}</textarea>
                            @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" name="phone" id="phone"
                                   value="{{ old('phone', $profile->phone) }}"
                                   class="form-control @error('phone') is-invalid @enderror">
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email"
                                   value="{{ old('email', $profile->email) }}"
                                   class="form-control @error('email') is-invalid @enderror">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <h6 class="fw-semibold mt-4 mb-2">Statutory Details</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="gstin" class="form-label">GSTIN</label>
                            <input type="text" name="gstin" id="gstin"
                                   value="{{ old('gstin', $profile->gstin) }}"
                                   class="form-control @error('gstin') is-invalid @enderror"
                                   placeholder="Required before generating an Export Invoice">
                            @error('gstin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="iec_code" class="form-label">IEC Code</label>
                            <input type="text" name="iec_code" id="iec_code"
                                   value="{{ old('iec_code', $profile->iec_code) }}"
                                   class="form-control @error('iec_code') is-invalid @enderror"
                                   placeholder="Required before generating an Export Invoice">
                            @error('iec_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <h6 class="fw-semibold mt-4 mb-2">Bank Details <span class="text-body-secondary fw-normal">(for the "For Bank" invoice variant)</span></h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="bank_name" class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" id="bank_name"
                                   value="{{ old('bank_name', $profile->bank_name) }}"
                                   class="form-control @error('bank_name') is-invalid @enderror">
                            @error('bank_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="bank_account_number" class="form-label">Account Number</label>
                            <input type="text" name="bank_account_number" id="bank_account_number"
                                   value="{{ old('bank_account_number', $profile->bank_account_number) }}"
                                   class="form-control @error('bank_account_number') is-invalid @enderror">
                            @error('bank_account_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="bank_ifsc" class="form-label">IFSC Code</label>
                            <input type="text" name="bank_ifsc" id="bank_ifsc"
                                   value="{{ old('bank_ifsc', $profile->bank_ifsc) }}"
                                   class="form-control @error('bank_ifsc') is-invalid @enderror">
                            @error('bank_ifsc') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="bank_swift" class="form-label">SWIFT Code</label>
                            <input type="text" name="bank_swift" id="bank_swift"
                                   value="{{ old('bank_swift', $profile->bank_swift) }}"
                                   class="form-control @error('bank_swift') is-invalid @enderror">
                            @error('bank_swift') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <h6 class="fw-semibold mt-4 mb-2">Signatory <span class="text-body-secondary fw-normal">(printed on the invoice signature block)</span></h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="signatory_name" class="form-label">Name</label>
                            <input type="text" name="signatory_name" id="signatory_name"
                                   value="{{ old('signatory_name', $profile->signatory_name) }}"
                                   class="form-control @error('signatory_name') is-invalid @enderror">
                            @error('signatory_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="signatory_designation" class="form-label">Designation</label>
                            <input type="text" name="signatory_designation" id="signatory_designation"
                                   value="{{ old('signatory_designation', $profile->signatory_designation) }}"
                                   class="form-control @error('signatory_designation') is-invalid @enderror"
                                   placeholder="e.g. Partner, Director">
                            @error('signatory_designation') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Save Company Profile
                        </button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
