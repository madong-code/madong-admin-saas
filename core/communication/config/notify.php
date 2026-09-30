<?php
declare(strict_types=1);

/**
 *+------------------
 * madong
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: http://www.madong.tech
 */
return [
    'webman-push' => [
        'websocket'    => config('plugin.webman.push.app.websocket'),
        'api'          => config('plugin.webman.push.app.api'),
        'app_key'      => config('plugin.webman.push.app.app_key'),
        'app_secret'   => config('plugin.webman.push.app.app_secret'),
        'channel_hook' => config('plugin.webman.push.app.channel_hook'),
        'auth'         => config('plugin.webman.push.app.auth'),
    ],
];
