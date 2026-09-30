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

/**
 * 租户迁移执行记录
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $type
 * @property string $target
 * @property int $batch
 * @property string $status
 * @property string|null $version_before
 * @property string|null $version_after
 * @property string|null $error_message
 * @property int|null $started_at
 * @property int|null $finished_at
 * @property int|null $deleted_at
 * @property int $created_at
 * @property int $updated_at
 */
class TenantMigration extends SystemModel
{
    protected $table = 'tenant_migration';

    protected $fillable = [
        'id',
        'tenant_id',
        'type',
        'target',
        'batch',
        'status',
        'version_before',
        'version_after',
        'error_message',
        'started_at',
        'finished_at',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'tenant_id' => 'integer',
        'batch' => 'integer',
        'started_at' => 'integer',
        'finished_at' => 'integer',
        'deleted_at' => 'integer',
        'created_at' => 'integer',
        'updated_at' => 'integer',
    ];
}
