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
namespace app\adminapi\schema\request\system;


use app\schema\request\BaseFormRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '角色表单',
    description: '系统角色创建和编辑接口共用的表单请求参数'
)]
class RoleFormRequest extends BaseFormRequest
{
    #[OA\Property(
        description: '角色名称',
        type: 'string',
        maxLength: 30,
        example: '管理员'
    )]
    #[ValidationRules(rules: 'required|string|max:30|unique:sys_role,name')]
    public string $name;

    #[OA\Property(
        description: '角色编码',
        type: 'string',
        maxLength: 100,
        example: 'ADMIN'
    )]
    #[ValidationRules(rules: 'required|string|max:100|unique:sys_role,code')]
    public string $code;

    #[OA\Property(
        description: '数据范围(1:全部数据 2:自定义 3:本部门 4:本部门及以下 5:本人)',
        type: 'integer',
        enum: [1, 2, 3, 4, 5],
        example: 1
    )]
    #[ValidationRules(rules: 'required|integer|in:1,2,3,4,5')]
    public int $data_scope;

    #[OA\Property(
        description: '排序号',
        type: 'integer',
        example: 10,
    )]
    #[ValidationRules(rules: 'required|integer|between:0,999')]
    public int $sort;

    #[OA\Property(
        description: '状态(1:启用 0:禁用)',
        type: 'integer',
        enum: [0, 1],
        example: 1
    )]
    #[ValidationRules(rules: 'required|integer|in:0,1')]
    public int $enabled;

    #[OA\Property(
        description: '权限ID列表',
        type: 'array',
        items: new OA\Items(type: 'integer'),
        example: [1, 2, 3]
    )]
    #[ValidationRules(rules: 'array|nullable|each:integer|each:min:1')]
    public ?array $permissions = [];

    #[OA\Property(
        description: '备注',
        type: 'string',
        maxLength: 255,
        example: '系统管理员角色'
    )]
    #[ValidationRules(rules: 'string|max:255|nullable')]
    public ?string $remark = null;
}
