<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        <ul class="navbar-nav align-items-center flex-grow-1 min-w-0">
            <li class="nav-item flex-grow-1 min-w-0">
                <nav class="erp-breadcrumb" aria-label="Breadcrumb">
                    @unless (request()->routeIs('dashboard'))
                        <a href="{{ route('dashboard') }}" class="erp-breadcrumb-link">
                            <i class="bi bi-house-door me-1"></i>Home
                        </a>
                        <i class="bi bi-chevron-right erp-breadcrumb-sep" aria-hidden="true"></i>
                        <span class="erp-breadcrumb-current">{{ $header ?? 'Page' }}</span>
                    @else
                        <span class="erp-breadcrumb-current">
                            <i class="bi bi-house-door me-1"></i>Home
                        </span>
                    @endunless
                </nav>
            </li>
        </ul>

        <ul class="navbar-nav ms-auto align-items-center gap-2">
            <li class="nav-item">
                <button type="button"
                        class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-2 px-3 rounded-pill"
                        id="themeToggleBtn"
                        onclick="toggleGuruTheme()">
                    <i class="bi bi-sun-fill text-warning" id="themeIconSun"></i>
                    <i class="bi bi-moon-stars-fill text-info d-none" id="themeIconMoon"></i>
                    <span id="themeLabelText" class="small fw-bold">Theme</span>
                </button>
            </li>

            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=2563eb&color=fff"
                         class="user-image rounded-circle shadow-sm" alt="User Image"
                         style="width: 32px; height: 32px;">
                    <span class="d-none d-md-inline fw-semibold">{{ Auth::user()->name }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end shadow">
                    <li class="user-header text-bg-primary">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=fff&color=2563eb"
                             class="rounded-circle shadow" alt="User Image">
                        <p>
                            {{ Auth::user()->name }}
                            <small>Member since {{ Auth::user()->created_at->format('M. Y') }}</small>
                        </p>
                    </li>
                    <li class="user-footer">
                        <a href="{{ route('profile.edit') }}" class="btn btn-default btn-flat">Profile</a>
                        <form method="POST" action="{{ route('logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-default btn-flat float-end">Sign out</button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>

<script>
    function updateGuruThemeUI(theme) {
        const sunIcon = document.getElementById('themeIconSun');
        const moonIcon = document.getElementById('themeIconMoon');
        const labelText = document.getElementById('themeLabelText');
        if (theme === 'dark') {
            sunIcon?.classList.add('d-none');
            moonIcon?.classList.remove('d-none');
            if (labelText) labelText.textContent = 'Dark';
        } else {
            sunIcon?.classList.remove('d-none');
            moonIcon?.classList.add('d-none');
            if (labelText) labelText.textContent = 'Light';
        }
    }

    window.toggleGuruTheme = function () {
        const currentTheme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', currentTheme);
        localStorage.setItem('guru_theme', currentTheme);
        updateGuruThemeUI(currentTheme);
    };

    document.addEventListener('DOMContentLoaded', function () {
        const savedTheme = localStorage.getItem('guru_theme') || 'dark';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
        updateGuruThemeUI(savedTheme);
    });
</script>
