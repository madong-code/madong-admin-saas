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

namespace app\adminapi\validate\system\org;

use app\model\system\org\Post;
use core\foundation\base\BaseValidate;

/**
 * 用户角色验证器
 */
class PostValidate extends BaseValidate
{
    /**
     * 定义验证规则
     */
    protected array $rules = [
        'dept_id' => 'required',
        'code'    => 'required|alphaNum|unique:' . Post::class . ',code',
        'name'    => 'required|max:16',
        'sort'    => 'numeric',
        'enabled' => 'required',
    ];

    /**
     * 定义错误信息
     */
    protected array $messages = [
        'code.required'  => '职位标识必须填写',
        'code.alphaNum'  => '职位标识只能由英文字或者数字母组成',
        'code.unique'    => '职位代码已被占用',
        'name.required'  => '职位名称必须填写',
        'name.max'       => '职位名称最多不能超过16个字符',
        'enabled'        => '状态必须填写',
    ];

    /**
     * 定义场景
     */
    protected array $scenes = [
        'store'  => [
            'dept_id',
            'code',
            'name',
            'sort',
            'enabled',
        ],
        'update' => [
            'code',
            'name',
            'sort',
            'enabled',
        ],
    ];

    /**
     * update 场景：code 唯一校验排除自身
     */
    public function Update(): void
    {
        $this->only = $this->scenes['update'];
        $id = request()->route->param('id');
        $this->rules['code'] = 'required|alphaNum|unique:' . Post::class . ',code,' . ($id ?? 'NULL') . ',id';
    }
}
