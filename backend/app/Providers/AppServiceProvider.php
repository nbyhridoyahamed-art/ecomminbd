<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
use App\Models\Category;
use App\Models\HomepageBlock;
use App\Observers\CategoryObserver;
use App\Observers\HomepageBlockObserver;
use App\Policies\RolePolicy;
use App\Services\Sms\LogSmsGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        // Phase 21: the named limiter `throttle:api` (wired globally in
        // bootstrap/app.php via throttleApi()) resolves to. Every route
        // already under a tighter per-route throttle (login, checkout,
        // etc.) stays governed by that stricter limit; this is only the
        // backstop for the rest of the API. Disabled under the test suite:
        // PHPUnit runs 400+ tests in one process against the array cache
        // driver, all through the in-process test client's shared
        // "127.0.0.1" — without this they'd all share one bucket and start
        // failing with 429s well before the suite finishes. A test that
        // wants to exercise the real limit re-registers it with its own
        // tiny value, which overrides this for that test only.
        RateLimiter::for('api', function (Request $request) {
            if (app()->runningUnitTests()) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Phase 22: keeps the storefront's cached category tree
        // (Category::storefrontCacheKey()) from ever going stale after an
        // admin edit — every write here already goes through a model
        // instance (create()/update()/delete()), never a raw query-builder
        // bulk update, so observer events are a reliable place to hook.
        Category::observe(CategoryObserver::class);

        // Same reasoning for the cached storefront homepage — covers
        // HomepageBlockController's store/update/destroy/publish/unpublish/
        // duplicate/restore actions and the scheduled-publish console
        // command, all of which mutate through a model instance.
        // reorder()'s bulk per-ID query-builder update is the one write
        // path this can't see, and invalidates explicitly in the
        // controller instead.
        HomepageBlock::observe(HomepageBlockObserver::class);
    }
}
