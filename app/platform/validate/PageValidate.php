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
namespace app\platform\validate;

/**
 * 分页参数验证器
 */
class PageValidate extends BaseValidate
{
    protected array $rules = [
        'page' => 'nullable|integer|min:1',
        'limit' => 'nullable|integer|min:1|max:100',
        'keyword' => 'nullable|string|max:255',
        'order_by' => 'nullable|string|max:50',
        'order_dir' => 'nullable|string|in:asc,desc',
    ];

    protected array $messages = [
        'page.integer' => '页码必须是整数',
        'page.min' => '页码最小为1',
        'limit.integer' => '每页数量必须是整数',
        'limit.min' => '每页数量最小为1',
        'limit.max' => '每页数量最大为100',
        'keyword.string' => '关键词必须是字符串',
        'keyword.max' => '关键词长度不能超过255字符',
        'order_by.string' => '排序字段必须是字符串',
        'order_by.max' => '排序字段长度不能超过50字符',
        'order_dir.string' => '排序方向必须是字符串',
        'order_dir.in' => '排序方向只能是asc或desc',
    ];
}
