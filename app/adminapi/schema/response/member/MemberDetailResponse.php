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
namespace app\adminapi\schema\response\member;

use app\schema\response\Result;
use app\schema\response\ResultCode;
use OpenApi\Attributes as OA;

/**
 * 会员详情响应Schema
 */
#[OA\Schema(
    title: '会员详情响应模型',
    description: '会员详情接口的返回数据结构'
)]
class MemberDetailResponse extends Result
{
    public function __construct(
        #[OA\Property(ref: 'ResultCode', title: '响应码')]
        ResultCode $code = ResultCode::SUCCESS,
        #[OA\Property(title: '响应消息', type: 'string', example: '获取成功')]
        ?string $msg = null,
        #[OA\Property(
            title: '会员详情数据',
            ref: '#/components/schemas/MemberSchema'
        )]
        mixed $data = []
    ) {
        parent::__construct($code, $msg, $data);
    }
}