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
namespace app\enum\terminal;

use core\foundation\interface\IEnum;

enum CommandEvent: string implements IEnum
{
    /**
     * 连接成功事件
     */
    case LINK_SUCCESS = 'command-link';

    /**
     * 执行成功事件
     */
    case EXEC_SUCCESS = 'command-success';

    /**
     * 执行完成事件
     */
    case EXEC_COMPLETED = 'command-completed';

    /**
     * 执行出错事件
     */
    case EXEC_ERROR = 'command-error';

    /**
     * 默认/未知事件
     */
    case DEFAULT = 'message'; // 默认事件类型

    /**
     * 获取状态显示文本
     */
    public function label(): string
    {
        return match ($this) {
            self::LINK_SUCCESS => '链接成功',
            self::EXEC_SUCCESS => '执行成功',
            self::EXEC_COMPLETED => '执行完成',
            self::EXEC_ERROR => '执行出错',
            self::DEFAULT => '默认',
        };
    }

}
