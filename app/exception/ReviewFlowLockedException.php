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

namespace app\exception;

use core\foundation\exception\handler\BaseException;

/**
 * 审核记录已进入外部审批流时的业务异常
 *
 * 抛出时提示管理员前往审批中心处理；仅超级管理员可超审批（强制本地通过/拒绝）。
 */
class ReviewFlowLockedException extends BaseException
{
    public function __construct(
        string $message = '该记录已进入外部审批流，请前往审批中心处理；仅超级管理员可超审批',
        array $params = [],
        string $error = ''
    ) {
        parent::__construct($message, array_merge(['statusCode' => 403], $params), $error);
    }
}
