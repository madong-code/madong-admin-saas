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

namespace app\schema\request;

use OpenApi\Attributes as OA;
use madong\swagger\schema\BaseRequestDTO;

#[OA\Schema(
    title: '路径ID参数请求',
    description: '通过路径参数传递ID的请求结构，用于 /api/resource/{id} 类型的接口'
)]
class PathIdRequest extends BaseRequestDTO
{
    #[OA\Property(
        property: 'id',
        description: '资源ID（从路径参数获取）',
        type: 'string',
        example: 123,
    )]
    public int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * 验证ID是否有效
     */
    public function isValid(): bool
    {
        return $this->id > 0;
    }

    /**
     * 从路由参数创建实例
     */
    public static function fromRoute(int $id): self
    {
        return new self($id);
    }
}
