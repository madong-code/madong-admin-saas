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

use app\enum\review\ReviewStatus;
use core\foundation\base\BaseModel;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 审核归档模型（已终结记录的冷数据表）
 *
 * 运行表 sys_review 终结后由 ReviewService 实时复制至此，运行表软删，
 * 保证运行表只保留待审/审批中热数据。
 *
 * reviewable 多态依赖 MorphMap（主应用 + 插件 morph_map.php 合并注册）。
 */
class ReviewArchive extends BaseModel
{
    protected $table = 'sys_review_archive';

    protected string $pk = 'id';

    protected $fillable = [
        'id',
        'tenant_id',
        'reviewable_type',
        'reviewable_id',
        'status',
        'reason',
        'reviewer_id',
        'reviewed_at',
        'extra_data',
        'flow_type',
        'flow_instance_id',
        'cancel_reason',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'archived_at',
    ];

    protected $casts = [
        'extra_data'  => 'array',
        'status'      => 'integer',
        'reviewed_at' => 'integer',
        'archived_at' => 'integer',
    ];

    protected $dates = ['deleted_at'];

    public function statusText(): string
    {
        return ReviewStatus::fromValue($this->status)->label();
    }

    /**
     * 审核对象（多态，别名来自 MorphMap）
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo('reviewable', 'reviewable_type', 'reviewable_id');
    }
}
