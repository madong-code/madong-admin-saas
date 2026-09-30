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
    title: '角色详情响应模型',
    description: '角色详情接口的返回数据结构'
)]
class RoleResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'id',
        description: '角色ID',
        type: 'string',
        example: '239312652730916864'
    )]
    public string $id;

    #[OA\Property(
        property: 'pid',
        description: '父角色ID',
        type: 'integer',
        example: 0
    )]
    public int $pid;

    #[OA\Property(
        property: 'name',
        description: '角色名称',
        type: 'string',
        example: '测试'
    )]
    public string $name;

    #[OA\Property(
        property: 'code',
        description: '角色编码',
        type: 'string',
        example: 'dev'
    )]
    public string $code;

    #[OA\Property(
        property: 'is_super_admin',
        description: '是否超级管理员(0=否,1=是)',
        type: 'integer',
        enum: [0, 1],
        example: 0
    )]
    public int $is_super_admin;

    #[OA\Property(
        property: 'role_type',
        description: '角色类型',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $role_type = null;

    #[OA\Property(
        property: 'data_scope',
        description: '数据范围(1:全部数据 2:自定义 3:本部门 4:本部门及以下 5:本人)',
        type: 'integer',
        enum: [1, 2, 3, 4, 5],
        example: 1
    )]
    public int $data_scope;

    #[OA\Property(
        property: 'enabled',
        description: '状态(1:启用 0:禁用)',
        type: 'integer',
        enum: [0, 1],
        example: 1
    )]
    public int $enabled;

    #[OA\Property(
        property: 'sort',
        description: '排序号',
        type: 'integer',
        example: 0
    )]
    public int $sort;

    #[OA\Property(
        property: 'remark',
        description: '备注',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $remark = null;

    #[OA\Property(
        property: 'created_by',
        description: '创建人ID',
        type: 'integer',
        example: 1
    )]
    public int $created_by;

    #[OA\Property(
        property: 'updated_by',
        description: '更新人ID',
        type: 'integer',
        example: null,
        nullable: true
    )]
    public ?int $updated_by = null;

    #[OA\Property(
        property: 'created_at',
        description: '创建时间',
        type: 'string',
        format: 'date-time',
        example: '2025-10-22T01:03:02.000000Z'
    )]
    public string $created_at;

    #[OA\Property(
        property: 'updated_at',
        description: '更新时间',
        type: 'string',
        format: 'date-time',
        example: '2025-10-22T01:03:02.000000Z'
    )]
    public string $updated_at;

    #[OA\Property(
        property: 'deleted_at',
        description: '删除时间',
        type: 'string',
        format: 'date-time',
        example: null,
        nullable: true
    )]
    public ?string $deleted_at = null;

    #[OA\Property(
        property: 'created_date',
        description: '创建日期（格式化）',
        type: 'string',
        example: '2025-10-22 09:03:02'
    )]
    public string $created_date;

    #[OA\Property(
        property: 'updated_date',
        description: '更新日期（格式化）',
        type: 'string',
        example: '2025-10-22 09:03:02'
    )]
    public string $updated_date;

    #[OA\Property(
        property: 'casbin',
        description: '角色权限列表',
        type: 'array',
        items: new OA\Items(
            properties: [
                new OA\Property(property: 'id', type: 'string', example: '239312654937120768'),
                new OA\Property(property: 'ptype', type: 'string', example: 'p'),
                new OA\Property(property: 'v0', type: 'string', example: 'role:239312652730916864'),
                new OA\Property(property: 'v1', type: 'string', example: 'domain:1'),
                new OA\Property(property: 'v2', type: 'string', example: 'menu:/'),
                new OA\Property(property: 'v3', type: 'string', example: '*'),
                new OA\Property(property: 'v4', type: 'string', example: 'get'),
                new OA\Property(property: 'v5', type: 'string', example: 'menu:232462703808479232'),
                new OA\Property(
                    property: 'pivot',
                    properties: [
                        new OA\Property(property: 'role_id', type: 'string', example: '239312652730916864'),
                        new OA\Property(property: 'role_casbin_id', type: 'string', example: 'role:239312652730916864')
                    ],
                    type: 'object'
                )
            ],
            type: 'object'
        )
    )]
    public array $casbin;
}
