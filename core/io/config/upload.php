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
use core\io\upload\storage\Cos;
use core\io\upload\storage\Local;
use core\io\upload\storage\Oss;
use core\io\upload\storage\Qiniu;
use core\io\upload\storage\S3;

return [
    'debug'           => config('app.debug'),
    'cdn_url'         => '',
    'cdn_url_params'  => '',
    'default_avatar'  => '/upload/avatar.jpeg',
    'config_key'      => [
        'local' => '',
        'oss'   => '',
        'cos'   => '',
        'qiniu' => '',
        's3'    => '',
    ],
    'adapter_classes' => [
        'local' => Local::class,
        'oss'   => Oss::class,
        'cos'   => Cos::class,
        'qiniu' => Qiniu::class,
        's3'    => S3::class,
    ],
];
