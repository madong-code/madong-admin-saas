<?php
return [
    'default' => [
        'host'    => env('QUEUE_REDIS_HOST', '127.0.0.1').':'.env('QUEUE_REDIS_PORT','6379'),
        'options' => [
            'auth'          => env('QUEUE_REDIS_PASSWORD', null),   // 密码，字符串类型，可选参数
            'db'            => env('QUEUE_REDIS_DB', 0),            // 数据库
            'prefix'        => env('QUEUE_REDIS_PREFIX', ''),       // key 前缀,
            'max_attempts'  => 5,                                               // 消费失败后，重试次数
            'retry_seconds' => 5,                                               // 重试间隔，单位秒
        ],
    ],
];
