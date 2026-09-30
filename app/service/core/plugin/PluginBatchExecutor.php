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

namespace app\service\core\plugin;

use app\dao\plugin\PluginSyncJobDao;
use app\enum\plugin\PluginSyncStatus;
use app\model\plugin\PluginSyncJob;
use core\business\tenant\context\TenantContext;
use core\foundation\base\BaseService;
use core\foundation\tool\Sse;
use support\Container;
use Webman\RedisQueue\Redis as RedisQueue;

/**
 * 插件批处理执行器 (WP4)
 *
 * 职责:
 *   1. 阈值分流:
 *      - tenantCount <= config('plugin.sync.threshold_inline')(默认 50): 调用方驱动 SSE 流
 *      - tenantCount  > threshold: 写 saas_tenant_plugin_sync_jobs + Redis 队列, 由 PluginSyncJobConsumer 异步执行
 *   2. 单租户执行单元(每租户一次): install | update | uninstall
 *      委托给 PluginLifecycleOrchestrator(单体降级)
 *   3. 进度上报:
 *      - SSE 流: 通过 $progressCallback(Generator) 实时回推
 *      - 队列:  通过 PluginSyncJobDao::bumpProgress 写 audit 表
 *
 * 幂等:
 *   - 同一 (jobId, tenantId) 重复执行不报错, 由 Orchestrator 内部幂等保证
 *   - 队列重试通过 RedisQueue retry 配置 + de-duplication 防止双消费
 *
 * @author Mr.April
 * @since  1.0
 */
class PluginBatchExecutor extends BaseService
{
    /**
     * 入口: 决策 inline / queue 并执行
     *
     * @param string $code             插件编码
     * @param string $version          目标版本
     * @param string $action           install | update | uninstall
     * @param array  $tenantIds        目标租户ID列表(空=单体/平台)
     * @param array  $opts             { force?: bool, requestedBy?: string|int, connection?: string }
     * @param callable|null $progressCb  实时进度回调 fn(string $msg, int $pct, array $extra): void
     *                                   仅 inline 模式生效
     * @return array { mode: 'inline'|'queue', jobId?: int, processed?: int, total?: int }
     */
    public function dispatch(
        string $code,
        string $version,
        string $action,
        array $tenantIds = [],
        array $opts = [],
        ?callable $progressCb = null
    ): array {
        $threshold = (int)config('plugin.sync.threshold_inline', 50);

        // 单体模式: 强制 inline(无租户层)
        if (TenantContext::isSingleMode()) {
            return $this->runInline($code, $version, $action, [], $opts, $progressCb);
        }

        if (count($tenantIds) <= $threshold) {
            return $this->runInline($code, $version, $action, $tenantIds, $opts, $progressCb);
        }

        return $this->enqueue($code, $version, $action, $tenantIds, $opts);
    }

    /**
     * inline 模式: 同步逐租户执行(可配 SSE 回调)
     */
    public function runInline(
        string $code,
        string $version,
        string $action,
        array $tenantIds,
        array $opts,
        ?callable $progressCb
    ): array {
        $total = count($tenantIds);
        $batchSize = max(1, (int)config('plugin.sync.batch_size', 10));
        $processed = 0;
        $errors = [];

        /** @var PluginLifecycleOrchestrator $orchestrator */
        $orchestrator = Container::make(PluginLifecycleOrchestrator::class);
        $nameMap = $this->tenantNameMap($tenantIds);

        $this->emit($progressCb, "开始执行: {$action} {$code}@{$version} 租户数={$total}", 0);

        foreach (array_chunk($tenantIds, $batchSize) as $batchIndex => $batch) {
            foreach ($batch as $tenantId) {
                $display = $nameMap[(string)$tenantId] ?? "租户 {$tenantId}";
                try {
                    $stepAdapter = function (string $msg) use ($progressCb) {
                        if ($progressCb) {
                            $progressCb($msg, 0, []);
                        }
                    };
                    $this->executeOne($orchestrator, $code, $version, $action, $tenantId, $opts, $stepAdapter);
                    $processed++;
                } catch (\Throwable $e) {
                    $errors[] = ['tenant_id' => $tenantId, 'msg' => $e->getMessage()];
                }
                $pct = $total > 0 ? (int)floor($processed / $total * 100) : 100;
                $this->emit($progressCb, "{$display} " . ($errors && end($errors)['tenant_id'] === $tenantId ? '失败' : '完成') . " ({$processed}/{$total})", $pct);
            }
        }

        $this->emit($progressCb, "执行完成: 成功 {$processed}, 失败 " . count($errors), 100);

        return [
            'mode'      => 'inline',
            'processed' => $processed,
            'total'     => $total,
            'errors'    => $errors,
        ];
    }

    /**
     * queue 模式: 写审计 + 入队
     */
    public function enqueue(
        string $code,
        string $version,
        string $action,
        array $tenantIds,
        array $opts
    ): array {
        $now = time();

        /** @var PluginSyncJobDao $jobDao */
        $jobDao = Container::make(PluginSyncJobDao::class);

        $job = PluginSyncJob::create([
            'id'             => (int)\core\io\uuid\Snowflake::generate(),
            'plugin_key'     => $code,
            'action'         => $action,
            'tenant_ids'     => array_values($tenantIds),
            'force'          => (int)($opts['force'] ?? 0),
            'status'         => PluginSyncStatus::PENDING->value,
            'progress_total' => count($tenantIds),
            'progress_done'  => 0,
            'operator_id'    => $opts['requestedBy'] ?? null,
            'started_at'     => $now,
            'finished_at'    => null,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        $queueName = (string)config('plugin.queue.name', 'plugin-sync-jobs');
        $payload = [
            'jobId'    => $job->id,
            'code'     => $code,
            'version'  => $version,
            'action'   => $action,
            'tenantIds' => array_values($tenantIds),
            'force'    => (bool)($opts['force'] ?? false),
            'requestedBy' => (string)($opts['requestedBy'] ?? ''),
        ];

        // webman/redis-queue: RedisQueue::send($queue, $data, $delay=0)
        RedisQueue::send($queueName, $payload, 0);

        return [
            'mode'  => 'queue',
            'jobId' => $job->id,
            'total' => count($tenantIds),
        ];
    }

    /**
     * 单租户执行单元(供 inline + consumer 共用)
     */
    public function executeOne(
        PluginLifecycleOrchestrator $orchestrator,
        string $code,
        string $version,
        string $action,
        string|int $tenantId,
        array $opts,
        ?callable $stepCb = null
    ): void {
        $force = (bool)($opts['force'] ?? false);

        match ($action) {
            'install'   => $orchestrator->installForTenant($code, $version, (string)$tenantId, $opts, $stepCb),
            'update'    => $orchestrator->updateForTenant($code, $version, (string)$tenantId, $opts, $stepCb),
            'uninstall' => $orchestrator->uninstallForTenant($code, $version, (string)$tenantId, ['force' => $force] + $opts, $stepCb),
            default     => throw new \InvalidArgumentException("Unsupported action: {$action}"),
        };
    }

    protected function emit(?callable $cb, string $msg, int $pct): void
    {
        if ($cb) {
            $cb($msg, $pct, []);
        }
    }

    /**
     * 流式批量更新(供 SSE 输出)
     *
     * 与 runInline 的区别: 以 Generator 形式逐租户 yield 进度事件,
     * controller 遍历后 $connection->send() 即可让前端实时看到进度。
     * 说明: SSE 为长连接同步执行, 不经过 Redis 队列。
     *
     * @return \Generator<int, string> 逐条 SSE 事件字符串
     */
    public function streamUpdate(string $code, string $version, array $tenantIds, array $opts = []): \Generator
    {
        $total  = count($tenantIds);
        $done   = 0;
        $failed = 0;

        yield Sse::progress("开始升级 {$code} → v{$version}, 共 {$total} 个租户", 0, [
            'plugin'  => $code,
            'version' => $version,
            'total'   => $total,
        ]);

        /** @var PluginLifecycleOrchestrator $orchestrator */
        $orchestrator = Container::make(PluginLifecycleOrchestrator::class);
        $nameMap = $this->tenantNameMap($tenantIds);

        foreach ($tenantIds as $tenantId) {
            $display = $nameMap[(string)$tenantId] ?? (string)$tenantId;
            $steps = [];
            $stepCb = function (string $msg) use (&$steps, $tenantId) {
                $steps[] = ['tenant_id' => (string)$tenantId, 'msg' => $msg];
            };
            try {
                $this->executeOne($orchestrator, $code, $version, 'update', $tenantId, $opts, $stepCb);
                foreach ($steps as $step) {
                    yield Sse::progress("{$display}: {$step['msg']}", $this->pctOf($done + $failed, $total), $step + ['display' => $display]);
                }
                $done++;
                yield Sse::progress("{$display} 升级完成 ({$done}/{$total})", $this->pctOf($done + $failed, $total), [
                    'tenant_id' => (string)$tenantId,
                    'display'   => $display,
                    'result'    => 'success',
                ]);
            } catch (\Throwable $e) {
                foreach ($steps as $step) {
                    yield Sse::progress("{$display}: {$step['msg']}", $this->pctOf($done + $failed, $total), $step + ['display' => $display]);
                }
                $failed++;
                yield Sse::progress("{$display} 升级失败: " . $e->getMessage(), $this->pctOf($done + $failed, $total), [
                    'tenant_id' => (string)$tenantId,
                    'display'   => $display,
                    'result'    => 'failed',
                ]);
            }
        }

        yield Sse::completed("升级完成: 成功 {$done}, 失败 {$failed}", [
            'plugin'    => $code,
            'version'   => $version,
            'total'     => $total,
            'processed' => $done,
            'failed'    => $failed,
        ]);
    }

    /**
     * 批量加载租户显示名称
     *
     * @return array<string, string> key=tenantId, value=name 或 id
     */
    protected function tenantNameMap(array $tenantIds): array
    {
        if (empty($tenantIds)) {
            return [];
        }
        $map = [];
        $rows = \app\model\tenant\Tenant::withoutGlobalScopes()
            ->whereIn('id', $tenantIds)
            ->get(['id', 'name'])
            ->toArray();
        foreach ($rows as $row) {
            $id = (string)($row['id'] ?? '');
            $name = trim((string)($row['name'] ?? ''));
            $map[$id] = $name !== '' ? $name : $id;
        }
        return $map;
    }

    /**
     * 计算进度百分比
     */
    protected function pctOf(int $current, int $total): int
    {
        return $total > 0 ? (int)floor($current / $total * 100) : 100;
    }
}
