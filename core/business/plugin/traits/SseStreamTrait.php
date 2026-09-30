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
namespace core\business\plugin\traits;

use support\Response;

/**
 * SSE 流式响应头 Trait
 * 用于插件安装/卸载时的 SSE 响应头生成
 */
trait SseStreamTrait
{
    /**
     * 发送 SSE 响应头
     *
     * @param mixed $connection Workerman TCP 连接
     */
    protected function sendSseHeaders(mixed $connection): void
    {
        $connection->send(new Response(200, [
            'Content-Type'                     => 'text/event-stream',
            'Cache-Control'                    => 'no-cache',
            'X-Accel-Buffering'                => 'no', // 禁用 Nginx 缓冲, 保证事件实时推送
            // 注意: 不能携带 Connection 头, HTTP/2 下会触发 ERR_HTTP2_PROTOCOL_ERROR
            'Access-Control-Allow-Origin'      => '*',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Expose-Headers'    => 'Content-Type',
        ], "\r\n"));
    }
}
