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

namespace app\model\content\review;

use core\foundation\base\BaseModel;

/**
 * 审核操作审计日志（记录每次审核动作的操作用户、原因与时间）
 */
class ReviewLog extends BaseModel
{
    protected $table = 'sys_review_log';

    protected string $pk = 'id';

    protected $fillable = [
        'tenant_id',
        'review_id',
        'action',
        'operator_id',
        'reason',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'created_by' => 'integer',
        'review_id'  => 'integer',
    ];

    protected $dates = ['deleted_at'];
}
