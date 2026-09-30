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

use app\schema\request\BaseFormRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '菜单表单',
    description: '系统菜单创建和编辑接口共用的表单请求参数'
)]
class MenuFormRequest extends BaseFormRequest
{

    #[OA\Property(
        property: 'type',
        description: '菜单类型（1菜单 2按钮 3接口）',
        type: 'integer',
        enum: [1, 2, 3],
        example: 2,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|in:1,2,3')]
    public int $type;

    #[OA\Property(
        property: 'pid',
        description: '父菜单ID（0为顶级菜单）',
        type: 'string',
        example: '242218273067237379',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string')]
    public string $pid;

    #[OA\Property(
        property: 'title',
        description: '菜单标题',
        type: 'string',
        example: '智能问答',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:50')]
    public string $title;

    #[OA\Property(
        property: 'code',
        description: '菜单编码',
        type: 'string',
        example: 'ai:chat',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:50|unique:sys_menu,code,' . 'id' . ',id')]
    public string $code;

    #[OA\Property(
        property: 'path',
        description: '路由路径',
        type: 'string',
        example: '/ai/agent',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:255|nullable')]
    public ?string $path;

    #[OA\Property(
        property: 'component',
        description: '前端组件路径',
        type: 'string',
        example: '/ai/agent/index',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:255|nullable')]
    public ?string $component;

    #[OA\Property(
        property: 'open_type',
        description: '打开方式（0当前页 1新窗口）',
        type: 'integer',
        enum: [0, 1],
        example: 1,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|in:0,1')]
    public int $open_type;

    #[OA\Property(
        property: 'icon',
        description: '菜单图标',
        type: 'string',
        example: 'mingcute:kakao-talk-line',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:50|nullable')]
    public ?string $icon;

    #[OA\Property(
        property: 'sort',
        description: '排序号',
        type: 'integer',
        example: 999,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|integer|min:-1000|max:1000')]
    public int $sort;

    #[OA\Property(
        property: 'enabled',
        description: '是否启用（1启用 0禁用）',
        type: 'integer',
        enum: [0, 1],
        example: 1,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|in:0,1')]
    public int $enabled;

    #[OA\Property(
        property: 'is_show',
        description: '是否显示（1显示 0隐藏）',
        type: 'integer',
        enum: [0, 1],
        example: 1,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|in:0,1')]
    public int $is_show;

    #[OA\Property(
        property: 'is_sync',
        description: '是否同步路由（1同步 0不同步）',
        type: 'integer',
        enum: [0, 1],
        example: 0,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|in:0,1')]
    public int $is_sync;

    #[OA\Property(
        property: 'is_cache',
        description: '是否缓存（1缓存 0不缓存）',
        type: 'integer',
        enum: [0, 1],
        example: 0,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|in:0,1')]
    public int $is_cache;
}
