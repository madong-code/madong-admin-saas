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

use app\enum\common\QueueMessageStatusEnum;
use core\io\uuid\Snowflake;
use Illuminate\Database\Eloquent\Model;

/**
 * 队列消息记录模型
 *
 * 对应 saas_queue_message 表，用于记录队列任务的执行状态。
 * 任务完成后自动删除记录（auto-destroy），避免数据堆积。
 */
class QueueMessage extends Model
{
    /**
     * 表名
     */
    protected $table = 'saas_queue_message';

    /**
     * 主键类型
     */
    protected $primaryKey = 'id';

    /**
     * 雪花ID = 不自增
     */
    public $incrementing = false;

    /**
     * 雪花ID = string 类型
     */
    protected $keyType = 'string';

    /**
     * 使用 Eloquent 自动时间戳，以 Unix 时间戳（整型）存储
     */
    public $timestamps = true;
    protected $dateFormat = 'U';

    protected $fillable = [
        'id',
        'queue_name',
        'message_id',
        'status',
        'type',
        'topic',
        'payload',
        'result',
        'error_msg',
        'retry_count',
        'max_retry',
        'processed_at',
    ];


    protected $casts = [
        'payload' => 'array',
        'result'  => 'array',
        'id'      => 'string',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            // 自动生成雪花ID
            if (!isset($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string)Snowflake::generate();
            }
            // 默认状态
            if (empty($model->status)) {
                $model->status = QueueMessageStatusEnum::PENDING->value;
            }
            // 默认最大重试次数
            if (empty($model->max_retry)) {
                $model->max_retry = 3;
            }
        });
    }
}
