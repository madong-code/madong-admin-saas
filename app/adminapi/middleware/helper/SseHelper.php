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

namespace app\adminapi\middleware\helper;

use core\foundation\tool\Sse;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * SSE 处理辅助类
 * 提供 SSE 请求检测和错误处理功能
 */
class SseHelper
{
    /**
     * 检查是否为SSE请求
     *
     * @param Request $request
     *
     * @return bool
     */
    public static function isSseRequest(Request $request): bool
    {
        // 方法1：检查请求头
        $acceptHeader = $request->header('Accept', '');
        if (str_contains($acceptHeader, 'text/event-stream')) {
            return true;
        }
        
        // 方法2：检查路径（SSE接口通常有特定路径模式）
        $path = $request->path();
        $ssePaths = [
            '/terminal',
            '/terminal/exec',
        ];
        foreach ($ssePaths as $ssePath) {
            if (str_starts_with($path, $ssePath)) {
                return true;
            }
        }
        
        // 方法3：检查查询参数
        $isSse = $request->input('sse', false) || $request->input('stream', false);
        if ($isSse) {
            return true;
        }
        
        return false;
    }

    /**
     * 通过连接发送SSE错误消息
     *
     * @param Request $request
     * @param string  $message
     *
     * @return Response
     * @throws \Exception
     */
    public static function sendSseErrorViaConnection(Request $request, string $message): Response
    {
        // 检查是否有连接对象
        if (!property_exists($request, 'connection') || !$request->connection) {
            // 回退到普通的SSE响应
            return self::createSseErrorResponse($message, $request->input('uuid'));
        }
        
        $connection = $request->connection;
        $uuid = $request->input('uuid');
        
        try {
            // 发送SSE响应头 (注意: 不能携带 Connection 头, HTTP/2 下会触发 ERR_HTTP2_PROTOCOL_ERROR)
            $connection->send(new Response(200, [
                'Content-Type'                     => 'text/event-stream',
                'Cache-Control'                    => 'no-cache',
                'Access-Control-Allow-Origin'      => '*',
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Expose-Headers'    => 'Content-Type',
            ], "\r\n"));
            
            // 发送SSE错误消息，传递uuid
            $connection->send(Sse::error($message, null, $uuid));
            
            // 发送空数据表示结束，防止前端等待更多数据
            $connection->send(new Response(200, [], ""));
            
        } catch (\Exception $e) {
            // 忽略连接异常，返回普通响应
            return self::createSseErrorResponse($message, $uuid);
        }
        
        // 返回空响应结束请求
        return new Response(200, [], '');
    }

    /**
     * 创建SSE格式的错误响应
     *
     * @param string $message
     * @param string|null $uuid
     *
     * @return Response
     * @throws \Exception
     */
    public static function createSseErrorResponse(string $message, ?string $uuid = null): Response
    {
        $errorMessage = Sse::error($message, [], $uuid);
        
        // 创建完整的SSE响应
        $responseBody = $errorMessage;

        return new Response(200, [
            // 注意: 必须带 charset 参数, 裸 text/event-stream 会被 workerman 吞掉 body 并挂起连接
            'Content-Type'                     => 'text/event-stream; charset=utf-8',
            'Cache-Control'                    => 'no-cache',
            'X-Accel-Buffering'                => 'no', // 禁用Nginx缓冲
            // 注意: 不能携带 Connection 头, HTTP/2 下会触发 ERR_HTTP2_PROTOCOL_ERROR
            'Access-Control-Allow-Origin'      => '*',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Expose-Headers'    => 'Content-Type',
        ], $responseBody);
    }
}