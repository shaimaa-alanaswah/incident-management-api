<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Default binding so BelongsToTenant's global scope resolves to "no tenant"
        // (unscoped queries) until ResolveTenant middleware overwrites this with
        // app()->instance('current_tenant', $tenant) — required for the initial
        // TenantApiKey lookup during authentication, before any tenant is known.
        $this->app->bind('current_tenant', fn () => null);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
