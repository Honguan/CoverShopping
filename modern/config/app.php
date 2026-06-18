<?php

return [
    'name' => env('APP_NAME', 'CoverShopping'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'Asia/Taipei',
    'locale' => env('APP_LOCALE', 'zh_TW'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'zh_TW'),
    'faker_locale' => 'zh_TW',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(explode(',', env('APP_PREVIOUS_KEYS', ''))),
    ],
];
