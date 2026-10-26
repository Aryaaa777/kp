<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Header keamanan dasar (NFR-SEC-017)
    |--------------------------------------------------------------------------
    |
    | Header berikut dipasang oleh middleware `SecurityHeaders` pada seluruh
    | respons. HSTS hanya dikirim bila request benar-benar HTTPS. CSP default
    | nonaktif agar `npm run dev` (Vite HMR) tidak terganggu; produksi wajib
    | mengaktifkannya lewat `SECURITY_CSP_ENABLED=true` dan memverifikasi
    | melalui `php artisan app:production-check`.
    |
    */

    'headers' => [
        'x_frame_options' => env('SECURITY_X_FRAME_OPTIONS', 'DENY'),
        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'permissions_policy' => env(
            'SECURITY_PERMISSIONS_POLICY',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ),
        'cross_origin_opener_policy' => env('SECURITY_COOP', 'same-origin'),
    ],

    'hsts' => [
        'enabled' => (bool) env('SECURITY_HSTS_ENABLED', true),
        'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', false),
        'preload' => (bool) env('SECURITY_HSTS_PRELOAD', false),
    ],

    'csp' => [
        'enabled' => (bool) env('SECURITY_CSP_ENABLED', false),
        'report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', false),

        /*
         * Kebijakan ini kompatibel dengan Blade/Tailwind saat ini:
         * - script hanya dari origin sendiri (tidak ada inline event handler);
         * - style mengizinkan atribut style inline (mis. progress bar);
         * - gambar mengizinkan data URI untuk ikon kecil.
         *
         * `upgrade-insecure-requests` ditambahkan otomatis hanya pada request
         * HTTPS agar pengembangan HTTP lokal tidak rusak.
         */
        'policy' => env(
            'SECURITY_CSP_POLICY',
            "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'",
        ),
    ],

];
