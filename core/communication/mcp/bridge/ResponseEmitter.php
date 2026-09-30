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

use Psr\Http\Message\ResponseInterface;
use support\Response;

/**
 * PSR-7 Response -> webman Response 发射器
 *
 * 首期以 JSON 单响应模式跑通（SDK 的 application/json 响应一次性回发）；
 * text/event-stream 流式回发（raw send + Connection: keep-alive）为后续增强，
 * 届时须遵循项目已验证的 SSE 三大坑实现。
 */
final class ResponseEmitter
{
    public function emit(ResponseInterface $response): Response
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[(string) $name] = implode(', ', $values);
        }

        return new Response(
            $response->getStatusCode(),
            $headers,
            (string) $response->getBody(),
        );
    }
}
