<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Lets Blade use @can('admin') ... @endcan
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // Phase 9: log sign-ins and sign-outs
        Event::listen(function (Login $event) {
            if ($event->user instanceof User) {
                ActivityLogger::log('login', $event->user, 'Logged in', [], $event->user);
            }
        });

        Event::listen(function (Logout $event) {
            if ($event->user instanceof User) {
                ActivityLogger::log('logout', $event->user, 'Logged out', [], $event->user);
            }
        });
    }
}
