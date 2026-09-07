<?php

namespace App\Providers;

use App\Support\PermissionRegistry;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance for the request — every call re-reads the same config array.
        $this->app->singleton(PermissionRegistry::class);
    }

    public function boot(): void
    {
        // App uses Bootstrap (Tailwind utilities are not compiled). Default
        // Tailwind pagination ships bare SVGs with w-5/h-5 classes — without
        // Tailwind those icons expand to fill the page.
        Paginator::useBootstrapFive();

        $appUrl = (string) config('app.url');
        if ($appUrl !== '') {
            URL::forceRootUrl($appUrl);
            if (str_starts_with($appUrl, 'https://')) {
                URL::forceScheme('https');
            }
        }

        // Super Admin bypasses every permission check.
        //
        // Returning null (not false) for everyone else is deliberate: it means
        // "no opinion", so Spatie's own check still runs. Returning false here
        // would deny every non-Super-Admin outright.
        Gate::before(function ($user, string $ability): ?bool {
            return $user->hasRole('Super Admin') ? true : null;
        });
    }
}
