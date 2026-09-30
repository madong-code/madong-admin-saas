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
    schema: 'RateRestrictionsSchema',
    title: '限访名单',
    description: '限访名单数据模型'
)]
class RateRestrictionsResponse extends BaseResponseDTO
{
    #[OA\Property(property: "id", description: "主键ID", type: "string", example: "247306532294254592")]
    public string $id;

    #[OA\Property(property: "ip", description: "IP地址", type: "string", example: null, nullable: true)]
    public ?string $ip = null;

    #[OA\Property(property: "name", description: "名单名称", type: "string", example: "sss")]
    public string $name;

    #[OA\Property(
        property: "enabled",
        description: "是否启用(0:禁用,1:启用)",
        type: "integer",
        enum: [0, 1],
        example: 1
    )]
    public int $enabled;

    #[OA\Property(property: "priority", description: "优先级", type: "integer", example: 100)]
    public int $priority;

    #[OA\Property(property: "methods", description: "请求方法", type: "string", example: "GET")]
    public string $methods;

    #[OA\Property(property: "path", description: "请求路径", type: "string", example: "/system/menu")]
    public string $path;

    #[OA\Property(property: "message", description: "限制提示消息", type: "string", example: "限制访问")]
    public string $message;

    #[OA\Property(property: "start_time", description: "生效开始时间", type: "string", format: "date-time", example: null, nullable: true)]
    public ?string $start_time = null;

    #[OA\Property(property: "end_time", description: "生效结束时间", type: "string", format: "date-time", example: null, nullable: true)]
    public ?string $end_time = null;

    #[OA\Property(property: "created_at", description: "创建时间(UTC)", type: "string", format: "date-time", example: "2025-11-13T02:27:52.000000Z")]
    public string $created_at;

    #[OA\Property(property: "updated_at", description: "更新时间(UTC)", type: "string", format: "date-time", example: "2025-11-13T02:27:52.000000Z")]
    public string $updated_at;

    #[OA\Property(property: "created_by", description: "创建人ID", type: "integer", example: 1)]
    public int $created_by;

    #[OA\Property(property: "updated_by", description: "更新人ID", type: "integer", example: null, nullable: true)]
    public ?int $updated_by = null;

    #[OA\Property(property: "remark", description: "备注信息", type: "string", example: null, nullable: true)]
    public ?string $remark = null;

    #[OA\Property(property: "created_date", description: "创建时间(本地)", type: "string", example: "2025-11-13 10:27:52")]
    public string $created_date;

    #[OA\Property(property: "updated_date", description: "更新时间(本地)", type: "string", example: "2025-11-13 10:27:52")]
    public string $updated_date;

    #[OA\Property(property: "start_date", type: "string", nullable: true, description: "生效开始日期(本地)", example: null)]
    public ?string $start_date = null;

    #[OA\Property(property: "end_date", type: "string", nullable: true, description: "生效结束日期(本地)", example: null)]
    public ?string $end_date = null;
}
