<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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
     */
    public function boot(): void
    {
        Gate::define('isAgent', function ($user) {
            return $user->role === 'agent_municipal';
        });

        Gate::define('isCitoyen', function ($user) {
            return $user->role === 'citoyen';
        });
    }
}