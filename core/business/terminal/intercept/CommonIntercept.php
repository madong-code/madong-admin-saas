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

use core\foundation\tool\Sse;

/**
 * 通用拦截器类
 * 提供默认的前置和后置方法
 */
class CommonIntercept implements InterceptInterface
{
    protected string $uuid;

    public function __construct(string $uuid = '')
    {
        $this->uuid = $uuid;
    }

    /**
     * 前置处理
     *
     * @return \Generator
     * @throws \Exception
     */
    public function before(): \Generator
    {
        yield Sse::progress('默认拦截前置处理....', 0, [], $this->uuid);
    }

    /**
     * 后置处理
     *
     * @param string $commandKey 命令键
     * @param int    $exitCode   退出码
     *
     * @return \Generator
     * @throws \Exception
     */
    public function after(string $commandKey = '', int $exitCode = 0): \Generator
    {
        if ($exitCode === 0) {
            yield Sse::progress('默认拦截后置处理', 98, [], $this->uuid);
        } else {
            yield Sse::progress('默认拦截后置处理-失败，退出代码: ' . $exitCode, 98, [], $this->uuid);
        }
    }
}
