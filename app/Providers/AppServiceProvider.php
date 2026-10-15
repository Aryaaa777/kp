<?php

namespace App\Providers;

use App\Models\Cooperative;
use App\Policies\CooperativePolicy;
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
        // Otorisasi data induk koperasi (termasuk isolasi antar koperasi / anti-IDOR).
        Gate::policy(Cooperative::class, CooperativePolicy::class);
    }
}
