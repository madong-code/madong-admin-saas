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

namespace app\schema\request\common;

use OpenApi\Attributes as OA;
use madong\swagger\schema\BaseRequestDTO;

#[OA\Schema(
    title: '通用数据请求',
    description: '包含通用data字段的请求结构，用于传递任意业务数据'
)]
class DataRequest extends BaseRequestDTO
{
    #[OA\Property(
        property: 'data',
        description: '业务数据',
        type: 'object',
        example: []
    )]
    public mixed $data = null;

    public function __construct(mixed $data = null)
    {
        $this->data = $data;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * 获取数据并转换为数组
     */
    public function getDataAsArray(): array
    {
        return is_array($this->data) ? $this->data : (array) $this->data;
    }

    /**
     * 从请求体创建实例
     */
    public static function fromBody(mixed $data): self
    {
        return new self($data);
    }

    /**
     * 检查是否有数据
     */
    public function hasData(): bool
    {
        return $this->data !== null;
    }

    /**
     * 检查数据是否为空
     */
    public function isEmpty(): bool
    {
        if ($this->data === null) {
            return true;
        }

        if (is_array($this->data)) {
            return empty($this->data);
        }

        if (is_object($this->data)) {
            return empty((array) $this->data);
        }

        return false;
    }
}
