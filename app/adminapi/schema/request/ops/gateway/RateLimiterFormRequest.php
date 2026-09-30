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

namespace app\adminapi\schema\request\ops\gateway;


use app\schema\request\BaseFormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '限访规则表单',
    description: '限访规则创建和编辑接口共用的表单请求参数'
)]
class RateLimiterFormRequest extends BaseFormRequest
{
    #[OA\Property(
        property: 'name',
        description: '规则名称',
        type: 'string',
        example: 'customized'
    )]
    public string $name;

    #[OA\Property(
        property: 'match_type',
        description: '匹配类型',
        type: 'string',
        example: 'allow'
    )]
    public string $match_type;

    #[OA\Property(
        property: 'methods',
        description: '请求方法（多个用逗号分隔，如：GET,POST）',
        type: 'string',
        example: 'GET'
    )]
    public string $methods;

    #[OA\Property(
        property: 'path',
        description: '请求路径',
        type: 'string',
        example: '/system/menu'
    )]
    public string $path;

    #[OA\Property(
        property: 'limit_value',
        description: '限制次数',
        type: 'integer',
        example: 60
    )]
    public int $limit_value;

    #[OA\Property(
        property: 'period',
        description: '限制周期（秒）',
        type: 'integer',
        example: 1
    )]
    public int $period;

    #[OA\Property(
        property: 'enabled',
        description: '是否启用（0:禁用,1:启用）',
        type: 'integer',
        enum: [0, 1],
        example: 1
    )]
    public int $enabled;

    #[OA\Property(
        property: 'message',
        description: '触发限制时提示消息',
        type: 'string',
        example: '请求频繁'
    )]
    public string $message;
}
