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
namespace app\adminapi\schema\response\system;

use madong\swagger\schema\BaseResponseDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '部门详情响应模型',
    description: '部门详情接口的返回数据结构'
)]
class DeptResponse extends BaseResponseDTO
{
    #[OA\Property(description: '部门ID', type: 'string', example: '246964377436553216')]
    public string $id;

    #[OA\Property(description: '父部门ID', type: 'string', example: null, nullable: true)]
    public ?string $pid = null;

    #[OA\Property(description: '部门层级', type: 'integer', example: null, nullable: true)]
    public ?int $level = null;

    #[OA\Property(description: '部门编码', type: 'string', example: 'dev')]
    public string $code;

    #[OA\Property(description: '部门名称', type: 'string', example: '测试')]
    public string $name;

    #[OA\Property(description: '主要负责人ID', type: 'string', example: null, nullable: true)]
    public ?string $main_leader_id = null;

    #[OA\Property(description: '联系电话', type: 'string', example: null, nullable: true)]
    public ?string $phone = null;

    #[OA\Property(description: '状态(1:启用,0:禁用)', type: 'integer', enum: [0, 1], example: 1)]
    public int $enabled;

    #[OA\Property(description: '排序号', type: 'integer', example: 1)]
    public int $sort;

    #[OA\Property(description: '创建人ID', type: 'integer', example: 1)]
    public int $created_by;

    #[OA\Property(description: '更新人ID', type: 'integer', example: null, nullable: true)]
    public int|string|null $updated_by = null;

    #[OA\Property(description: '创建时间', type: 'string', format: 'date-time', example: '2025-11-12T03:48:15.000000Z')]
    public string $created_at;

    #[OA\Property(description: '更新时间', type: 'string', format: 'date-time', example: '2025-11-12T03:48:15.000000Z')]
    public string $updated_at;

    #[OA\Property(description: '删除时间', type: 'string', format: 'date-time', example: null, nullable: true)]
    public ?string $deleted_at = null;

    #[OA\Property(description: '备注', type: 'string', example: 'sdf', nullable: true)]
    public ?string $remark = null;

    #[OA\Property(description: '创建时间(格式化)', type: 'string', example: '2025-11-12 11:48:15')]
    public string $created_date;

    #[OA\Property(description: '更新时间(格式化)', type: 'string', example: '2025-11-12 11:48:15')]
    public string $updated_date;

    #[OA\Property(
        description: '部门领导列表',
        type: 'array',
        items: new OA\Items(ref: AdminResponse::class)
    )]
    public array $leader;
}
