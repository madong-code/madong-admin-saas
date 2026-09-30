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

namespace app\adminapi\schema\request\system\org;

use app\schema\request\BaseFormRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '部门表单',
    description: '部门创建和编辑接口共用的表单请求参数'
)]
class DeptFormRequest extends BaseFormRequest
{
    #[OA\Property(
        description: '部门ID',
        type: 'string',
        example: '1',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string')]
    public string|int|null $id;

    #[OA\Property(
        description: '父部门ID',
        type: 'string',
        example: '0',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string')]
    public string $parent_id;

    #[OA\Property(
        description: '部门名称',
        type: 'string',
        example: '开发部',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:50|unique:sys_dept,name,' . 'id' . ',id')]
    public string $name;

    #[OA\Property(
        description: '部门编码',
        type: 'string',
        example: 'dev',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:50|alpha_dash|unique:sys_dept,code,' . 'id' . ',id')]
    public string $code;

    #[OA\Property(
        description: '部门领导ID列表',
        type: 'array',
        items: new OA\Items(type: 'integer'),
        example: [1, 2],
        nullable: true
    )]
    #[ValidationRules(rules: 'array|nullable')]
    public ?array $leader_id_list = null;

    #[OA\Property(
        description: '联系电话',
        type: 'string',
        example: '13800138000',
        nullable: true
    )]
    #[ValidationRules(rules: 'phone|nullable')]
    public ?string $phone = null;

    #[OA\Property(
        description: '状态(0:禁用,1:启用)',
        type: 'integer',
        enum: [0, 1],
        example: 1,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|in:0,1')]
    public int $enabled;

    #[OA\Property(
        description: '排序号',
        type: 'integer',
        example: 100,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|integer|min:0|max:999')]
    public int $sort;
}
