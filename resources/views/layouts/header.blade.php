<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        <ul class="navbar-nav align-items-center flex-grow-1 min-w-0">
            {{-- Mobile menu button (C-05). Below lg, .app-sidebar is an off-canvas
                 drawer with no other way to open it — its own collapse button lives
                 inside the sidebar itself, which is exactly what's hidden. This one
                 lives in the always-visible header instead. Wired up in app.blade.php. --}}
            <li class="nav-item d-lg-none">
                <button type="button" class="nav-link px-2" id="mobileSidebarToggle"
                        aria-label="Open menu" title="Open menu">
                    <i class="bi bi-list fs-4"></i>
                </button>
            </li>
            <li class="nav-item flex-grow-1 min-w-0">
                @php
                    // M-08: was always "Home > $header", so a Create/Edit/Detail
                    // page (e.g. "Home > Add Category") skipped the list level
                    // entirely — no one-click way back to it except Cancel/Back
                    // buttons. Every module here follows the same Route::resource
                    // naming convention (`{prefix}.{resource}.{action}`, with the
                    // list at `{prefix}.{resource}.index`), so the middle crumb
                    // can be derived from the current route name instead of
                    // threading a new variable through 80+ views by hand.
                    $breadcrumbParents = [
                        'masters.categories'         => 'Categories',
                        'masters.formats'             => 'Order Formats',
                        'masters.products'            => 'Products',
                        'masters.buyers'               => 'Buyers',
                        'masters.suppliers'            => 'Suppliers',
                        'masters.jobbers'               => 'Jobbers',
                        'masters.agents'                => 'Agents',
                        'masters.fob-values'            => 'FOB Values',
                        'masters.trim-accessories'      => 'Trim / Accessories',
                        'masters.markups'               => 'Markups',
                        'sales.inquiries'               => 'Inquiries',
                        'sales.order-confirmations'     => 'Order Confirmations',
                        'procurement.purchase-orders'   => 'Purchase Orders',
                        'procurement.inward-entries'    => 'Goods Inward',
                        'export.documents'              => 'Export Documents',
                        'export.packing'                => 'Packing Desk',
                        'user-management.users'         => 'Users',
                        'user-management.roles'         => 'Roles',
                    ];

                    $breadcrumbParent = null;
                    $currentRouteName = request()->route()?->getName();

                    if ($currentRouteName) {
                        foreach ($breadcrumbParents as $prefix => $label) {
                            if ($currentRouteName === "{$prefix}.index") {
                                break; // already on the list page — no parent crumb needed
                            }
                            if (str_starts_with($currentRouteName, "{$prefix}.") && \Illuminate\Support\Facades\Route::has("{$prefix}.index")) {
                                $breadcrumbParent = ['label' => $label, 'url' => route("{$prefix}.index")];
                                break;
                            }
                        }
                    }
                @endphp
                <nav class="erp-breadcrumb" aria-label="Breadcrumb">
                    @unless (request()->routeIs('dashboard'))
                        <a href="{{ route('dashboard') }}" class="erp-breadcrumb-link">
                            <i class="bi bi-house-door me-1"></i>Home
                        </a>
                        <i class="bi bi-chevron-right erp-breadcrumb-sep" aria-hidden="true"></i>
                        @if($breadcrumbParent)
                            <a href="{{ $breadcrumbParent['url'] }}" class="erp-breadcrumb-link">{{ $breadcrumbParent['label'] }}</a>
                            <i class="bi bi-chevron-right erp-breadcrumb-sep" aria-hidden="true"></i>
                        @endif
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
    // M-14: was labelled with the CURRENT theme ('Dark' while dark mode was
    // already active), which reads as a status label, not a button — a user
    // has no way to tell from the text alone which way one click will go.
    // Labelled with the action instead ("Switch to light"), matching how
    // every other button in this app describes what clicking it will do.
    function updateGuruThemeUI(theme) {
        const sunIcon = document.getElementById('themeIconSun');
        const moonIcon = document.getElementById('themeIconMoon');
        const labelText = document.getElementById('themeLabelText');
        if (theme === 'dark') {
            sunIcon?.classList.add('d-none');
            moonIcon?.classList.remove('d-none');
            if (labelText) labelText.textContent = 'Switch to light';
        } else {
            sunIcon?.classList.remove('d-none');
            moonIcon?.classList.add('d-none');
            if (labelText) labelText.textContent = 'Switch to dark';
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
