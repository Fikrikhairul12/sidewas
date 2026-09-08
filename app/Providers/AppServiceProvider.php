<?php

namespace App\Providers;

use App\Models\Kunjungan\Visit;
use App\Models\User;
use App\Policies\VisitPolicy;
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
        Gate::define('access-kunjungan', fn (User $user): bool => $user->canAccessKunjungan());
        Gate::policy(Visit::class, VisitPolicy::class);
    }
}
