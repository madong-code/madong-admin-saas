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

use app\model\web\Link;
use core\foundation\base\BaseValidate;
use Illuminate\Validation\Rule;

/**
 * 友情链接验证器
 */
class LinkValidate extends BaseValidate
{
    /**
     * 验证规则
     */
    public function rules(): array
    {
        $id = request()->route->param('id');

        return [
            'name' => ['required', 'max:50', Rule::unique(Link::class, 'name')->ignore($id, 'id')],
            'url' => ['required', 'url', 'max:255'],
            'logo' => ['max:255'],
            'description' => ['max:255'],
            'sort' => ['integer', 'min:0'],
            'enabled' => ['integer', 'in:0,1'],
        ];
    }

    /**
     * 验证消息
     */
    protected array $messages = [
        'name.required' => '链接名称不能为空',
        'name.max' => '链接名称不能超过50个字符',
        'name.unique' => '链接名称已存在',
        'url.required' => '链接地址不能为空',
        'url.url' => '链接地址格式不正确',
        'url.max' => '链接地址不能超过255个字符',
        'logo.max' => '链接图标不能超过255个字符',
        'description.max' => '描述不能超过255个字符',
        'sort.integer' => '排序必须为整数',
        'sort.min' => '排序不能小于0',
        'enabled.integer' => '状态必须为整数',
        'enabled.in' => '状态值不正确',
    ];

    /**
     * 验证场景
     */
    protected array $scenes = [
        'store' => [
            'name',
            'url',
            'logo',
            'description',
            'sort',
            'enabled',
        ],
        'update' => [
            'name',
            'url',
            'logo',
            'description',
            'sort',
            'enabled',
        ],
    ];
}
