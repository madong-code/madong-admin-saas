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
namespace app\model\member;

use core\foundation\base\BasePivot;

/**
 * 会员标签关系模型
 */
class MemberTagRelation extends BasePivot
{
    /**
     * 数据表名称
     */
    protected $table = 'member_tag_relation';

    /**
     * 可批量赋值的字段
     */
    protected $fillable = [
        'member_id',
        'tag_id',
    ];

    protected $casts = [
        'member_id' => 'string',
        'tag_id'    => 'string',
        'tenant_id' => 'string',
    ];

    /**
     * 关联会员
     */
    public function member(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }

    /**
     * 关联标签
     */
    public function tag(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MemberTag::class, 'tag_id', 'id');
    }
}