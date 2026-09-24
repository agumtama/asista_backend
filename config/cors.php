<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'http://127.0.0.1:8080,http://localhost:8080'))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
