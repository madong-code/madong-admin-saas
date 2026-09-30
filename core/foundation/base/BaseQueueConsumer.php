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

namespace core\foundation\base;

use app\enum\common\QueueMessageStatusEnum;
use app\model\sync\QueueMessage;
use core\infrastructure\logger\Logger;
use Webman\RedisQueue\Consumer;

/**
 * 队列消费者基类
 *
 * 自动处理：任务记录创建/更新/销毁、日志记录、失败重试、死信标记。
 * 子类只需实现 handle() 执行业务逻辑，定义 $queue 队列名。
 *
 * @property string $queue 队列名（子类必须定义）
 */
abstract class BaseQueueConsumer implements Consumer
{
    /**
     * Redis 连接名，默认 default
     */
    public string $connection = 'default';

    /**
     * 最大重试次数，子类可覆盖
     */
    protected int $maxRetry = 3;

    /**
     * 执行业务逻辑
     *
     * @param array $data
     */
    abstract protected function handle(array $data): void;

    /**
     * 日志标签，默认取短类名，子类可覆盖
     */
    protected function getLogTag(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * {@inheritdoc}
     */
    final public function consume($data): bool
    {
        $tag = $this->getLogTag();
        $record = $this->createRecord($data);

        try {
            Logger::info("[{$tag}] 开始消费", ['data' => $data]);
            $this->handle($data);
            $this->finishRecord($record, QueueMessageStatusEnum::SUCCESS->value);
            Logger::info("[{$tag}] 消费完成");
            return true;
        } catch (\Throwable $e) {
            Logger::error("[{$tag}] 消费失败", [
                'error' => $e->getMessage(),
                'data'  => $data,
            ]);

            $this->failRecord($record, $e);

            // 超过重试上限 → 标记死信，不再重试
            if ($record && $record->retry_count >= $record->max_retry) {
                $this->finishRecord($record, QueueMessageStatusEnum::DEAD->value);
                Logger::warning("[{$tag}] 已达重试上限({$record->max_retry})，标记为死信");
                return true;
            }

            throw $e;
        } finally {
            \core\business\tenant\context\TenantContext::clear();
        }
    }

    /**
     * 创建任务执行记录
     */
    private function createRecord(array $data): ?QueueMessage
    {
        try {
            $record = new QueueMessage();
            $record->queue_name = $this->queue ?? static::class;
            $record->status = QueueMessageStatusEnum::PROCESSING->value;
            $record->payload = $data;
            $record->retry_count = 0;
            $record->max_retry = $this->maxRetry;
            $record->save();
            return $record;
        } catch (\Throwable $e) {
            Logger::error("[{$this->getLogTag()}] 创建任务记录失败", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 完成记录（成功/死信后自动删除）
     */
    private function finishRecord(?QueueMessage $record, string $status): void
    {
        if (!$record) return;

        try {
            $record->status = $status;
            $record->processed_at = time();
            $record->save();
            $record->delete(); // 自动销毁
        } catch (\Throwable $e) {
            Logger::error("[{$this->getLogTag()}] 更新任务记录失败", ['error' => $e->getMessage()]);
        }
    }

    /**
     * 失败记录（保留错误信息供排查）
     */
    private function failRecord(?QueueMessage $record, \Throwable $e): void
    {
        if (!$record) return;

        try {
            $record->status = QueueMessageStatusEnum::FAILED->value;
            $record->retry_count = ($record->retry_count ?? 0) + 1;
            $record->error_msg = $e->getMessage();
            $record->result = [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ];
            $record->processed_at = time();
            $record->save();
        } catch (\Throwable $e2) {
            Logger::error("[{$this->getLogTag()}] 记录失败状态出错", ['error' => $e2->getMessage()]);
        }
    }
}
