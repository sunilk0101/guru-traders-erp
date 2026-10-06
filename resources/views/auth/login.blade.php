<x-guest-layout>
    <div class="mb-4">
        <h2 class="auth-brand">Welcome Back</h2>
        <p class="text-muted">Please enter your details to sign in.</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success rounded-3">
            {{ session('status') }}
        </div>
    @endif

    <form action="{{ route('login') }}" method="POST">
        @csrf

        <!-- Email -->
        <div class="mb-4">
            <label class="form-label fw-bold text-secondary small">Email Address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="name@example.com" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>
            @error('email')
                <div class="text-danger small mt-1 d-block">{{ $message }}</div>
            @enderror
        </div>

        <!-- Password -->
        <div class="mb-4">
            <label class="form-label fw-bold text-secondary small">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" placeholder="Enter your password" required autocomplete="current-password">
                <button class="input-group-text toggle-password" type="button" data-target="#password" aria-label="Show password" aria-pressed="false">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            @error('password')
                <div class="text-danger small mt-1 d-block">{{ $message }}</div>
            @enderror
        </div>

        <!-- Forgot Password / Remember Me -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                <label class="form-check-label text-muted small" for="remember">Remember Me</label>
            </div>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-decoration-none small text-primary fw-bold">Forgot Password?</a>
            @endif
        </div>

        <!-- Submit -->
        <button type="submit" class="btn btn-primary btn-modern w-100 mb-4">
            Sign In
        </button>
    </form>
</x-guest-layout>
