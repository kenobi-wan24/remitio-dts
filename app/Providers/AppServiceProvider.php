<?php

namespace App\Providers;

use App\Models\User;
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
    }
}
