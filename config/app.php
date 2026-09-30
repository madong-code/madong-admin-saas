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

use support\Request;

return [

    'name'                 => env('APP_NAME', 'MD-Admin'),
    'debug'                => env('APP_DEBUG', false),
    "lang"                 => \app\enum\common\LangEnum::ZhCN->value,//默认语言
    'error_reporting'      => E_ALL,
    'default_timezone'     => 'Asia/Shanghai',
    'request_class'        => Request::class,
    'public_path'          => base_path() . DIRECTORY_SEPARATOR . 'public',
    'runtime_path'         => base_path(false) . DIRECTORY_SEPARATOR . 'runtime',
    'controller_suffix'    => 'Controller',
    'controller_reuse'     => false,
    'store_in_recycle_bin' => env('RECYCLE_BIN_ENABLED', false),//是否开启回站模式
];
