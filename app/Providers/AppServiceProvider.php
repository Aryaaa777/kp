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
        /**
         * Mencegah akses lintas koperasi (anti-IDOR).
         *
         * Admin dinas boleh mengakses seluruh koperasi. Pengguna koperasi
         * hanya boleh mengakses koperasi yang tertaut pada akunnya.
         */
        Gate::define('access-koperasi', function (User $user, int $koperasiId): bool {
            if ($user->hasRole('admin_dinas')) {
                return true;
            }

            return $user->koperasi_id !== null
                && (int) $user->koperasi_id === $koperasiId;
        });
    }
}
