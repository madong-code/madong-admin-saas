<?php

use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\RedisStore;

return [
    'storage' => 'file', // file/redis， 建议使用 redis，file 不支持 ttl
    'storage_configs' => [
        'file' => [
            'class' => FlockStore::class,
            'construct' => [
                'lockPath' => runtime_path() . '/lock',
            ],
        ],
        'redis' => [
            'class' => RedisStore::class,
            'construct' => function() {
                return [
                    'redis' => \support\Redis::connection('default')->client(),
                ];
            },
        ],
    ],
    'default_config' => [
        'ttl' => 300, // 默认锁超时时间
        'auto_release' => true, // 是否自动释放，建议设置为 true
        'prefix' => 'lock_', // 锁前缀
    ],
];
