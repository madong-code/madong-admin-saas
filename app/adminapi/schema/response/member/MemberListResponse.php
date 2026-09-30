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
 * 会员列表响应Schema
 */
#[OA\Schema(
    title: '会员列表响应模型',
    description: '会员列表接口的返回数据结构'
)]
class MemberListResponse extends Result
{
    public function __construct(
        #[OA\Property(ref: 'ResultCode', title: '响应码')]
        ResultCode $code = ResultCode::SUCCESS,
        #[OA\Property(title: '响应消息', type: 'string', example: '获取成功')]
        ?string $msg = null,
        #[OA\Property(
            title: '响应数据',
            properties: [
                new OA\Property(
                    property: 'list',
                    description: '会员列表',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/MemberSchema')
                ),
                new OA\Property(
                    property: 'total',
                    description: '总记录数',
                    type: 'integer',
                    example: 100
                ),
                new OA\Property(
                    property: 'page',
                    description: '当前页码',
                    type: 'integer',
                    example: 1
                ),
                new OA\Property(
                    property: 'limit',
                    description: '每页数量',
                    type: 'integer',
                    example: 15
                ),
                new OA\Property(
                    property: 'pages',
                    description: '总页数',
                    type: 'integer',
                    example: 7
                ),
            ],
            type: 'object'
        )]
        mixed $data = []
    ) {
        parent::__construct($code, $msg, $data);
    }
}