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

use app\model\member\MemberTag;
use core\foundation\base\BaseValidate;
use Illuminate\Validation\Rule;

/**
 * 会员标签验证器
 */
class MemberTagValidate extends BaseValidate
{
    /**
     * 验证规则
     */
    public function rules(): array
    {
        return [
            'name'   => ['required', 'max:50', Rule::unique(MemberTag::class, 'name')->ignore(request()->route->param('id'), 'id')],
            'color'  => 'max:20',
            'sort'   => 'integer|min:0',
            'status' => 'in:0,1',
        ];
    }

    /**
     * 验证消息
     */
    protected array $messages = [
        'name.required' => '标签名称不能为空',
        'name.max'     => '标签名称不能超过50个字符',
        'name.unique'  => '标签名称已存在',
        'color.max'    => '标签颜色不能超过20个字符',
        'sort.integer' => '排序必须为整数',
        'sort.min'     => '排序不能小于0',
        'status.in'    => '状态值不正确',
    ];

    /**
     * 验证场景
     */
    protected array $scenes = [
        'store'  => ['name', 'color', 'sort', 'status'],
        'update' => ['name', 'color', 'sort', 'status'],
    ];

}