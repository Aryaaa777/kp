<?php

namespace App\Providers;

use App\Models\Cooperative;
use App\Models\CooperativeLicense;
use App\Models\Document;
use App\Policies\CooperativeLicensePolicy;
use App\Policies\CooperativePolicy;
use App\Policies\DocumentPolicy;
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

        // Otorisasi perizinan & dokumen (F3).
        Gate::policy(CooperativeLicense::class, CooperativeLicensePolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
    }
}
