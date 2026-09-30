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
namespace app\adminapi\validate\web;

use app\model\web\Menu;
use core\foundation\base\BaseValidate;
use Illuminate\Validation\Rule;

/**
 * 菜单验证器
 */
class MenuValidate extends BaseValidate
{
    /**
     * 验证规则
     */
    public function rules(): array
    {
        $id = request()->route->param('id');

        return [
            'id' => ['sometimes', 'bail', 'string', 'integer'],
            'app' => ['max:50'],
            'category' => ['integer', 'in:1,2'],
            'source' => ['max:50'],
            'name' => ['required', 'max:50', Rule::unique(Menu::class, 'name')->ignore($id, 'id')],
            'type' => ['integer', 'in:1,2,3,4'],
            'parent_id' => ['sometimes', 'bail', 'string', 'integer'],
            'level' => ['integer', 'min:0'],
            'path' => ['max:255'],
            'title' => ['max:50'],
            'url' => ['max:255'],
            'icon' => ['max:50'],
            'target' => ['integer', 'in:1,2'],
            'is_show' => ['boolean'],
            'enabled' => ['boolean'],
            'sort' => ['integer', 'min:0'],
        ];
    }

    /**
     * 验证消息
     */
    protected array $messages = [
        'id.string' => 'ID必须为字符串或整数',
        'id.integer' => 'ID必须为字符串或整数',
        'app.max' => '应用名称不能超过50个字符',
        'category.integer' => '菜单分类必须为整数',
        'category.in' => '菜单分类值不正确',
        'source.max' => '来源不能超过50个字符',
        'name.required' => '菜单名称不能为空',
        'name.max' => '菜单名称不能超过50个字符',
        'name.unique' => '菜单名称已存在',
        'type.integer' => '菜单类型必须为整数',
        'type.in' => '菜单类型值不正确',
        'pid.string' => '父菜单ID必须为字符串或整数',
        'pid.integer' => '父菜单ID必须为字符串或整数',
        'path.max' => '路由路径不能超过255个字符',
        'title.max' => '菜单标题不能超过50个字符',
        'url.max' => '链接地址不能超过255个字符',
        'icon.max' => '图标不能超过50个字符',
        'target.integer' => '打开方式必须为整数',
        'target.in' => '打开方式值不正确',
        'is_show.boolean' => '是否显示必须为布尔值',
        'enabled.boolean' => '是否启用必须为布尔值',
        'sort.integer' => '排序必须为整数',
        'sort.min' => '排序不能小于0',
    ];

    /**
     * 验证场景
     */
    protected array $scenes = [
        'store' => [
            'id',
            'app',
            'category',
            'source',
            'name',
            'type',
            'parent_id',
            'level',
            'path',
            'title',
            'url',
            'icon',
            'target',
            'is_show',
            'enabled',
            'belong',
            'belong_id',
            'sort',
        ],
        'update' => [
            'id',
            'app',
            'category',
            'source',
            'name',
            'type',
            'parent_id',
            'level',
            'path',
            'title',
            'url',
            'icon',
            'target',
            'is_show',
            'enabled',
            'belong',
            'belong_id',
            'sort',
        ],
    ];
}
