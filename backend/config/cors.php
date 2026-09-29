<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Phase 21: this codifies what was previously an undocumented framework
    | fallback (no config/cors.php existed, so Laravel's own hardcoded
    | defaults — these exact values — applied silently). A wildcard origin
    | is genuinely correct here, not just convenient: every API consumer
    | (admin SPA, storefront, customer account) authenticates with a bearer
    | token in the Authorization header, never a cookie, so there's no
    | ambient credential a cross-origin page could ride on — the risk a
    | restrictive `allowed_origins` list defends against.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
