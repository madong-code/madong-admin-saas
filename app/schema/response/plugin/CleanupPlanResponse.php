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

namespace app\schema\response\plugin;

use madong\swagger\schema\BaseResponseDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '卸载清理计划响应',
    description: '插件卸载前预览的清理计划'
)]
class CleanupPlanResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'mode',
        description: '隔离模式: field/database',
        type: 'string',
        example: 'field'
    )]
    public string $mode;

    #[OA\Property(
        property: 'items',
        description: '清理操作列表',
        type: 'array',
        items: new OA\Items(
            properties: [
                new OA\Property(property: 'action', description: '操作名称', type: 'string'),
                new OA\Property(property: 'detail', description: '操作详情', type: 'string'),
                new OA\Property(property: 'level', description: '安全级别: safe/caution/danger', type: 'string'),
            ],
            type: 'object'
        )
    )]
    public array $items;
}
