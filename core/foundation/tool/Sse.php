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
namespace core\foundation\tool;

use core\io\uuid\UUIDGenerator;

class Sse
{
    /**
     * 创建SSE响应字符串
     *
     * @param mixed $data 数据内容
     * @param string $event 事件类型
     * @param string|null $uuid 消息UUID，用于防止消息错乱
     * @param array $extend 扩展数据
     * @param string|null $key 标识键
     * @return string
     * @throws \Exception
     */
    public static function make(mixed $data, string $event = 'message', ?string $uuid = null, array $extend = [], ?string $key = null): string
    {
        // 如果没有提供uuid，自动生成一个
        if (empty($uuid)) {
            $uuid = UUIDGenerator::generate();
        }

        $dataPayload = [
            'data' => $data,
            'event' => $event,
            'uuid' => $uuid,
            'extend' => $extend,
            'key' => $key,
            'date' => date('Y-m-d H:i:s'),
        ];

        $jsonData = json_encode($dataPayload, JSON_UNESCAPED_UNICODE);
        if ($jsonData === false) {
            $jsonData = json_encode(['error' => 'JSON encode error'], JSON_UNESCAPED_UNICODE);
        }

        $sseLines = [];
        $sseLines[] = "id: $uuid";
        $sseLines[] = "event: $event";
        $sseLines[] = "data: $jsonData";

        return implode("\n", $sseLines) . "\n\n";
    }

    /**
     * 创建成功事件SSE响应字符串
     *
     * @param string $message 消息内容
     * @param mixed|null $data 附加数据
     * @param string|null $uuid 消息UUID
     * @param array $extend 扩展数据
     * @param string|null $key 标识键
     * @return string
     * @throws \Exception
     */
    public static function success(string $message, mixed $data = null, ?string $uuid = null, array $extend = [], ?string $key = null): string
    {
        return self::make([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], 'success', $uuid, $extend, $key);
    }

    /**
     * 创建错误事件SSE响应字符串（连接错误，会导致连接中断）
     *
     * @param string $message 错误消息
     * @param mixed|null $data 附加数据
     * @param string|null $uuid 消息UUID
     * @param array $extend 扩展数据
     * @param string|null $key 标识键
     * @return string
     * @throws \Exception
     */
    public static function error(string $message, mixed $data = null, ?string $uuid = null, array $extend = [], ?string $key = null): string
    {
        return self::make([
            'status' => 'error',
            'message' => $message,
            'data' => $data,
            'error_type' => 'connection', // 连接错误类型
        ], 'error', $uuid, $extend, $key);
    }

    /**
     * 创建运行错误事件SSE响应字符串（不会导致连接中断）
     *
     * @param string $message 错误消息
     * @param mixed|null $data 附加数据
     * @param string|null $uuid 消息UUID
     * @param array $extend 扩展数据
     * @param string|null $key 标识键
     * @return string
     * @throws \Exception
     */
    public static function runtimeError(string $message, mixed $data = null, ?string $uuid = null, array $extend = [], ?string $key = null): string
    {
        return self::make([
            'status' => 'error',
            'message' => $message,
            'data' => $data,
            'error_type' => 'runtime', // 运行错误类型，不会中断连接
        ], 'runtime_error', $uuid, $extend, $key);
    }

    /**
     * 创建警告事件SSE响应字符串（不会导致连接中断）
     *
     * @param string $message 警告消息
     * @param mixed|null $data 附加数据
     * @param string|null $uuid 消息UUID
     * @param array $extend 扩展数据
     * @param string|null $key 标识键
     * @return string
     * @throws \Exception
     */
    public static function warning(string $message, mixed $data = null, ?string $uuid = null, array $extend = [], ?string $key = null): string
    {
        return self::make([
            'status' => 'warning',
            'message' => $message,
            'data' => $data,
        ], 'warning', $uuid, $extend, $key);
    }

    /**
     * 创建进度事件SSE响应字符串
     *
     * @param string $message 消息内容
     * @param int $progress 进度百分比
     * @param mixed|null $data 附加数据
     * @param string|null $uuid 消息UUID
     * @param array $extend 扩展数据
     * @param string|null $key 标识键
     * @return string
     * @throws \Exception
     */
    public static function progress(string $message, int $progress, mixed $data = null, ?string $uuid = null, array $extend = [], ?string $key = null): string
    {
        return self::make([
            'status' => 'progress',
            'message' => $message,
            'progress' => $progress,
            'data' => $data,
        ], 'progress', $uuid, $extend, $key);
    }

    /**
     * 创建完成事件SSE响应字符串
     *
     * @param string $message 消息内容
     * @param mixed|null $data 附加数据
     * @param string|null $uuid 消息UUID
     * @param array $extend 扩展数据
     * @param string|null $key 标识键
     * @return string
     * @throws \Exception
     */
    public static function completed(string $message, mixed $data = null, ?string $uuid = null, array $extend = [], ?string $key = null): string
    {
        return self::make([
            'status' => 'completed',
            'message' => $message,
            'data' => $data,
        ], 'completed', $uuid, $extend, $key);
    }

    /**
     * 创建心跳事件SSE响应字符串（用于保持连接）
     *
     * @param string|null $uuid 消息UUID
     * @return string
     * @throws \Exception
     */
    public static function heartbeat(?string $uuid = null): string
    {
        return self::make([
            'status' => 'heartbeat',
            'message' => 'keep alive',
        ], 'heartbeat', $uuid);
    }

    /**
     * 创建重试事件SSE响应字符串（用于设置重连时间）
     *
     * @param int $retryMs 重试时间（毫秒）
     * @param string|null $uuid 消息UUID
     * @return string
     * @throws \Exception
     */
    public static function retry(int $retryMs = 3000, ?string $uuid = null): string
    {
        $dataPayload = [
            'status' => 'retry',
            'retry_ms' => $retryMs,
        ];

        $jsonData = json_encode($dataPayload, JSON_UNESCAPED_UNICODE);
        if ($jsonData === false) {
            $jsonData = json_encode(['error' => 'JSON encode error'], JSON_UNESCAPED_UNICODE);
        }

        $sseLines = [];
        $sseLines[] = "id: " . ($uuid ?: UUIDGenerator::generate());
        $sseLines[] = "event: retry";
        $sseLines[] = "retry: $retryMs";
        $sseLines[] = "data: $jsonData";

        return implode("\n", $sseLines) . "\n\n";
    }

    /**
     * 发送SSE响应头
     *
     * @param bool $closeConnection 是否关闭连接
     */
    public static function sendHeaders(bool $closeConnection = false): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        // 注意: 不输出 keep-alive, HTTP/2 下 Connection 头会触发 ERR_HTTP2_PROTOCOL_ERROR
        if ($closeConnection) {
            header('Connection: close');
        }
        header('X-Accel-Buffering: no'); // 禁用 Nginx 缓冲, 保证事件实时推送
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

        // 禁用输出缓冲
        if (ob_get_level()) {
            ob_end_flush();
        }

        // 立即发送头部
        flush();
    }

    /**
     * 检查连接是否仍然有效
     *
     * @return bool
     */
    public static function isConnectionAlive(): bool
    {
        return connection_status() === CONNECTION_NORMAL;
    }

    /**
     * 格式化SSE数据（用于Terminal类）
     *
     * @param string      $data   数据内容
     * @param string|null $event  事件类型
     * @param string|null $uuid   消息UUID
     * @param array       $extend 扩展数据
     *
     * @return array
     * @throws \Exception
     */
    public static function format(string $data, ?string $event = null, ?string $uuid = null, array $extend = []): array
    {
        if (empty($uuid)) {
            $uuid = UUIDGenerator::generate();
        }

        // 如果没有提供事件类型，使用默认值
        if (empty($event)) {
            $event = 'message';
        }

        return [
            'data' => $data,
            'event' => $event,
            'uuid' => $uuid,
            'extend' => $extend,
            'date' => date('Y-m-d H:i:s'),
        ];
    }
}