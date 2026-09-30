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
namespace app\model\sync;

use core\io\uuid\Snowflake;
use Illuminate\Database\Eloquent\Model;

/**
 * 同步模型基类
 *
 * 专用于跨租户数据同步的模型基类，特点：
 * - 无租户全局作用域（TenantScope）
 * - 无 created_by / updated_by 自动填充
 * - 无删除事件（回收站）
 * - 无软删除
 * - 无时间戳自动管理（同步操作不维护这些）
 * - 自动生成雪花ID
 */
class SyncModel extends Model
{
    /**
     * 雪花ID = 不自增
     */
    public $incrementing = false;

    /**
     * 雪花ID = string 类型
     */
    protected $keyType = 'string';

    /**
     * 同步不自动管理时间戳
     */
    public $timestamps = false;

    protected static function boot(): void
    {
        parent::boot();

        // 自动生成雪花ID
        static::creating(function ($model) {
            if (!$model->getIncrementing() && !isset($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string)Snowflake::generate();
            }
        });
    }
}
