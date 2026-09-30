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

namespace app\model\content\review\concerns;

use app\enum\review\ReviewStatus;
use app\model\content\review\Review;
use app\model\content\review\ReviewArchive;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * 可审核业务模型：基于 MorphMap 的审核归档多态关联
 *
 * 约定：
 * - reviewable_type 使用模型 getMorphClass()（MorphMap 别名，如 question/answer/comment）
 * - 公开渲染以「最新归档」为权威：仅当最新归档 status=APPROVED 时可见
 * - 业务模型需在 config/morph_map.php 或插件 morph_map.php 中注册
 *
 * 用法：
 *   use HasReviewArchives;
 *   Question::query()->approvedArchive()->get();
 *   $q->with(['answers' => fn ($q) => $q->approvedArchive()]);
 */
trait HasReviewArchives
{
    /**
     * 运行中审核单（热数据）
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * 审核归档记录（冷数据，公开权威）
     */
    public function reviewArchives(): MorphMany
    {
        return $this->morphMany(ReviewArchive::class, 'reviewable');
    }

    /**
     * 最新一条审核归档
     */
    public function latestReviewArchive(): MorphOne
    {
        return $this->morphOne(ReviewArchive::class, 'reviewable')->latestOfMany('id');
    }

    /**
     * 公开可见：最新归档必须为 APPROVED
     *
     * 走 Eloquent 关联（latestReviewArchive / morph），自动处理：
     * - 表前缀（如 md_sys_review_archive）
     * - MorphMap 别名（getMorphClass）
     * - 租户库连接
     *
     * 注意：勿对 getTable() 结果手写 from/raw SQL，前缀库下会查到无前缀表名。
     */
    public function scopeApprovedArchive(Builder $query): Builder
    {
        return $query->whereHas('latestReviewArchive', function (Builder $q) {
            $q->where('status', ReviewStatus::APPROVED->value);
        });
    }

    /**
     * 是否公开可见（最新归档 APPROVED）
     */
    public function isArchiveApproved(): bool
    {
        $latest = $this->relationLoaded('latestReviewArchive')
            ? $this->latestReviewArchive
            : $this->latestReviewArchive()->first();

        return $latest !== null
            && (int) $latest->status === ReviewStatus::APPROVED->value;
    }
}
