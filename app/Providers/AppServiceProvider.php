<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use App\Policies\NotificationPolicy;
use App\Services\NotificationCenterService;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
     *
     * Phase 3 rate limiters. Login itself is throttled manually inside
     * `App\Http\Requests\Auth\LoginRequest::ensureIsNotRateLimited()`
     * (5 attempts per email+IP, Laravel's standard Breeze-style
     * approach) using this same `RateLimiter` facade, so no route-level
     * `throttle:login` middleware is layered on top of it. The limiters
     * below back the other auth-adjacent endpoints. All limits are
     * generous enough not to get in the way of normal local development
     * -- see README.md "Rate limiting" for the documented values.
     */
    public function boot(): void
    {
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip().'|'.strtolower((string) $request->input('email')));
        });

        RateLimiter::for('verification-resend', function (Request $request) {
            return Limit::perMinute(6)->by(optional($request->user())->id ?: $request->ip());
        });

        // Phase 10: DatabaseNotification lives in the framework namespace,
        // so policy auto-discovery cannot find it -- register explicitly.
        Gate::policy(DatabaseNotification::class, NotificationPolicy::class);

        // Phase 10: real unread count for the navbar/bell. Runs only for
        // authenticated users, once per rendered page (both layouts).
        View::composer(['layouts.minimal', 'layouts.site'], function ($view) {
            $user = Auth::user();
            $request = request();

            // layouts.site extends layouts.minimal, so memoize per request
            // to keep it to a single COUNT query per page.
            if ($user && ! $request->attributes->has('unread_notifications_count')) {
                $request->attributes->set(
                    'unread_notifications_count',
                    app(NotificationCenterService::class)->unreadCount($user)
                );
            }

            $view->with(
                'unreadNotificationsCount',
                $user ? $request->attributes->get('unread_notifications_count', 0) : 0
            );
        });
    }
}
