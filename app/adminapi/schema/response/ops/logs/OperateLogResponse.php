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
namespace app\adminapi\schema\response\ops\logs;

use madong\swagger\schema\BaseResponseDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '操作日志详情响应模型',
    description: '操作日志详情接口的返回数据结构'
)]
class OperateLogResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'id',
        description: '日志ID',
        type: 'string',
        example: '233375369209069568'
    )]
    public string $id;

    #[OA\Property(
        property: 'name',
        description: '操作名称',
        type: 'string',
        example: '数据字典'
    )]
    public string $name;

    #[OA\Property(
        property: 'app',
        description: '应用名称',
        type: 'string',
        example: 'admin'
    )]
    public string $app;

    #[OA\Property(
        property: 'ip',
        description: '操作IP',
        type: 'string',
        example: '220.165.172.45'
    )]
    public string $ip;

    #[OA\Property(
        property: 'ip_location',
        description: 'IP归属地',
        type: 'string',
        example: '未知'
    )]
    public ?string $ip_location;

    #[OA\Property(
        property: 'browser',
        description: '浏览器',
        type: 'string',
        example: 'Chrome'
    )]
    public ?string $browser;

    #[OA\Property(
        property: 'os',
        description: '操作系统',
        type: 'string',
        example: 'Windows'
    )]
    public ?string $os;

    #[OA\Property(
        property: 'url',
        description: '请求URL',
        type: 'string',
        example: '/system/dict'
    )]
    public string $url;

    #[OA\Property(
        property: 'class_name',
        description: '控制器类名',
        type: 'string',
        example: 'app\\admin\\controller\\system\\SysDictController'
    )]
    public string $class_name;

    #[OA\Property(
        property: 'action',
        description: '操作方法',
        type: 'string',
        example: 'index'
    )]
    public string $action;

    #[OA\Property(
        property: 'method',
        description: '请求方法',
        type: 'string',
        enum: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
        example: 'GET'
    )]
    public string $method;

    #[OA\Property(
        property: 'param',
        description: '请求参数',
        type: 'object',
        example: '{"page":"1","limit":"20"}'
    )]
    public array $param;

    #[OA\Property(
        property: 'user_name',
        description: '操作用户',
        type: 'string',
        example: 'admin'
    )]
    public string $user_name;

    #[OA\Property(
        property: 'created_at',
        description: '操作时间',
        type: 'string',
        format: 'date-time',
        example: '2025-10-05T15:50:23.000000Z'
    )]
    public string $created_at;

    #[OA\Property(
        property: 'updated_at',
        description: '更新时间',
        type: 'string',
        format: 'date-time',
        example: '2025-10-05T15:50:23.000000Z'
    )]
    public string $updated_at;

    #[OA\Property(
        property: 'created_date',
        description: '操作日期（本地）',
        type: 'string',
        example: '2025-10-05 23:50:23'
    )]
    public string $created_date;

    #[OA\Property(
        property: 'updated_date',
        description: '更新日期（本地）',
        type: 'string',
        example: '2025-10-05 23:50:23'
    )]
    public string $updated_date;
}
