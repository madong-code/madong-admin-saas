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

namespace app\process;

use app\dao\plugin\PluginSyncJobDao;
use app\enum\plugin\PluginSyncStatus;
use app\service\core\plugin\PluginBatchExecutor;
use app\service\core\plugin\PluginLifecycleOrchestrator;
use support\Container;
use Workerman\Worker;
use Webman\RedisQueue\Redis as RedisQueue;

/**
 * 插件同步队列消费者 (WP4)
 *
 * webman/redis-queue 消费者进程:
 *   - 监听 config('plugin.queue.name') 默认 plugin-sync-jobs
 *   - 每条消息 = 一个 PluginSyncJob 任务
 *   - 逐租户执行, 通过 PluginSyncJobDao::bumpProgress 实时回写进度
 *   - 全部完成 → status=SUCCESS; 任一失败 → status=FAILED 并收集 errors
 *
 * 注册: config/process.php
 *
 * 异常策略:
 *   - 整体 catch 异常, 标记 FAILED, 不抛出(避免 webman 反复重试耗尽 retry 配额)
 *   - 单租户失败不中断, 收集到 errors 后继续
 */
class PluginSyncJobConsumer
{
    /**
     * webman/redis-queue 消费者入口
     *
     * 调用方式: RedisQueue::consume($queueName, $callback)
     * 阻塞拉取, 无任务时休眠(由 redis-queue 内部 Timer 驱动)
     *
     * @param Worker|null $worker  webman 注入, 可忽略
     */
    public function onWorkerStart(?Worker $worker = null): void
    {
        $queueName = (string)config('plugin.queue.name', 'plugin-sync-jobs');

        // 检测 Redis 是否可达,不可达时静默退出,避免 workerman 反复重试刷爆日志
        try {
            $host = (string)config('redis_queue.default.host', '127.0.0.1');
            $port = (int)config('redis_queue.default.port', 6379);
            $sock = @fsockopen($host, $port, $errno, $errstr, 1.0);
            if ($sock === false) {
                $this->log('warning', "[plugin-sync-consumer] redis {$host}:{$port} unreachable ({$errstr} #{$errno}), consumer stopped silently", []);
                return;
            }
            fclose($sock);
        } catch (\Throwable $e) {
            $this->log('warning', "[plugin-sync-consumer] redis check failed: " . $e->getMessage(), []);
            return;
        }

        try {
            RedisQueue::consume($queueName, function ($payload) {
                $this->handle($payload);
            });
        } catch (\Throwable $e) {
            $this->log('warning', "[plugin-sync-consumer] consume() failed: " . $e->getMessage(), []);
        }
    }

    /**
     * 处理单条任务
     *
     * @param array $payload  enqueue() 时发送的关联数组
     *                       { jobId, code, version, action, tenantIds, force, requestedBy }
     */
    public function handle(array $payload): void
    {
        $jobId      = (int)($payload['jobId'] ?? 0);
        $code       = (string)($payload['code'] ?? '');
        $version    = (string)($payload['version'] ?? '');
        $action     = (string)($payload['action'] ?? 'install');
        $tenantIds  = (array)($payload['tenantIds'] ?? []);
        $force      = (bool)($payload['force'] ?? false);
        $requestedBy = (string)($payload['requestedBy'] ?? '');

        if ($jobId === 0 || $code === '' || empty($tenantIds)) {
            // 静默丢弃(避免毒消息), 但写日志
            $this->log('warn', 'invalid payload dropped', $payload);
            return;
        }

        /** @var PluginSyncJobDao $jobDao */
        $jobDao = Container::make(PluginSyncJobDao::class);
        /** @var PluginLifecycleOrchestrator $orchestrator */
        $orchestrator = Container::make(PluginLifecycleOrchestrator::class);
        /** @var PluginBatchExecutor $executor */
        $executor = Container::make(PluginBatchExecutor::class);

        // 标记 running
        $jobDao->bumpProgress($jobId, 0, null, PluginSyncStatus::RUNNING->value);

        $opts = [
            'force'        => $force,
            'requestedBy'  => $requestedBy,
        ];

        $failed = 0;
        $errors = [];

        foreach ($tenantIds as $tenantId) {
            try {
                $executor->executeOne($orchestrator, $code, $version, $action, (string)$tenantId, $opts);
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = [
                    'tenant_id' => (string)$tenantId,
                    'msg'       => $e->getMessage(),
                ];
            }
            // 每租户 +1 processed
            $jobDao->bumpProgress($jobId, 1, null, PluginSyncStatus::RUNNING->value);
        }

        // 终态: 全成功 → SUCCESS, 否则 FAILED
        $status = $failed === 0
            ? PluginSyncStatus::SUCCESS->value
            : PluginSyncStatus::FAILED->value;

        $jobDao->bumpProgress($jobId, 0, $errors, $status);
    }

    protected function log(string $level, string $msg, array $context = []): void
    {
        try {
            \support\Log::channel('plugin')->{$level}("[PluginSyncJobConsumer] {$msg}", $context);
        } catch (\Throwable $e) {
            // 日志失败不阻塞业务
        }
    }
}
