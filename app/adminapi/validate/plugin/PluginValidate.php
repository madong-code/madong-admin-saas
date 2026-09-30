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
 * Official Website: https://madong.tech
 */

namespace app\adminapi\validate\plugin;

use app\model\plugin\Plugin;
use core\foundation\base\BaseValidate;
use Illuminate\Validation\Rule;

/**
 * Plugin验证器
 *
 * @author Mr.April
 * @since  1.0
 */
class PluginValidate extends BaseValidate
{
    /**
     * 场景
     *
     * @var array
     */
    protected array $scenes = [
        'store'  => [
            'author',
            'version',
            'cover',
            'key',
            'desc',
            'title',
            'icon',
            'type',
            'category_id',
            'tags',
            'price',
        ],
        'update' => [
            'author',
            'version',
            'cover',
            'desc',
            'title',
            'icon',
            'type',
            'id',
            'category_id',
            'tags',
            'price',
        ],
        'market.store' => [
            'key',
            'title',
            'version',
            'category_id',
        ],
        'market.update' => [
            'key',
            'title',
            'version',
            'category_id',
        ],
    ];

    /**
     * 验证规则
     *
     * @return array
     */
    public function rules(): array
    {
        $id = request()->route->param('id');
        return [
            'author'       => 'string',
            'version'      => 'string',
            'cover'        => 'string',
            'updated_at'   => 'integer',
            'id'           => 'integer|string',
            'key'          => ['required', 'max:50', Rule::unique(Plugin::class, 'key')->ignore($id, 'id')],
            'desc'         => 'string',
            'support_app'  => 'required|string',
            'installed_at' => 'required|integer',
            'title'        => 'required|string',
            'icon'         => 'string',
            'status'       => 'required|integer',
            'type'         => 'required|string',
            'created_at'   => 'integer',
            'variables'    => 'string',
            'category_id'  => 'string|max:50',
            'tags'         => 'string|max:255',
            'price'        => 'numeric|min:0',
        ];
    }

    /**
     * 错误信息
     *
     * @return array
     */
    public function message(): array
    {
        return [
            'author.required'       => '作者必须填写',
            'author.string'         => '作者必须是字符串',
            'version.required'      => '版本号必须填写',
            'version.string'        => '版本号必须是字符串',
            'cover.required'        => '封面必须填写',
            'cover.string'          => '封面必须是字符串',
            'updated_at.integer'    => '更新时间必须是整数',
            'id.integer'            => '主键必须是整数或字符串',
            'id.string'             => '主键必须是整数或字符串',
            'key.required'          => '插件标识必须填写',
            'key.string'            => '插件标识必须是字符串',
            'key.unique'            => '插件Key已被占用',
            'desc.string'           => '插件描述必须是字符串',
            'support_app.required'  => '插件支持的应用必须填写',
            'support_app.string'    => '插件支持的应用必须是字符串',
            'installed_at.required' => '安装时间必须填写',
            'installed_at.integer'  => '安装时间必须是整数',
            'title.required'        => '插件名称必须填写',
            'title.string'          => '插件名称必须是字符串',
            'icon.required'         => '插件图标必须填写',
            'icon.string'           => '插件图标必须是字符串',
            'status.required'       => '状态必须填写',
            'status.integer'        => '状态必须是整数',
            'type.required'         => '插件类型必须填写',
            'type.string'           => '插件类型必须是字符串',
            'created_at.integer'    => '创建时间必须是整数',
            'variables.string'      => '必须是字符串',
            'category_id.string'    => '分类ID必须是字符串',
            'category_id.max'       => '分类ID不能超过50个字符',
            'tags.string'           => '标签必须是字符串',
            'tags.max'              => '标签不能超过255个字符',
            'price.numeric'         => '价格必须是数字',
            'price.min'             => '价格不能小于0',
        ];
    }
}
