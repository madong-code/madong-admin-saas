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
    title: '系统菜单详情响应模型',
    description: '系统菜单详情接口的返回数据结构'
)]
class MenuResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'id',
        description: '菜单ID',
        type: 'string',
        example: '242218273029488640'
    )]
    public string|int $id;

    #[OA\Property(
        property: 'pid',
        description: '父菜单ID（0为顶级菜单）',
        type: 'string',
        example: 0
    )]
    public int|string $pid;

    #[OA\Property(
        property: 'app',
        description: '所属应用',
        type: 'string',
        example: 'admin'
    )]
    public string $app;

    #[OA\Property(
        property: 'title',
        description: '菜单标题',
        type: 'string',
        example: '首页'
    )]
    public string $title;

    #[OA\Property(
        property: 'code',
        description: '菜单编码',
        type: 'string',
        example: 'Dashboard'
    )]
    public string $code;

    #[OA\Property(
        property: 'level',
        description: '菜单层级',
        type: 'integer',
        example: null,
        nullable: true
    )]
    public ?int $level;

    #[OA\Property(
        property: 'type',
        description: '菜单类型（1菜单 2按钮 3接口）',
        type: 'integer',
        example: 1
    )]
    public int $type;

    #[OA\Property(
        property: 'sort',
        description: '排序号（越小越靠前）',
        type: 'integer',
        example: -1
    )]
    public int $sort;

    #[OA\Property(
        property: 'path',
        description: '路由路径',
        type: 'string',
        example: '/'
    )]
    public string $path;

    #[OA\Property(
        property: 'component',
        description: '前端组件路径',
        type: 'string',
        example: 'BasicLayout'
    )]
    public string $component;

    #[OA\Property(
        property: 'redirect',
        description: '路由重定向',
        type: 'string',
        example: '/workspace',
        nullable: true
    )]
    public ?string $redirect;

    #[OA\Property(
        property: 'icon',
        description: '菜单图标（Ant Design图标）',
        type: 'string',
        example: 'ant-design:home-outlined'
    )]
    public string $icon;

    #[OA\Property(
        property: 'is_show',
        description: '是否显示（1显示 0隐藏）',
        type: 'integer',
        enum: [0, 1],
        example: 1
    )]
    public int $is_show;

    #[OA\Property(
        property: 'is_link',
        description: '是否外链（1是 0否）',
        type: 'integer',
        enum: [0, 1],
        example: 0
    )]
    public int $is_link;

    #[OA\Property(
        property: 'link_url',
        description: '外链地址',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $link_url;

    #[OA\Property(
        property: 'enabled',
        description: '是否启用（1启用 0禁用）',
        type: 'integer',
        enum: [0, 1],
        example: 1
    )]
    public int $enabled;

    #[OA\Property(
        property: 'open_type',
        description: '打开方式（0当前页 1新窗口）',
        type: 'integer',
        enum: [0, 1],
        example: 0
    )]
    public int $open_type;

    #[OA\Property(
        property: 'is_cache',
        description: '是否缓存（1缓存 0不缓存）',
        type: 'integer',
        enum: [0, 1],
        example: 0
    )]
    public int $is_cache;

    #[OA\Property(
        property: 'is_sync',
        description: '是否同步路由（1同步 0不同步）',
        type: 'integer',
        enum: [0, 1],
        example: 1
    )]
    public int $is_sync;

    #[OA\Property(
        property: 'is_affix',
        description: '是否固定（1固定 0不固定）',
        type: 'integer',
        enum: [0, 1],
        example: 0
    )]
    public int $is_affix;

    #[OA\Property(
        property: 'is_global',
        description: '是否全局菜单（1是 0否）',
        type: 'integer',
        enum: [0, 1],
        example: 0
    )]
    public int $is_global;

    #[OA\Property(
        property: 'variable',
        description: '自定义变量',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $variable;

    #[OA\Property(
        property: 'methods',
        description: '请求方法（多个用逗号分隔）',
        type: 'string',
        example: 'get'
    )]
    public string $methods;

    #[OA\Property(
        property: 'is_frame',
        description: '是否内嵌窗口',
        type: 'integer',
        example: null,
        nullable: true
    )]
    public ?int $is_frame;

    #[OA\Property(
        property: 'created_at',
        description: '创建时间',
        type: 'string',
        format: 'date-time',
        example: null,
        nullable: true
    )]
    public ?string $created_at;

    #[OA\Property(
        property: 'updated_at',
        description: '更新时间',
        type: 'string',
        format: 'date-time',
        example: null,
        nullable: true
    )]
    public ?string $updated_at;

    #[OA\Property(
        property: 'deleted_at',
        description: '删除时间',
        type: 'string',
        format: 'date-time',
        example: null,
        nullable: true
    )]
    public ?string $deleted_at;

    #[OA\Property(
        property: 'created_by',
        description: '创建人ID',
        type: 'integer',
        example: null,
        nullable: true
    )]
    public ?int $created_by;

    #[OA\Property(
        property: 'updated_by',
        description: '更新人ID',
        type: 'integer',
        example: null,
        nullable: true
    )]
    public ?int $updated_by;

    #[OA\Property(
        property: 'created_date',
        description: '创建日期（格式化）',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $created_date;

    #[OA\Property(
        property: 'updated_date',
        description: '更新日期（格式化）',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $updated_date;

    #[OA\Property(
        property: 'children',
        description: '子菜单列表',
        type: 'array',
        items: new OA\Items(ref: MenuResponse::class),
        nullable: true
    )]
    public ?array $children;  // 嵌套子菜单
}
