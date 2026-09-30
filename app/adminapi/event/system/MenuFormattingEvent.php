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
namespace app\adminapi\event\system;

use core\foundation\base\BaseEvent;
use Illuminate\Support\Collection;

/**
 * 菜单格式化事件
 */
class MenuFormattingEvent extends BaseEvent
{
    /**
     * 菜单数据
     */
    public Collection|null $data;

    /**
     * 格式化类型
     */
    public string $formatType;

    /**
     * 结果
     */
    public array $result = [];

    /**
     * 构造函数
     */
    public function __construct(
        Collection|null $data,
        string $formatType
    ) {
        $this->data = $data;
        $this->formatType = $formatType;
    }

    public function getEventName(): string
    {
        return 'adminapi.menu.formatting';
    }

    /**
     * 触发事件（兼容外部 $data = $event->dispatch() 调用）
     */
    public function dispatch(): array
    {
        parent::dispatch();
        return $this->result;
    }
}
