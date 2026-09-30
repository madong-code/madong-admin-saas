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
    title: '菜单结构响应模型【Art】',
    description: 'Madong-Art-Ui系统菜单数据结构'
)]
class ArtMenuResponse
{
    /**
     * @var ArtMenuResponse[] 子菜单列表（同步更新子菜单类型）
     */
    #[OA\Property(description: '子菜单', type: 'array', items: new OA\Items(ref: '#/components/schemas/ArtMenuSchema'))]
    public array $children = [];

    public function __construct(
        #[OA\Property(description: '菜单名称', type: 'string')]
        public string              $name,

        #[OA\Property(description: '路由路径', type: 'string')]
        public string              $path,

        #[OA\Property(description: '组件路径', type: 'string')]
        public string|null         $components,

        // 关联元数据 schema（同步修改为 MenuMetaSchema）
        #[OA\Property( ref: ArtMenuMetaResponse::class,description: '元数据')]
        public ArtMenuMetaResponse $meta,

        ?array                     $children
    ) {
        if ($children !== null) {
            // 递归转换子菜单为 ArtMenuSchema 对象
            $this->children = array_map(
                fn(array $child) => new self(
                    $child['name'],
                    $child['path'],
                    $child['component'] ?? null,
                    new ArtMenuMetaResponse(
                        order: $child['meta']['order']??0,
                        title: $child['meta']['title']??'',
                        icon: $child['meta']['icon']??'',
                        roles: $child['meta']['roles'] ?? null,
                        keepAlive: $child['meta']['keepAlive'] ?? null,
                        fixedTab: $child['meta']['fixedTab'] ?? null,
                        authList: $child['meta']['authList'] ?? null
                    ),
                    $child['children'] ?? null
                ),
                $children
            );
        }
    }
}
