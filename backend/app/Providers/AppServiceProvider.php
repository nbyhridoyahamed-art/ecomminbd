<?php

namespace App\Providers;

use App\Policies\RolePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Role lives in Spatie's own namespace, so Laravel's policy
        // auto-discovery (which guesses from the MODEL's namespace, not
        // App\Policies) never finds RolePolicy on its own — it must be
        // registered explicitly.
        Gate::policy(Role::class, RolePolicy::class);
    }
}
