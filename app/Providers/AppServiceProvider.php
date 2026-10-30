<?php

namespace App\Providers;

use App\Models\User;
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
        Gate::define('personal', fn (User $user) => $user->esPersonal());
        Gate::define('jefe', fn (User $user) => $user->esJefe());
        Gate::define('asignar', fn (User $user) => $user->puedeAsignar());
    }
}
