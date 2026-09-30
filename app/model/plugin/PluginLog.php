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
 * Official Website: https://madong.tech
 */

namespace app\model\plugin;

use core\foundation\base\SystemModel;

/**
 * 系统插件日志
 *
 * 平台级审计表(sys_ 前缀), 始终主库且不被 TenantScope 隐式过滤。
 *
 * @author Mr.April
 * @since  1.0
 */
class PluginLog extends SystemModel
{
    protected $table = 'sys_plugin_log';

    /**
     * 指示是否自动维护时间戳
     *
     * @var bool
     */
    public $timestamps = true;

    protected $appends = ['created_date', 'updated_date'];

    protected $fillable = [
        'id',
        'action',
        'key',
        'pre_upgrade_version',
        'post_upgrade_version',
        'created_at',
        'updated_at',
        'tenant_id',
    ];


    protected $casts = [
        'id'        => 'string',
        'tenant_id' => 'string',
    ];}
