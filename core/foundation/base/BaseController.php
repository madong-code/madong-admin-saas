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
namespace core\foundation\base;

abstract class BaseController
{

    /**
     * @var \core\foundation\base\BaseService|null
     */
    protected BaseService|null $service = null;


    /**
     * @var \core\foundation\base\BaseValidate|null
     */
    protected BaseValidate|null $validate=null;

    /**
     * 构造方法
     *
     * @access public
     */
    public function __construct()
    {
        $this->initialize();
    }

    /**
     * @return void
     */
    abstract protected function initialize(): void;

}
