<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memasang header keamanan dasar pada seluruh respons (NFR-SEC-017).
 *
 * HSTS sengaja hanya dipasang pada request HTTPS. CSP dapat dinonaktifkan
 * untuk pengembangan lokal (Vite HMR), tetapi wajib aktif di produksi dan
 * diperiksa oleh `php artisan app:production-check`.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = (array) config('security.headers', []);

        if (($value = $headers['x_frame_options'] ?? null) !== null && $value !== '') {
            $response->headers->set('X-Frame-Options', (string) $value);
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');

        if (($value = $headers['referrer_policy'] ?? null) !== null && $value !== '') {
            $response->headers->set('Referrer-Policy', (string) $value);
        }

        if (($value = $headers['permissions_policy'] ?? null) !== null && $value !== '') {
            $response->headers->set('Permissions-Policy', (string) $value);
        }

        if (($value = $headers['cross_origin_opener_policy'] ?? null) !== null && $value !== '') {
            $response->headers->set('Cross-Origin-Opener-Policy', (string) $value);
        }

        $this->applyHsts($request, $response);
        $this->applyContentSecurityPolicy($request, $response);

        return $response;
    }

    private function applyHsts(Request $request, Response $response): void
    {
        if (! $request->isSecure() || ! (bool) config('security.hsts.enabled', true)) {
            return;
        }

        $maxAge = max(0, (int) config('security.hsts.max_age', 31536000));
        $value = 'max-age='.$maxAge;

        $includeSubdomains = (bool) config('security.hsts.include_subdomains', false);
        $preload = (bool) config('security.hsts.preload', false);

        if ($includeSubdomains || $preload) {
            $value .= '; includeSubDomains';
        }

        if ($preload) {
            $value .= '; preload';
        }

        $response->headers->set('Strict-Transport-Security', $value);
    }

    private function applyContentSecurityPolicy(Request $request, Response $response): void
    {
        if (! (bool) config('security.csp.enabled', false)) {
            return;
        }

        $policy = trim((string) config('security.csp.policy', ''));

        if ($policy === '') {
            return;
        }

        if ($request->isSecure() && ! str_contains($policy, 'upgrade-insecure-requests')) {
            $policy .= '; upgrade-insecure-requests';
        }

        $header = (bool) config('security.csp.report_only', false)
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $response->headers->set($header, $policy);
    }
}
