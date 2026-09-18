<x-guest-layout>
    <div class="login-box-msg mb-4 text-muted">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </div>

    <!-- Session Status -->
    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div class="input-group mb-3">
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="Email" value="{{ old('email') }}" required autofocus autocomplete="username">
            <div class="input-group-text"><span class="bi bi-envelope"></span></div>
            @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="row">
            <div class="col-12">
                <button type="submit" class="btn btn-primary w-100">
                    {{ __('Email Password Reset Link') }}
                </button>
            </div>
        </div>
        
    </form>
    
    <p class="mt-3 mb-1">
        <a href="{{ route('login') }}">Login</a>
    </p>

</x-guest-layout>
