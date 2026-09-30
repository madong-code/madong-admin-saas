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
 * Official Website: https://madong.tech
 */

namespace app\enum\system;

enum DataPermission: int
{
    // 基础权限类型
    case ALL = 1;
    case CUSTOM = 2;
    case CURRENT_DEPT = 3;
    case CURRENT_DEPT_WITH_CHILDREN = 4;
    case SELF = 5;
    case HYBRID = 6;

    /**
     *
     * 获取人类可读的标签
     */
    public function label(): string
    {
        return match ($this) {
            self::ALL => '全部数据权限',
            self::CUSTOM => '自定义数据权限',
            self::CURRENT_DEPT => '本部门数据权限',
            self::CURRENT_DEPT_WITH_CHILDREN => '本部门及以下数据权限',
            self::SELF => '本人数据权限',
            self::HYBRID => '部门及以下或本人数据权限'
        };
    }

    // 颜色映射（兼容Ant Design色系）
    public function color(): string
    {
        return match ($this) {
            self::ALL => 'green',
            self::CUSTOM => 'default',
            self::CURRENT_DEPT => 'orange',
            self::CURRENT_DEPT_WITH_CHILDREN => 'cyan',
            self::SELF => 'error',
            self::HYBRID => 'default'
        };
    }
}
