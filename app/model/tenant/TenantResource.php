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
namespace app\model\tenant;

use core\foundation\base\SystemModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 租户资源使用模型
 *
 * @property int    $id
 * @property int    $tenant_id         租户ID
 * @property string $resource_type     资源类型
 * @property int    $used              已使用量
 * @property int    $quota             配额上限
 * @property string $unit              单位
 * @property int    $warning_threshold 警告阈值%
 * @property string $reset_date        重置日期
 * @property int    $last_reset_at     上次重置时间戳
 */
class TenantResource extends SystemModel
{
    use SoftDeletes;

    protected $table = 'saas_tenant_resource';
    protected $primaryKey = 'id';
    protected $autoWriteTimestamp = true;


    protected $casts = [
        'tenant_id'         => 'string',
        'used'              => 'integer',
        'quota'             => 'integer',
        'warning_threshold' => 'integer',
    ];

    /** 用户 */
    const TYPE_USERS = 'users';
    /** 存储 */
    const TYPE_STORAGE = 'storage';
    /** 坐席 */
    const TYPE_AGENTS = 'agents';
    /** API调用 */
    const TYPE_API_CALLS = 'api_calls';
}
