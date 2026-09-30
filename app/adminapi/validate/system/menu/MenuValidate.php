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

namespace app\adminapi\validate\system\menu;

use app\model\system\menu\Menu;
use core\foundation\base\BaseValidate;

/**
 * 菜单验证器
 */
class MenuValidate extends BaseValidate
{
    /**
     * 定义验证规则
     */
    protected array $rules = [
        'pid'     => 'required',
        'code'    => 'required',
        'title'   => 'required|max:16',
        'type'    => 'numeric',
        'sort'    => 'numeric',
        'enabled' => 'numeric',
        'path'    => 'checkPathUnique:pid',
    ];

    /**
     * 定义错误信息
     */
    protected array $messages = [
        'pid.required'   => '菜单上级必须填写',
        'code.required'  => '菜单标识必须填写',
        'title.required' => '菜单名称必须填写',
        'title.max'      => '菜单名称最多不能超过16个字符',
        'enabled'        => '状态必须填写',
        'path.checkPathUnique' => '同级菜单路径不能重复',
    ];

    /**
     * 定义场景
     */
    protected array $scenes = [
        'store'       => [
            'code',
            'title',
            'type',
            'sort',
            'enabled',
            'path',
        ],
        'update'      => [
            'code',
            'title',
            'type',
            'sort',
            'enabled',
            'path',
        ],
        'batch-store' => [
            'title',
            'type',
            'sort',
        ],
    ];

    /**
     * 验证同级菜单 path 唯一性
     */
    public function checkPathUnique(mixed $value, mixed $rule, array $data = []): bool
    {
        $pid = $data['pid'] ?? '0';

        $query = Menu::where('pid', $pid)->where('path', $value);
        // 编辑时排除自身
        if (!empty($data['id'])) {
            $query->where('id', '<>', $data['id']);
        }

        return !$query->exists();
    }

}
