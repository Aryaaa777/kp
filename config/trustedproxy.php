<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reverse proxy tepercaya
    |--------------------------------------------------------------------------
    |
    | Daftar IP/CIDR dipisah koma, atau "*" hanya bila host benar-benar tidak
    | dapat diakses langsung. Dipakai middleware bawaan Laravel untuk mendeteksi
    | HTTPS di belakang reverse proxy/load balancer (X-Forwarded-*).
    |
    | Jangan gunakan "*" pada host yang dapat diakses langsung dari internet.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
