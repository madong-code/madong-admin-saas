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
    title: '简单数据响应',
    description: '简单数据响应结构，用于返回单个值或简单对象'
)]
class SimpleResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'flag',
        description: '操作状态标识',
        type: 'boolean',
        example: true
    )]
    public bool $flag;

    #[OA\Property(
        property: 'message',
        description: '操作消息',
        type: 'string',
        example: '操作成功'
    )]
    public ?string $message = null;

    #[OA\Property(
        property: 'data',
        description: '附加数据',
        type: 'object',
        example: []
    )]
    public mixed $data = null;

    public function __construct(
        bool    $flag = true,
        ?string $message = null,
        mixed   $data = null
    )
    {
        $this->flag    = $flag;
        $this->message = $message ?? ($flag ? '操作成功' : '操作失败');
        $this->data    = $data;
    }

    public static function success(?string $message = '操作成功', mixed $data = null): self
    {
        return new self(true, $message, $data);
    }

    public static function fail(?string $message = '操作失败', mixed $data = null): self
    {
        return new self(false, $message, $data);
    }
}
