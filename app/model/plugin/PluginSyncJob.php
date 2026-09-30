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

namespace app\model\plugin;

use app\enum\plugin\PluginSyncStatus;
use core\foundation\base\SystemModel;

/**
 * 插件同步任务审计表
 *
 * 记录平台对多租户的批量同步任务(安装/更新/卸载/重装),
 * 由 PluginBatchExecutor 在 >50 租户时落库入队,PluginSyncJobConsumer 消费。
 *
 * @property int    $id              主键
 * @property string $plugin_key      插件标识
 * @property string $action          install|update|uninstall|reinstall
 * @property array  $tenant_ids      目标租户ID列表
 * @property int    $force           是否强制级联 0否 1是
 * @property string $status          PluginSyncStatus 枚举值
 * @property int    $progress_total  总任务数
 * @property int    $progress_done   已完成数
 * @property string $error           错误信息
 * @property int    $operator_id     操作人ID
 * @property string $started_at      开始时间
 * @property string $finished_at     结束时间
 *
 * @author Mr.April
 * @since  1.0
 */
class PluginSyncJob extends SystemModel
{
    protected $table = 'saas_tenant_plugin_sync_jobs';

    public $timestamps = true;

    protected $dateFormat = 'U';

    protected $appends = ['created_date', 'updated_date'];

    protected $fillable = [
        'id',
        'plugin_key',
        'action',
        'tenant_ids',
        'force',
        'status',
        'progress_total',
        'progress_done',
        'error',
        'operator_id',
        'started_at',
        'finished_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id'             => 'string',
        'tenant_ids'     => 'array',
        'force'          => 'integer',
        'progress_total' => 'integer',
        'progress_done'  => 'integer',
        'operator_id'    => 'string',
    ];

    public function getStatusEnum(): PluginSyncStatus
    {
        return PluginSyncStatus::tryFrom((string) $this->status) ?? PluginSyncStatus::PENDING;
    }
}
