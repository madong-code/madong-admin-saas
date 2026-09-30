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
    title: '租户插件详情响应',
    description: '租户插件详情，包含隔离模式信息'
)]
class TenantPluginDetailResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'key',
        description: '插件标识',
        type: 'string',
        example: 'demo-plugin'
    )]
    public string $key;

    #[OA\Property(
        property: 'title',
        description: '插件名称',
        type: 'string',
        example: '示例插件'
    )]
    public string $title;

    #[OA\Property(
        property: 'version',
        description: '版本号',
        type: 'string',
        example: '1.0.0'
    )]
    public string $version;

    #[OA\Property(
        property: 'description',
        description: '插件描述',
        type: 'string'
    )]
    public ?string $description = null;

    #[OA\Property(
        property: 'author',
        description: '作者',
        type: 'string'
    )]
    public ?string $author = null;

    #[OA\Property(
        property: 'icon',
        description: '图标',
        type: 'string'
    )]
    public ?string $icon = null;

    #[OA\Property(
        property: 'is_installed',
        description: '是否已安装(0=未安装 1=已安装)',
        type: 'integer',
        example: 1
    )]
    public int $is_installed;

    #[OA\Property(
        property: 'installed_at',
        description: '安装时间',
        type: 'integer',
        example: null,
        nullable: true
    )]
    public ?int $installed_at = null;

    #[OA\Property(
        property: 'expires_at',
        description: '过期时间',
        type: 'integer',
        example: null,
        nullable: true
    )]
    public ?int $expires_at = null;

    #[OA\Property(
        property: 'isolation_mode',
        description: '隔离模式: field/database',
        type: 'string',
        example: 'field'
    )]
    public string $isolation_mode;

    #[OA\Property(
        property: 'has_migrations',
        description: '是否有迁移文件',
        type: 'boolean',
        example: false
    )]
    public bool $has_migrations;

    #[OA\Property(
        property: 'migration_count',
        description: '迁移文件数量',
        type: 'integer',
        example: 0
    )]
    public int $migration_count;
}
