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
namespace app\platform\validate\tenant;

use app\platform\validate\BaseValidate;

/**
 * 租户管理验证器
 */
class TenantValidate extends BaseValidate
{
    protected array $rules = [
        'id'              => 'required|integer',
        'name'            => 'required|string|min:2|max:100',
        'code'            => 'required|string|min:2|max:50|alphaDash',
        'database_mode'   => 'required|string|in:field,database',
        'db_setting_id'   => 'nullable|integer',
        'effective_mode'  => 'nullable|string|in:immediate,specified',
        'start_time'      => 'nullable|integer',
        'domain'          => 'nullable|string|max:191',
        'contact_name'    => 'nullable|string|max:50',
        'contact_phone'   => 'nullable|string|max:20',
        'contact_email'   => 'nullable|email|max:100',
        'contact_address' => 'nullable|string|max:255',
        'system_name'     => 'nullable|string|max:100',
        'status'          => 'required|string|in:active,suspended,cancelled',
        'expire_time'     => 'nullable|integer',
        'sort'            => 'nullable|integer',
        'plan_id'         => 'required|integer',
    ];

    protected array $messages = [
        'id.required'                 => '租户ID不能为空',
        'id.integer'                  => '租户ID必须是整数',
        'name.required'               => '租户名称不能为空',
        'name.string'                 => '租户名称必须是字符串',
        'name.min'                    => '租户名称长度不能少于2个字符',
        'name.max'                    => '租户名称长度不能超过100个字符',
        'code.required'               => '租户编码不能为空',
        'code.string'                 => '租户编码必须是字符串',
        'code.min'                    => '租户编码长度不能少于2个字符',
        'code.max'                    => '租户编码长度不能超过50个字符',
        'code.alphaDash'              => '租户编码只能包含字母、数字、下划线和短横线',
        'code.unique'                 => '租户编码已存在，请更换',
        'database_mode.required'      => '隔离模式不能为空',
        'database_mode.in'            => '隔离模式值无效',
        'db_setting_id.integer'       => '数据源ID必须是整数',
        'effective_mode.in'           => '生效方式值无效',
        'start_time.integer'          => '生效时间必须是秒级时间戳',
        'domain.string'               => '域名必须是字符串',
        'domain.max'                  => '域名长度不能超过191个字符',
        'contact_name.string'         => '联系人姓名必须是字符串',
        'contact_name.max'            => '联系人姓名最多50个字符',
        'contact_phone.string'        => '联系电话必须是字符串',
        'contact_phone.max'           => '联系电话最多20个字符',
        'contact_email.email'         => '请输入正确的邮箱地址',
        'contact_email.max'           => '邮箱最多100个字符',
        'contact_address.string'      => '联系地址必须是字符串',
        'contact_address.max'         => '联系地址最多255个字符',
        'system_name.string'          => '系统名称必须是字符串',
        'system_name.max'             => '系统名称最多100个字符',
        'status.required'             => '状态不能为空',
        'status.string'               => '状态必须是字符串',
        'status.in'                   => '状态值无效',
        'expire_time.integer'         => '到期时间必须是秒级时间戳',
        'sort.integer'                => '排序必须是整数',
    ];

    protected array $scenes = [
        'store' => [
            'name', 'code', 'database_mode', 'db_setting_id', 'effective_mode',
            'start_time', 'domain', 'contact_name', 'contact_phone', 'contact_email',
            'contact_address', 'system_name', 'status', 'expire_time', 'sort',
        ],
        'update' => [
            'id', 'name', 'code', 'database_mode', 'db_setting_id', 'effective_mode',
            'start_time', 'domain', 'contact_name', 'contact_phone', 'contact_email',
            'contact_address', 'system_name', 'status', 'expire_time', 'sort',
        ],
        'status' => ['id', 'status'],
        'bind_plan' => ['id', 'plan_id'],
    ];

    /**
     * store 场景：code 添加数据库唯一校验
     */
    public function sceneStore(): void
    {
        $this->only = $this->scenes['store'];
        $this->rules['code'] .= '|unique:saas_tenant,code';
    }
}
