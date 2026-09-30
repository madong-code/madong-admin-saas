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
namespace app\platform\controller;

use app\adminapi\controller\Crud;
use core\foundation\tool\Json;
use support\Request;

/**
 * platformapi 控制器基类
 * 
 * 继承自 adminapi 的 Crud 基类
 */
class Base extends Crud
{
    /**
     * 返回成功响应
     *
     * @param string $msg
     * @param mixed $data
     * @param int $code
     * @return \support\Response
     */
    protected function success(string $msg = 'ok', mixed $data = [], int $code = 0): \support\Response
    {
        return Json::success($msg, $data, $code);
    }

    /**
     * 返回失败响应
     *
     * @param string $msg
     * @param mixed $data
     * @param int $code
     * @return \support\Response
     */
    protected function fail(string $msg = 'error', mixed $data = [], int $code = 400): \support\Response
    {
        return Json::fail($msg, $data, $code);
    }
}
