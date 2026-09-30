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
 * Official Website: https://madong.tech
 */

namespace app\adminapi\schema\request\system;


use app\schema\request\BaseFormRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '管理员表单',
    description: '管理员创建和编辑接口共用的表单请求参数'
)]
class AdminFormRequest extends BaseFormRequest
{
    #[OA\Property(
        description: '用户名',
        type: 'string',
        example: 'admin',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:50|unique:sys_admin,user_name')]
    public string $user_name;

    #[OA\Property(
        description: '真实姓名',
        type: 'string',
        example: '系统管理员',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:50')]
    public string $real_name;

    #[OA\Property(
        description: '密码',
        type: 'string',
        example: '123456',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|min:6|max:20')]
    public string $password;

    #[OA\Property(
        description: '手机号码',
        type: 'string',
        example: '18888888888',
        nullable: true
    )]
    #[ValidationRules(rules: 'mobile|nullable')]
    public ?string $mobile_phone = null;

    #[OA\Property(
        description: '邮箱',
        type: 'string',
        example: 'admin@example.com',
        nullable: true
    )]
    #[ValidationRules(rules: 'email|nullable')]
    public ?string $email = null;

    #[OA\Property(
        description: '状态(0:禁用,1:启用)',
        type: 'integer',
        enum: [0, 1],
        example: 1,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|in:0,1')]
    public int $status;

    #[OA\Property(
        description: '部门ID',
        type: 'integer',
        example: 1,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|integer|min:1')]
    public int $dept_id;

    #[OA\Property(
        description: '角色ID列表',
        type: 'array',
        items: new OA\Items(type: 'integer'),
        example: [1, 2],
        nullable: false
    )]
    #[ValidationRules(rules: 'required|array|min:1')]
    public array $role_id_list;

    #[OA\Property(
        description: '岗位ID列表',
        type: 'array',
        items: new OA\Items(type: 'integer'),
        example: [1],
        nullable: true
    )]
    #[ValidationRules(rules: 'array|nullable')]
    public ?array $post_id_list = null;
}
