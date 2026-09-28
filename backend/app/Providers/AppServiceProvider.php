<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
use App\Policies\RolePolicy;
use App\Services\Sms\LogSmsGateway;
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
        // Phase 19: the one place a real BD SMS provider replaces the log
        // mock — every caller depends on SmsGateway, never LogSmsGateway
        // directly.
        $this->app->bind(SmsGateway::class, LogSmsGateway::class);
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
