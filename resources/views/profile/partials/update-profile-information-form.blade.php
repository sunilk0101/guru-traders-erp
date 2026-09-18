{{-- M-07: heading removed — the card-header in profile/edit.blade.php
     already says "Profile Information"; repeating it here was the
     literal double-heading the audit flagged. Tailwind utility classes
     (text-gray-900, space-y-6, ...) replaced with this app's own
     <x-ui.field> / Bootstrap conventions, matching every other form. --}}
<p class="text-body-secondary small mb-3">Update your account's profile information and email address.</p>

<form id="send-verification" method="post" action="{{ route('verification.send') }}">
    @csrf
</form>

<form method="post" action="{{ route('profile.update') }}">
    @csrf
    @method('patch')

    <x-ui.field name="name" label="Name" :value="old('name', $user->name)" required autofocus autocomplete="name" col="col-12" />

    <x-ui.field name="email" type="email" label="Email" :value="old('email', $user->email)" required autocomplete="username" col="col-12" />

    @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
        <div class="mb-3">
            <p class="small text-body-secondary mb-1">
                {{ __('Your email address is unverified.') }}
                <button form="send-verification" class="btn btn-link btn-sm p-0 align-baseline">
                    {{ __('Click here to re-send the verification email.') }}
                </button>
            </p>

            @if (session('status') === 'verification-link-sent')
                <p class="small text-success mb-0">
                    {{ __('A new verification link has been sent to your email address.') }}
                </p>
            @endif
        </div>
    @endif

    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
</form>
