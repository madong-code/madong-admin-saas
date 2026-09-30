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

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * 系统级中间表（Pivot）基类
 *
 * 用于平台级的多对多关联中间表（如套餐⇔权限关联），
 * 不受租户隔离影响，始终使用默认数据库连接。
 *
 * 与 SystemModel 对应，是 BasePivot 的平台级版本。
 *
 * 文档位置: docs/saas/08-模型设计.md
 */
class SystemPivot extends Pivot
{
    /**
     * 中间表默认不维护时间戳
     * 子类可按需覆盖为 true
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * 系统中间表始终使用默认数据库连接
     *
     * @var string|null
     */
    protected $connection;

    public function __construct(array $attributes = [])
    {
        $this->connection = config('database.default');
        parent::__construct($attributes);
    }

    /**
     * 获取数据库连接名
     * 系统中间表始终使用默认连接，不受租户切换影响
     */
    public function getConnectionName()
    {
        if (isset($this->connection)) {
            return $this->connection;
        }
        return config('database.default');
    }
}
