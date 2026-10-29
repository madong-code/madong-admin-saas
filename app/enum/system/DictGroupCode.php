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

namespace app\enum\system;

use core\foundation\interface\IEnum;

/**
 * 字典分组枚举
 *
 * 与字典表 group_code 取值保持一致（安装时按 resource/data/dict/dict.php 的 group_code 写入）。
 *
 * @author Mr.April
 * @since  1.0
 */
enum DictGroupCode: string implements IEnum
{
    case DEFAULT = 'default';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DEFAULT => '默认分组',
            self::OTHER   => '其他分组',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DEFAULT => '#409EFF',
            self::OTHER   => '#909399',
        };
    }
}
