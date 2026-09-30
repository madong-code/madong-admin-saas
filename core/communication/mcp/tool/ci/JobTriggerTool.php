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

namespace core\communication\mcp\tool\ci;

use app\service\admin\ops\crontab\CrontabService;
use core\communication\mcp\attribute\McpTool;
use core\communication\mcp\security\McpUser;
use core\communication\mcp\support\McpTenant;
use support\Container;

/**
 * job_trigger：立即触发一次定时任务（写操作，权限码 mcp:job:trigger）
 *
 * 安全约束：
 *  - 仅允许 sys_crontab 中 enabled=1 的任务
 *  - 复用 CrontabService::runOneTask，自动写 sys_crontab_log 审计
 *  - 返回执行 code 与日志（code=0 成功）
 */
final class JobTriggerTool
{
    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'job_trigger',
        title: 'Job Trigger',
        description: '⚠️ 写操作：立即触发执行一次指定的定时任务（sys_crontab）。仅允许 enabled=1 的任务；执行结果写入 sys_crontab_log 审计表。返回 code（0=成功）与 log。需要权限码 mcp:job:trigger。',
        inputSchema: [
            'type'       => 'object',
            'properties' => [
                'id' => [
                    'type'        => 'integer',
                    'description' => 'sys_crontab 任务 ID',
                ],
            ],
            'required' => ['id'],
        ],
        permission: 'mcp:job:trigger',
    )]
    public function trigger(int $id): array
    {
        if ($id <= 0) {
            return ['code' => 1, 'log' => 'id 必须大于 0'];
        }

        // 白名单：仅 enabled=1 的任务可触发
        $crontab = McpTenant::table('sys_crontab')
            ->where('id', $id)
            ->first(['id', 'title', 'type', 'enabled', 'target']);

        if (empty($crontab)) {
            return ['code' => 1, 'log' => "任务 {$id} 不存在"];
        }
        if ((int) $crontab->enabled !== 1) {
            return ['code' => 1, 'log' => "任务 {$id} 已停用，不允许触发"];
        }

        try {
            $service = Container::make(CrontabService::class);
            $result  = $service->runOneTask($id);
        } catch (\Throwable $e) {
            return ['code' => 1, 'log' => '触发失败：' . $e->getMessage()];
        }

        return [
            'code'       => $result['code'] ?? 1,
            'log'        => $result['log'] ?? '',
            'task_id'    => $id,
            'task_title' => $crontab->title,
            'operator_id'=> $this->user?->id,
            'audited'    => true,
        ];
    }
}
