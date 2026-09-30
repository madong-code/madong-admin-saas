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

use Illuminate\Contracts\Support\Arrayable;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'API响应结构',
    description: '系统统一API响应数据格式'
)]
class Result implements Arrayable
{
    public function __construct(
        #[OA\Property(ref: 'ResultCode', title: '响应码')]
        public ResultCode $code = ResultCode::SUCCESS,
        #[OA\Property(title: '响应消息', type: 'string', example: 'success')]
        public ?string    $msg = null,
        #[OA\Property(title: '响应数据', example: [])]
        public mixed      $data = []
    )
    {
        if ($this->code === ResultCode::SUCCESS) {
            $this->msg = $this->msg ?? 'success';
        }
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'msg'  => $this->msg,
            'data' => $this->data,
        ];
    }
}
