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

namespace app\adminapi\schema\response\content\review;

use app\schema\response\Result;
use app\schema\response\ResultCode;
use OpenApi\Attributes as OA;

/**
 * 审核详情响应（聚合四视图：审核信息 / 表单 / 状态 / 事件）
 */
#[OA\Schema(
    title: '审核详情响应',
    description: '审核详情接口返回，包含审核信息、业务表单快照、当前状态、操作事件轨迹'
)]
class ReviewDetailResponse extends Result
{
    public function __construct(
        #[OA\Property(ref: 'ResultCode', title: '响应码')]
        ResultCode $code = ResultCode::SUCCESS,
        #[OA\Property(title: '响应消息', type: 'string', example: 'ok')]
        ?string $msg = null,
        #[OA\Property(
            title: '审核详情数据',
            properties: [
                new OA\Property(
                    property: 'is_archived',
                    description: '是否来自归档表（已终结记录）',
                    type: 'boolean',
                    example: false
                ),
                new OA\Property(
                    property: 'review_info',
                    description: '审核信息（基础字段映射：类型/申请人/状态文案等）',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'reviewable_type', type: 'string', example: 'comment'),
                        new OA\Property(property: 'reviewable_id', type: 'integer', example: 100),
                        new OA\Property(property: 'type_text', type: 'string', example: '评论'),
                        new OA\Property(property: 'applicant', type: 'string', example: '张三'),
                        new OA\Property(property: 'status', type: 'integer', example: 0),
                        new OA\Property(property: 'status_text', type: 'string', example: '待审核'),
                        new OA\Property(property: 'flow_type', type: 'string', example: 'simple'),
                        new OA\Property(property: 'reviewed_at', type: 'integer', nullable: true, example: 1753000000),
                        new OA\Property(property: 'created_at', type: 'integer', example: 1752990000),
                    ],
                    type: 'object'
                ),
                new OA\Property(
                    property: 'form',
                    description: '业务表单快照（extra_data，自包含，不依赖业务表）',
                    type: 'object',
                    example: ['title' => '评论标题', 'content' => '评论内容', 'applicant' => '张三']
                ),
                new OA\Property(
                    property: 'status',
                    description: '当前状态信息',
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 0),
                        new OA\Property(property: 'status_text', type: 'string', example: '待审核'),
                        new OA\Property(property: 'flow_type', type: 'string', example: 'simple'),
                        new OA\Property(property: 'flow_instance_id', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'reviewer_id', type: 'integer', nullable: true, example: null),
                        new OA\Property(property: 'reviewed_at', type: 'integer', nullable: true, example: null),
                        new OA\Property(property: 'reason', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'cancel_reason', type: 'string', nullable: true, example: null),
                    ],
                    type: 'object'
                ),
                new OA\Property(
                    property: 'events',
                    description: '操作事件轨迹（sys_review_log，按时间正序）',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 10),
                            new OA\Property(property: 'action', type: 'string', example: 'approve'),
                            new OA\Property(property: 'action_text', type: 'string', example: '通过'),
                            new OA\Property(property: 'operator_id', type: 'integer', nullable: true, example: 10001),
                            new OA\Property(property: 'reason', type: 'string', nullable: true, example: '内容合规'),
                            new OA\Property(property: 'created_at', type: 'integer', nullable: true, example: 1753000000),
                            new OA\Property(property: 'created_by', type: 'integer', nullable: true, example: 10001),
                        ],
                        type: 'object'
                    )
                ),
            ],
            type: 'object'
        )]
        mixed $data = []
    ) {
        parent::__construct($code, $msg, $data);
    }
}
