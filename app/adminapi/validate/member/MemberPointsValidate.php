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
namespace app\adminapi\validate\member;

use core\foundation\base\BaseValidate;

/**
 * 会员积分验证器
 */
class MemberPointsValidate extends BaseValidate
{
    /**
     * 验证规则
     */
    public function rules(): array
    {
        return [
            'member_id' => 'required|integer|gt:0',
            'points' => 'required|integer|gt:0',
            'type' => 'required|integer|in:1,2,3',
            'source' => 'nullable|string|max:255',
            'remark' => 'nullable|string|max:255',
            'operator' => 'nullable|string|max:50',
        ];
    }

    /**
     * 验证消息
     */
    protected array $messages = [
        'member_id.required' => '会员ID不能为空',
        'member_id.integer' => '会员ID必须为整数',
        'member_id.gt' => '会员ID必须大于0',
        'points.required' => '积分数量不能为空',
        'points.integer' => '积分数量必须为整数',
        'points.gt' => '积分数量必须大于0',
        'type.required' => '积分类型不能为空',
        'type.integer' => '积分类型必须为整数',
        'type.in' => '积分类型只能是1（增加）、2（减少）或3（调整）',
        'source.max' => '积分来源不能超过255个字符',
        'remark.max' => '备注不能超过255个字符',
        'operator.max' => '操作人不能超过50个字符',
    ];

    /**
     * 验证场景
     */
    protected array $scenes = [
        'store' => [
            'member_id',
            'points',
            'type',
            'source',
            'remark',
            'operator',
        ],
    ];

}