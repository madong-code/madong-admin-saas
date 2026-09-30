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
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace app\middleware\global;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * Class StaticFile
 * @package app\middleware
 */
class StaticFile implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        if (str_contains($request->path(), '/.')) {
            return response('<h1>403 forbidden</h1>', 403);
        }
        /** @var Response $response */
        $response = $handler($request);
        // Add cross domain HTTP header
        /*$response->withHeaders([
            'Access-Control-Allow-Origin'      => '*',
            'Access-Control-Allow-Credentials' => 'true',
        ]);*/
        return $response;
    }
}
