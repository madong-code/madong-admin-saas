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

namespace app\schema\response;

use OpenApi\Attributes as OA;
use madong\swagger\schema\BaseResponseDTO;

#[OA\Schema(
    title: '树形数据响应',
    description: '树形结构数据响应，用于菜单、部门等层级数据'
)]
class TreeResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'id',
        description: '节点ID',
        type: 'string',
        example: 1
    )]
    public string $id;

    #[OA\Property(
        property: 'pid',
        description: '父节点ID',
        type: 'string',
        example: 0
    )]
    public string $pid;

    #[OA\Property(
        property: 'name',
        description: '节点名称',
        type: 'string',
        example: '系统管理'
    )]
    public string $name;

    #[OA\Property(
        property: 'children',
        description: '子节点列表',
        type: 'array',
        items: new OA\Items(),
        example: []
    )]
    public array $children = [];

    public function __construct(
        string|int $id = 0,
        string|int $parentId = 0,
        string     $name = '',
        array      $children = []
    )
    {
        $this->id       = (string)$id;
        $this->pid      = (string)$parentId;
        $this->name     = $name;
        $this->children = $children;
    }

    public static function make(
        int    $id,
        int    $parentId,
        string $name,
        array  $children = []
    ): self
    {
        return new self($id, $parentId, $name, $children);
    }

    public function hasChildren(): bool
    {
        return !empty($this->children);
    }
}
