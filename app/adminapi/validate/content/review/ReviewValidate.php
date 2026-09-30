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

namespace app\adminapi\validate\content\review;

use core\foundation\base\BaseValidate;

/**
 * 审核验证器
 *
 * 场景：
 * - reject：拒绝原因必填
 * - cancel：取消原因必填
 * - batch：ids 必填数组
 */
class ReviewValidate extends BaseValidate
{
    protected array $rules = [
        'reason' => 'required|max:255',
        'ids'    => 'required|array',
    ];

    protected array $messages = [
        'reason.required' => '拒绝/取消原因不能为空',
        'reason.max'      => '原因不能超过255个字符',
        'ids.required'    => '请选择审核记录',
        'ids.array'       => 'ids格式错误',
    ];

    protected array $scenes = [
        'reject' => ['reason'],
        'cancel' => ['reason'],
        'batch'  => ['ids'],
    ];
}
