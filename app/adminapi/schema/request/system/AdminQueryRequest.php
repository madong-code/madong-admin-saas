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


namespace app\adminapi\schema\request\system;


use app\schema\request\BaseQueryRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SysAdminQueryParam',
    title: '用户列表查询请求',
    description: '用户列表接口的查询过滤参数'
)]
class AdminQueryRequest extends BaseQueryRequest
{
    #[OA\Property(
        description: '返回数据格式(normal|tree|table_tree|select)',
        type: 'string',
        default: 'normal',
        enum: ['normal', 'tree', 'table_tree', 'select'],
        example: 'normal'
    )]
    public string $format = 'normal';


    #[OA\Property(
        description: '查询字段',
        type: 'string',
        default: '*',
        example: 'id,user_name,real_name'
    )]
    public string $field = '*';

    #[OA\Property(
        description: '排序字段',
        type: 'string',
        default: 'create_time',
        example: 'create_time desc'
    )]
    public string $order = 'create_time';

    #[OA\Property(
        description: '用户名',
        type: 'string',
        example: 'admin',
        nullable: true
    )]
    public ?string $user_name = null;

    #[OA\Property(
        description: '真实姓名',
        type: 'string',
        example: '管理员',
        nullable: true
    )]
    public ?string $real_name = null;

    #[OA\Property(
        description: '手机号码',
        type: 'string',
        example: '18888888888',
        nullable: true
    )]
    public ?string $mobile_phone = null;

    #[OA\Property(
        description: '邮箱',
        type: 'string',
        example: 'admin@example.com',
        nullable: true
    )]
    public ?string $email = null;

    #[OA\Property(
        description: '状态(0:禁用,1:启用)',
        type: 'integer',
        enum: [0, 1],
        example: 1,
        nullable: true
    )]
    public ?int $status = null;

    #[OA\Property(
        description: '部门ID',
        type: 'integer',
        example: 1,
        nullable: true
    )]
    public ?int $dept_id = null;
}
