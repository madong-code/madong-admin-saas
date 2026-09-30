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
 * 平台队列消息模型
 *
 * @property int    $id
 * @property string $queue_name   队列名称
 * @property string $message_id   消息ID
 * @property string $status       状态
 * @property string $type         消息类型
 * @property string $topic        主题
 * @property string $payload      消息内容(JSON)
 * @property string $result       处理结果(JSON)
 * @property string $error_msg    错误信息
 * @property int    $retry_count  重试次数
 * @property int    $max_retry    最大重试次数
 * @property int    $created_at   创建时间戳
 * @property int    $processed_at 处理完成时间戳
 * @property int    $updated_at   更新时间戳
 */
class QueueMessage extends SystemModel
{
    protected $table = 'saas_queue_message';
    protected $primaryKey = 'id';
    public $timestamps = false;


    protected $casts = [
        'retry_count' => 'integer',
        'max_retry'   => 'integer',
        'payload'     => 'json',
        'result'      => 'json',
    ];

    /** 待处理 */
    const STATUS_PENDING = 'pending';
    /** 处理中 */
    const STATUS_PROCESSING = 'processing';
    /** 成功 */
    const STATUS_SUCCESS = 'success';
    /** 失败 */
    const STATUS_FAILED = 'failed';
    /** 死信 */
    const STATUS_DEAD = 'dead';
}
