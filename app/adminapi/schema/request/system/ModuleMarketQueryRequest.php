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

use app\schema\request\BaseQueryRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '模块市场查询请求',
    description: '远程获取模块市场列表的查询过滤参数'
)]
class ModuleMarketQueryRequest extends BaseQueryRequest
{
    #[OA\Property(
        description: '模块类型（module: 系统模块, plugin: 插件）',
        type: 'string',
        enum: ['module', 'plugin'],
        nullable: true
    )]
    #[ValidationRules(rules: 'in:module,plugin|nullable')]
    public ?string $type = null;

    #[OA\Property(
        description: '状态(0:禁用,1:启用)',
        type: 'integer',
        enum: [0, 1],
        nullable: true
    )]
    #[ValidationRules(rules: 'in:0,1|nullable')]
    public ?int $status = null;

    #[OA\Property(
        description: '搜索关键词',
        type: 'string',
        maxLength: 50,
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:50|nullable')]
    public ?string $keyword = null;

    #[OA\Property(
        description: '是否已安装(0:未安装,1:已安装)',
        type: 'integer',
        enum: [0, 1],
        nullable: true
    )]
    #[ValidationRules(rules: 'in:0,1|nullable')]
    public ?int $installed = null;

}