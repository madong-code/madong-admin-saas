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
    title: '字典项表单',
    description: '字典项创建和编辑接口共用的表单请求参数'
)]
class DictItemFormRequest extends BaseFormRequest
{

    #[OA\Property(
        description: '字典ID',
        type: 'string',
        example: '242218273104986112',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string')]
    public string $dict_id;

    #[OA\Property(
        description: '选项标签',
        type: 'string',
        example: '所属分组',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:50')]
    public string $label;

    #[OA\Property(
        description: '选项值',
        type: 'string',
        example: 'default',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:50|unique:sys_dict_item,value,dict_id,' . 'id' . ',id')]
    public string $value;

    #[OA\Property(
        description: '排序号',
        type: 'integer',
        example: 1,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|integer|min:0|max:999')]
    public int $sort;

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
        description: '颜色值',
        type: 'string',
        example: '#4CAF50',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:20|nullable')]
    public ?string $color = null;

    #[OA\Property(
        description: '扩展信息',
        type: 'object',
        nullable: true,
        example: '{"icon": "el-icon-setting"}'
    )]
    #[ValidationRules(rules: 'array|nullable')]
    public ?array $ext = null;
}
