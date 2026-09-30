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
 * 系统插件
 *
 * 平台级共享表(sys_ 前缀), 始终主库且不被 TenantScope 隐式过滤。
 *
 * @author Mr.April
 * @since  1.0
 */
class Plugin extends SystemModel
{

    protected $table = 'sys_plugin';

    /**
     * 指示是否自动维护时间戳
     *
     * @var bool
     */
    public $timestamps = true;

    protected $appends = ['created_date', 'updated_date'];

    /**
     * 时间格式为Unix时间戳
     *
     * @var string
     */
    protected $dateFormat = 'U';

    protected $fillable = [
        'id',
        'title',
        'icon',
        'key',
        'desc',
        'status',
        'author',
        'version',
        'created_at',
        'updated_at',
        'installed_at',
        'cover',
        'type',
        'variables',
        'support_app',
        'tenant_id',
    ];


    protected $casts = [
        'id'        => 'string',
        'tenant_id' => 'string',
    ];}
