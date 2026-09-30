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

/**自定义示例拦截类
 *
 * @author Mr.April
 * @since  1.0
 */
class CustomIntercept implements InterceptInterface
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
        yield Sse::progress('自定义拦截前置处理....', 0, [], $this->uuid);
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
            yield Sse::progress('自定义拦截后置处理-成功', 98, [], $this->uuid);
        } else {
            yield Sse::progress('自定义拦截后置处理失败，退出代码: ' . $exitCode, 98, [], $this->uuid);
        }
    }

}