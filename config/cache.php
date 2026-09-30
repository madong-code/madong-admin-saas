<?php
/**
 * This file is part of webman.
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

return [
    'default' => 'redis',
    'stores'  => [
        'file'  => [
            'driver' => 'file',
            'path'   => runtime_path('cache'),
        ],
        'redis' => [
            'driver'     => 'redis',
            'connection' => 'default',
        ],
    ],
    //对应自定义扩展
    'custom'  => [
        'default'   => [
            'host'     => env('CACHE_CUSTOM_REDIS_HOST', '127.0.0.1'),
            'password' => env('CACHE_CUSTOM_REDIS_PASSWORD', null),
            'port'     => env('CACHE_CUSTOM_REDIS_PORT', 6379),
            'database' => env('CACHE_CUSTOM_REDIS_DB', 0),
        ],
        'namespace' => '',
        'prefix'    => 'md_',
        'type'      => 'redis',//redis||file
        'stores'    => [
            'redis' => [
                'host'     => env('CACHE_CUSTOM_REDIS_HOST', '127.0.0.1'),
                'password' => env('CACHE_CUSTOM_REDIS_PASSWORD', null),
                'port'     => env('CACHE_CUSTOM_REDIS_PORT', 6379),
                'database' => env('CACHE_CUSTOM_REDIS_DB', 0),
            ],
        ],
    ],

];
