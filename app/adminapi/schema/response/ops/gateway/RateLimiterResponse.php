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

namespace app\adminapi\schema\response\ops\gateway;

use madong\swagger\schema\BaseResponseDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RateLimiterSchema',
    title: "限访规则",
    description: "限访规则模型"
)]
class RateLimiterResponse extends BaseResponseDTO
{
    #[OA\Property(property: "id", description: "主键ID", type: "string", example: "247298887944531968")]
    public string $id;

    #[OA\Property(property: "name", description: "规则名称", type: "string", example: "customized")]
    public string $name;

    #[OA\Property(property: "match_type", description: "匹配类型", type: "string", example: "allow")]
    public string $match_type;

    #[OA\Property(property: "ip", description: "IP地址", type: "string", example: null, nullable: true)]
    public ?string $ip = null;

    #[OA\Property(property: "priority", description: "优先级", type: "integer", example: 100)]
    public int $priority;

    #[OA\Property(property: "methods", description: "请求方法", type: "string", example: "GET")]
    public string $methods;

    #[OA\Property(property: "path", description: "请求路径", type: "string", example: "/system/menu")]
    public string $path;

    #[OA\Property(property: "limit_type", description: "限制类型", type: "string", example: "count")]
    public string $limit_type;

    #[OA\Property(property: "limit_value", description: "限制值", type: "integer", example: 60)]
    public int $limit_value;

    #[OA\Property(
        property: "enabled",
        description: "是否启用(0:禁用,1:启用)",
        type: "integer",
        enum: [0, 1],
        example: 1
    )]
    public int $enabled;

    #[OA\Property(property: "message", description: "提示消息", type: "string", example: "请求频繁")]
    public string $message;

    #[OA\Property(property: "created_by", description: "创建人ID", type: "integer", example: 1)]
    public int $created_by;

    #[OA\Property(property: "updated_by", description: "更新人ID", type: "integer", example: null, nullable: true)]
    public ?int $updated_by = null;

    #[OA\Property(property: "created_at", description: "创建时间(UTC)", type: "string", format: "date-time", example: "2025-11-13T01:57:29.000000Z")]
    public string $created_at;

    #[OA\Property(property: "updated_at", description: "更新时间(UTC)", type: "string", format: "date-time", example: "2025-11-13T01:57:29.000000Z")]
    public string $updated_at;

    #[OA\Property(property: "created_date", description: "创建时间(本地)", type: "string", example: "2025-11-13 09:57:29")]
    public string $created_date;

    #[OA\Property(property: "updated_date", description: "更新时间(本地)", type: "string", example: "2025-11-13 09:57:29")]
    public string $updated_date;
}
