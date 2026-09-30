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
    title: '岗位表单',
    description: '岗位创建和编辑接口共用的表单请求参数'
)]
class PostFormRequest extends BaseFormRequest
{


    #[OA\Property(
        property: 'dept_id',
        description: '部门ID',
        type: 'string',
        example: '246996721795072000',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string')]
    public string $dept_id;

    #[OA\Property(
        property: 'code',
        description: '岗位编码',
        type: 'string',
        example: 'SDF',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:30|unique:sys_post,code')]
    public string $code;

    #[OA\Property(
        property: 'name',
        description: '岗位名称',
        type: 'string',
        example: 'AD',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:50')]
    public string $name;

    #[OA\Property(
        property: 'sort',
        description: '排序号',
        type: 'integer',
        example: 0,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|integer|min:0|max:1000')]
    public int $sort;

    #[OA\Property(
        property: 'enabled',
        description: '状态（1启用 0禁用）',
        type: 'integer',
        enum: [0, 1],
        example: 1,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|integer|in:0,1')]
    public int $enabled;

    #[OA\Property(
        property: 'remark',
        description: '备注',
        type: 'string',
        example: 'ASDF',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:255|nullable')]
    public ?string $remark = null;
}
