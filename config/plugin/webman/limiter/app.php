<?php

use support\limiter\RateLimitException;

return [
    'enable' => true,
    'driver' => 'auto', // auto, apcu, memory, redis
    'stores' => [
        'redis' => [
            'connection' => 'default',
        ]
    ],
    // 这些ip的请求不做频率限制
    'ip_whitelist' => [
        '127.0.0.1',
    ],
    'exception' => RateLimitException::class
];