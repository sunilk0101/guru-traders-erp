{{-- M-07: was the raw, unstyled Laravel Breeze default — different
     typography from the rest of the app, each card repeating its own
     heading on top of the card-header's, and a Delete Account button that
     both looked out of place and (isProtected() aside) had no business
     being self-service on an internal ERP tool. Restyled to this app's
     own card/form conventions and the Delete Account card dropped
     entirely — see the note below. --}}
<x-app-layout>
    <x-slot name="header">
        {{ __('Profile') }}
    </x-slot>

    <div class="row">
        <div class="col-md-6">
            <div class="card card-primary card-outline mb-4">
                <div class="card-header">
                    <h3 class="card-title">Profile Information</h3>
                </div>
                <div class="card-body">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-success card-outline mb-4">
                <div class="card-header">
                    <h3 class="card-title">Update Password</h3>
                </div>
                <div class="card-body">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>
    </div>

    {{-- M-07: "Delete Account" removed from this self-service page —
         deleting your own login while using it is not a decision any user
         should be one click away from, the Super Admin's account is
         already protected server-side (see User::isProtected() /
         ProfileController::destroy()) but was still shown the button here,
         and account removal belongs in User Management like every other
         user-lifecycle action, not duplicated here. --}}
</x-app-layout>
