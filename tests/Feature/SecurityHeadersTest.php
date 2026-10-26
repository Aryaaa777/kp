<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Header keamanan dasar (NFR-SEC-017).
 */
class SecurityHeadersTest extends TestCase
{
    public function test_public_response_has_basic_security_headers(): void
    {
        config()->set('security.csp.enabled', true);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Permissions-Policy');

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
    }

    public function test_hsts_is_only_sent_on_secure_requests(): void
    {
        $plain = $this->get('/');
        $plain->assertHeaderMissing('Strict-Transport-Security');

        $secure = $this->get('https://localhost/');
        $secure->assertOk();
        $secure->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_hsts_preload_implies_include_subdomains(): void
    {
        config()->set('security.hsts.preload', true);

        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
    }

    public function test_csp_can_be_disabled_for_local_development(): void
    {
        config()->set('security.csp.enabled', false);

        $this->get('/')->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_csp_does_not_use_inline_script_handlers(): void
    {
        config()->set('security.csp.enabled', true);

        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringNotContainsString("script-src 'unsafe-inline'", $csp);
    }

    public function test_forwarded_https_is_detected_when_the_proxy_is_trusted(): void
    {
        config()->set('trustedproxy.proxies', '*');

        $response = $this->withHeader('X-Forwarded-Proto', 'https')->get('/');

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_security_headers_are_present_on_error_responses(): void
    {
        $this->get('/halaman-tidak-ada')
            ->assertNotFound()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}
