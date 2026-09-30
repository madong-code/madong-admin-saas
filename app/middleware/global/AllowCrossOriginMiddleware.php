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
 * Official Website: https://madong.tech
 */

namespace app\middleware\global;



use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 跨域中间件
 *
 * @author Mr.April
 * @since  1.0
 */
class AllowCrossOriginMiddleware implements MiddlewareInterface
{

    public function process(Request $request, callable $handler): Response
    {
        $response = $request->method() == 'OPTIONS' ? response('') : $handler($request);
        $response->withHeaders([
            'Access-Control-Allow-Origin'      => '*',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Headers'     => 'Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With,Origin',
            'Access-Control-Allow-Methods'     => 'GET,POST,PUT,DELETE,OPTIONS,PATCH',
        ]);
        return $response;
    }
}
