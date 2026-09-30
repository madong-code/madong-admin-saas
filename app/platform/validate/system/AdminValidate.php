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

namespace app\platform\validate\system;

use app\model\system\admin\Admin;
use core\foundation\base\BaseValidate;
use core\business\tenant\scope\TenantScope;
use Illuminate\Validation\Rule;

/**
 * 平台端用户管理验证器
 *
 * 与 adminapi 验证器不同：
 * - 平台端验证用户名唯一性时，需要绕过 TenantScope（跨所有租户检查）
 * - adminapi 验证器受 TenantScope 影响，只在当前租户范围内检查
 */
class AdminValidate extends BaseValidate
{
    public function rules(): array
    {
        $id = request()->route->param('id');
        return [
            'user_name'    => [
                'required',
                'max:50',
                'not_in:root',
                // 平台端：绕过 TenantScope 检查全局唯一性
                Rule::unique(Admin::class, 'user_name')
                    ->ignore($id, 'id')
                    ->where(function ($query) {
                        $query->withoutGlobalScope(TenantScope::class);
                    }),
            ],
            'real_name'    => 'required',
            'password'     => 'required|min:5|max:18',
            'dept_id'      => 'required',
            'mobile_phone' => 'required|mobile',
            'old_password' => 'required',
            'new_password' => 'required|min:5|max:18',
            'id'           => 'required',
        ];
    }

    protected array $messages = [
        'id.required'           => '缺少参数id',
        'user_name.required'    => '用户名必须填写',
        'user_name.max'         => '用户名最多不能超过18个字符',
        'user_name.unique'      => '用户名已被占用',
        'user_name.not_in'      => '用户名 "root" 为系统保留，不允许创建',
        'real_name.required'    => '姓名必须填写',
        'password.required'     => '密码必须填写',
        'password.min'          => '密码最少为5位',
        'password.max'          => '密码长度不能超过18位',
        'old_password.required' => '旧密码不能为空',
        'new_password.required' => '密码必须填写',
        'new_password.min'      => '密码最少为5位',
        'new_password.max'      => '密码长度不能超过18位',
        'dept_id'               => '部门必须填写',
        'mobile_phone.required' => '手机号码必须填写',
        'mobile_phone.mobile'   => '无效手机号码',
    ];

    protected array $scenes = [
        'store' => [
            'user_name',
            'password',
            'mobile_phone',
        ],
        'update' => [
            'user_name',
            'mobile_phone',
        ],
    ];
}
