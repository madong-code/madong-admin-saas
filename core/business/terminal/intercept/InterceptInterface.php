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
namespace core\business\terminal\intercept;

/**
 * 拦截器接口
 * 定义拦截器的标准方法
 */
interface InterceptInterface
{
    /**
     * 前置处理
     *
     * @return \Generator
     */
    public function before(): \Generator;

    /**
     * 后置处理
     *
     * @param string $commandKey 命令键
     * @param int $exitCode 退出码
     *
     * @return \Generator
     */
    public function after(string $commandKey = '', int $exitCode = 0): \Generator;
}