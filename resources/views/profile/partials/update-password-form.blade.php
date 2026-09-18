{{-- M-07: heading removed (card-header already says "Update Password");
     the rest of this form was already using proper Bootstrap input-groups
     with the app's shared .toggle-password behaviour (see app.js), it just
     needed the leftover Tailwind header/toast stripped out. --}}
<p class="text-body-secondary small mb-3">Ensure your account is using a long, random password to stay secure.</p>

<form method="post" action="{{ route('password.update') }}">
    @csrf
    @method('put')

    <div class="mb-3">
        <x-input-label for="update_password_current_password" :value="__('Current Password')" />
        <div class="input-group mt-1">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input id="update_password_current_password" name="current_password" type="password" class="form-control border-end-0" autocomplete="current-password" />
            <button class="input-group-text toggle-password" type="button" aria-label="Toggle password visibility" style="cursor: pointer; border-left: none;">
                <i class="bi bi-eye"></i>
            </button>
        </div>
        <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
    </div>

    <div class="mb-3">
        <x-input-label for="update_password_password" :value="__('New Password')" />
        <div class="input-group mt-1">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input id="update_password_password" name="password" type="password" class="form-control border-end-0" autocomplete="new-password" />
            <button class="input-group-text toggle-password" type="button" aria-label="Toggle password visibility" style="cursor: pointer; border-left: none;">
                <i class="bi bi-eye"></i>
            </button>
        </div>
        <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
    </div>

    <div class="mb-3">
        <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
        <div class="input-group mt-1">
            <span class="input-group-text"><i class="bi bi-check-circle"></i></span>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="form-control border-end-0" autocomplete="new-password" />
            <button class="input-group-text toggle-password" type="button" aria-label="Toggle password visibility" style="cursor: pointer; border-left: none;">
                <i class="bi bi-eye"></i>
            </button>
        </div>
        <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
    </div>

    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
</form>
