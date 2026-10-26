<?php

namespace App\Http\Middleware;

use App\Models\Cooperative;
use App\Services\AdminRegionScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjagaan tambahan untuk admin wilayah (FR-012, PROVISIONAL).
 *
 * Middleware ini **tidak** menggantikan Policy. Ia hanya memastikan route yang
 * membawa resource koperasi tidak dapat diakses admin wilayah di luar kewenangannya.
 * Daftar/index difilter di controller masing-masing.
 */
class EnsureAdminRegionAccess
{
    public function __construct(private readonly AdminRegionScope $scope) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $this->scope->appliesTo($user)) {
            return $next($request);
        }

        $cooperative = $request->route('cooperative');

        if ($cooperative instanceof Cooperative
            && ! $this->scope->allowsCooperative($user, (int) $cooperative->getKey())) {
            abort(403, 'Anda tidak memiliki kewenangan atas koperasi di luar wilayah Anda.');
        }

        foreach (['license', 'healthAssessment', 'governanceRecord', 'asset', 'memberHistory', 'officer', 'document', 'submission'] as $parameter) {
            $model = $request->route($parameter);

            if ($model === null || ! is_object($model)) {
                continue;
            }

            $cooperativeId = $model->cooperative_id ?? null;

            if ($cooperativeId !== null
                && ! $this->scope->allowsCooperative($user, (int) $cooperativeId)) {
                abort(403, 'Anda tidak memiliki kewenangan atas data koperasi di luar wilayah Anda.');
            }
        }

        return $next($request);
    }
}
