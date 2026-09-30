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
 * 会员统计响应Schema
 */
#[OA\Schema(
    title: '会员统计响应模型',
    description: '会员统计接口的返回数据结构'
)]
class MemberStatisticsResponse extends Result
{
    public function __construct(
        #[OA\Property(ref: 'ResultCode', title: '响应码')]
        ResultCode $code = ResultCode::SUCCESS,
        #[OA\Property(title: '响应消息', type: 'string', example: '获取成功')]
        ?string $msg = null,
        #[OA\Property(
            title: '统计结果数据',
            properties: [
                new OA\Property(
                    property: 'total',
                    description: '总会员数',
                    type: 'integer',
                    example: 1000
                ),
                new OA\Property(
                    property: 'today',
                    description: '今日新增',
                    type: 'integer',
                    example: 10
                ),
                new OA\Property(
                    property: 'enabled',
                    description: '正常会员',
                    type: 'integer',
                    example: 950
                ),
                new OA\Property(
                    property: 'disabled',
                    description: '禁用会员',
                    type: 'integer',
                    example: 50
                ),
            ],
            type: 'object'
        )]
        mixed $data = []
    ) {
        parent::__construct($code, $msg, $data);
    }
}