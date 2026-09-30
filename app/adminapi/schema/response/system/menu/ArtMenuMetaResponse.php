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

namespace app\adminapi\schema\response\system\menu;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '菜单元信息【Art】',
    description: 'Madong-Art-Ui菜单的元信息（排序、标题、图标、权限等）'
)]
class ArtMenuMetaResponse
{
    public function __construct(
        #[OA\Property(
            description: '排序序号（用于菜单排序）',
            type: 'integer',
            example: 10
        )]
        public int $order,

        #[OA\Property(
            description: '菜单标题（显示名称）',
            type: 'string',
            example: '系统设置'
        )]
        public string $title,

        #[OA\Property(
            description: '菜单图标（支持图标库类名或 Unicode）',
            type: 'string',
            example: 'ant-design:setting-outlined'
        )]
        public string $icon,

        #[OA\Property(
            description: '访问角色列表（为空表示无角色限制）',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ["R_SUPER", "R_ADMIN"]
        )]
        public ?array $roles = null,

        #[OA\Property(
            description: '是否缓存组件（true=缓存，false=不缓存）',
            type: 'boolean',
            example: true
        )]
        public ?bool $keepAlive = null,

        #[OA\Property(
            description: '是否固定标签页（true=固定，false=不固定）',
            type: 'boolean',
            example: false
        )]
        public ?bool $fixedTab = null,

        #[OA\Property(
            description: '权限列表（用于细粒度权限控制）',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'title', type: 'string',example: '超级管理员'),
                    new OA\Property(property: 'authMark', type: 'string', example: 'admin')
                ]
            ),
            example: [
                ['title' => '超级管理员', 'authMark' => 'admin']
            ]
        )]
        public ?array $authList = null
    ) {}
}
