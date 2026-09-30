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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 审核记录模型（运行表：仅存储待审/审批中热数据）
 *
 * 已终结（通过/拒绝/取消）的记录由 ReviewService 实时搬移至 sys_review_archive，
 * 以保证本表数据量可控、后台列表不随历史堆积而卡顿。
 *
 * @property int    $id
 * @property int    $tenant_id
 * @property string $reviewable_type 审核类型键（如 comment/answer/question）
 * @property int    $reviewable_id   审核对象ID
 * @property int    $status          审核状态（ReviewStatus）
 * @property string $reason          审核意见
 * @property int    $reviewer_id     审核人ID
 * @property int    $reviewed_at     审核时间
 * @property array  $extra_data      业务快照（标题/内容/申请人等）
 * @property string $flow_type       审核模式 simple|workflow
 * @property string $flow_instance_id 外部审批流实例ID
 * @property string $cancel_reason   取消原因
 */
class Review extends BaseModel
{
    protected $table = 'sys_review';

    protected string $pk = 'id';

    protected $fillable = [
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
    ];

    protected $casts = [
        'extra_data'  => 'array',
        'status'      => 'integer',
        'reviewed_at' => 'integer',
    ];

    protected $dates = ['deleted_at'];

    /**
     * 状态文案
     */
    public function statusText(): string
    {
        return ReviewStatus::fromValue($this->status)->label();
    }

    /**
     * 审核人
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(\app\model\system\admin\Admin::class, 'reviewer_id', 'id');
    }

    /**
     * 审核对象（多态）
     *
     * reviewable_type 为 MorphMap 别名（主应用 config/morph_map.php + 插件 morph_map.php）。
     * 后台列表/详情优先用 extra_data 快照渲染，避免跨插件模型未加载时关联失败；
     * 业务侧公开过滤请用 HasReviewArchives::scopeApprovedArchive。
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo('reviewable', 'reviewable_type', 'reviewable_id');
    }

    /**
     * 审核操作审计日志
     */
    public function reviewLogs(): HasMany
    {
        return $this->hasMany(ReviewLog::class, 'review_id', 'id');
    }

    /**
     * 是否为外部审批流模式
     */
    public function isWorkflowMode(): bool
    {
        return $this->flow_type === 'workflow';
    }

    /**
     * 是否处于外部审批流锁定（普通管理员不可本地审批，仅超管可超审批）
     */
    public function isWorkflowLocked(): bool
    {
        return $this->flow_type === 'workflow';
    }

    /**
     * 是否已终结（已搬离运行表）
     */
    public function isTerminated(): bool
    {
        return in_array((int)$this->status, [
            ReviewStatus::APPROVED->value,
            ReviewStatus::REJECTED->value,
            ReviewStatus::CANCELED->value,
        ], true);
    }
}
