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


use app\schema\request\BaseQueryRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '字典项列表查询请求',
    description: '字典项列表接口的查询过滤参数'
)]
class DictItemQueryRequest extends BaseQueryRequest
{
    #[OA\Property(
        description: '字典ID',
        type: 'string',
        example: '242218273104986112',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|nullable')]
    public ?string $dict_id = null;

    #[OA\Property(
        description: '选项标签',
        type: 'string',
        example: '所属分组',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:50|nullable')]
    public ?string $label = null;

    #[OA\Property(
        description: '选项值',
        type: 'string',
        example: 'default',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:50|nullable')]
    public ?string $value = null;

    #[OA\Property(
        description: '状态(0:禁用,1:启用)',
        type: 'integer',
        enum: [0, 1],
        example: 1,
        nullable: true
    )]
    #[ValidationRules(rules: 'in:0,1|nullable')]
    public ?int $enabled = null;

}
