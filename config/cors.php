<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['http://localhost:5173'], // Vite default
    'allowed_headers' => ['*'],
    'supports_credentials' => true,
];