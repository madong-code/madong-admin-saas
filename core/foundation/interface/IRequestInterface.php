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
namespace core\foundation\interface;

interface IRequestInterface
{
    /**
     * 获取POST参数.
     */
    public function getMore(array $params, ?bool $suffix = null): array;

    /**
     * 获取GET参数.
     */
    public function postMore(array $params, ?bool $suffix = null): array;

}
