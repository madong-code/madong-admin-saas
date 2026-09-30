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
    title: '错误响应',
    description: '错误响应结构'
)]
class ErrorResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'error',
        description: '错误信息',
        type: 'string',
        example: '参数错误'
    )]
    public string $error;

    #[OA\Property(
        property: 'code',
        description: '错误码',
        type: 'string',
        example: 'VALIDATION_ERROR'
    )]
    public ?string $code = null;

    #[OA\Property(
        property: 'details',
        description: '错误详情',
        type: 'array',
        items: new OA\Items(type: 'string'),
        example: []
    )]
    public ?array $details = null;

    public function __construct(
        string  $error = '操作失败',
        ?string $code = null,
        ?array  $details = null
    )
    {
        $this->error   = $error;
        $this->code    = $code;
        $this->details = $details;
    }

    public static function make(
        string  $error,
        ?string $code = null,
        ?array  $details = null
    ): self
    {
        return new self($error, $code, $details);
    }

}
