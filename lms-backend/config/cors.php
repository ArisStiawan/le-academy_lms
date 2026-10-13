<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Atur origin frontend yang boleh mengakses API. Untuk produksi, isi
    | CORS_ALLOWED_ORIGINS di .env dengan daftar domain terpisah koma, mis:
    |   CORS_ALLOWED_ORIGINS=https://app.leacademy.id,https://admin.leacademy.id
    |
    | Autentikasi API ini memakai Bearer token (bukan cookie), sehingga
    | supports_credentials di-set false dan default "*" tidak berisiko
    | mengirim cookie lintas origin.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', '*'))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
