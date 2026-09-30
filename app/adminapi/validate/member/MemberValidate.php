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

use app\model\member\Member;
use core\foundation\base\BaseValidate;
use Illuminate\Validation\Rule;

/**
 * 会员验证器
 */
class MemberValidate extends BaseValidate
{
    /**
     * 验证规则
     */
    public function rules(): array
    {
        $id = request()->route->param('id');

        return [
            'username' => ['required', 'max:50', Rule::unique(Member::class, 'username')->ignore($id, 'id')],
            'email'    => ['email', 'max:100'],
            'phone'    => ['max:20'],
            'password' => 'required|min:6|max:60',
            'nickname' => 'max:50',
            'level_id' => 'integer|min:1',
            'points'   => 'integer|min:0',
            'balance'  => 'number|min:0',
            'gender'   => 'in:0,1,2',
            'birthday' => 'date',
            'enabled'   => 'in:0,1',
        ];
    }

    /**
     * 验证消息
     */
    protected array $messages = [
        'username.required' => '用户名不能为空',
        'username.max'      => '用户名不能超过50个字符',
        'username.unique'   => '用户名已存在',
        'email.email'       => '邮箱格式不正确',
        'email.max'         => '邮箱不能超过100个字符',
        'email.unique'      => '邮箱已存在',
        'phone.max'         => '手机号格式不正确',
        'phone.unique'      => '手机号已存在',
        'password.required' => '密码不能为空',
        'password.min'      => '密码不能少于6个字符',
        'password.max'      => '密码不能超过60个字符',
        'nickname.max'      => '昵称不能超过50个字符',
        'level_id.integer'  => '等级ID必须为整数',
        'level_id.min'      => '等级ID不能小于1',
        'points.integer'    => '积分必须为整数',
        'points.min'        => '积分不能小于0',
        'balance.number'    => '余额必须为数字',
        'balance.min'       => '余额不能小于0',
        'gender.in'         => '性别值不正确',
        'birthday.date'     => '生日格式不正确',
        'enabled.in'         => '状态值不正确',
    ];

    /**
     * 验证场景
     */
    protected array $scenes = [
        'store'  => [
            'username',
            'email',
            'phone',
            'password',
            'nickname',
            'level_id',
            'points',
            'balance',
            'gender',
            'birthday',
            'enabled',
        ],
        'update' => [
            'username',
            'email',
            'phone',
            'nickname',
            'level_id',
            'points',
            'balance',
            'gender',
            'birthday',
            'enabled',
        ],
    ];

    /**
     * 创建验证
     */
    public function sceneCreate(): MemberValidate
    {
        return $this->only($this->scene['create']);
    }

    /**
     * 更新验证
     */
//    public function sceneUpdate(): MemberValidate
//    {
//        return $this->only($this->scene['update'])
//            ->append('id', 'require|integer|min:1')
//            ->remove('password', 'require');
//    }

    /**
     * 重置密码验证
     */
    public function sceneResetPassword(): MemberValidate
    {
        return $this->only([])
            ->append('id', 'require|integer|min:1')
            ->append('password', 'require|min:6|max:20');
    }

    /**
     * 调整积分验证
     */
    public function sceneAdjustPoints(): MemberValidate
    {
        return $this->only([])
            ->append('id', 'require|integer|min:1')
            ->append('points', 'require|integer');
    }

    /**
     * 批量删除验证
     */
    public function sceneBatchDelete(): MemberValidate
    {
        return $this->only([])
            ->append('ids', 'require|array|min:1');
    }

    /**
     * 状态更新验证
     */
    public function sceneStatus(): MemberValidate
    {
        return $this->only([])
            ->append('id', 'require|integer|min:1')
            ->append('status', 'require|in:0,1');
    }
}