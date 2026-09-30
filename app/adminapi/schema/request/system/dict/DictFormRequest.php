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

namespace app\adminapi\schema\request\system\dict;

use app\schema\request\BaseFormRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '字典表单',
    description: '字典创建和编辑接口共用的表单请求参数'
)]
class DictFormRequest extends BaseFormRequest
{
    #[OA\Property(
        description: '字典编码',
        type: 'string',
        example: 'status',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:50|unique:sys_dict,code')]
    public string $code;

    #[OA\Property(
        description: '字典名称',
        type: 'string',
        example: '状态字典',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:50|unique:sys_dict,name')]
    public string $name;

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
        description: '排序号',
        type: 'integer',
        example: 100,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|integer|min:0|max:999')]
    public int $sort;

    #[OA\Property(
        description: '备注',
        type: 'string',
        example: '系统状态字典',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:255|nullable')]
    public ?string $remark = null;
}
