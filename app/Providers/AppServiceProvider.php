<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Rate limiting for login and manager PIN verification (W1.4, W2.2, Section 10)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email').'|'.$request->ip());
        });

        RateLimiter::for('pin-approval', function (Request $request) {
            return Limit::perMinute(5)->by(($request->user()?->id ?? 'guest').'|'.$request->ip());
        });

        // Role Policies / Gates (Section 2 & 3)
        Gate::define('manage-catalog', fn (User $user) => in_array($user->role, [UserRole::Manager, UserRole::Owner], true));
        Gate::define('manage-inventory', fn (User $user) => in_array($user->role, [UserRole::Manager, UserRole::Owner], true));
        Gate::define('force-close-shift', fn (User $user) => in_array($user->role, [UserRole::Manager, UserRole::Owner], true));
        Gate::define('view-reports', fn (User $user) => in_array($user->role, [UserRole::Manager, UserRole::Owner], true));
        Gate::define('manage-system', fn (User $user) => $user->role === UserRole::Owner);
    }
}

