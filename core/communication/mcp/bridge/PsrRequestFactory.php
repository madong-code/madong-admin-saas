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

namespace core\communication\mcp\bridge;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use support\Request;

/**
 * webman Request -> PSR-7 ServerRequest 桥接（nyholm/psr7）
 */
final class PsrRequestFactory
{
    public static function create(Request $request): ServerRequestInterface
    {
        $factory = new Psr17Factory();
        $serverRequest = $factory->createServerRequest(
            strtoupper($request->method()),
            $factory->createUri($request->fullUrl()),
        );

        foreach ((array) $request->header() as $name => $value) {
            $serverRequest = $serverRequest->withAddedHeader((string) $name, (array) $value);
        }

        $version = '1.1';
        if (method_exists($request, 'protocolVersion')) {
            $version = str_replace('HTTP/', '', (string) $request->protocolVersion()) ?: '1.1';
        }

        return $serverRequest
            ->withProtocolVersion($version)
            ->withBody($factory->createStream((string) $request->rawBody()));
    }
}
