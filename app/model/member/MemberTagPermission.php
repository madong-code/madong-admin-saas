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
namespace app\model\member;

use core\foundation\base\BasePivot;

/**
 * 会员标签菜单关联中间模型（Pivot）
 *
 * 用于 MemberTag 和 Menu 之间的多对多关联
 */
class MemberTagPermission extends BasePivot
{
    /**
     * 关联的表名
     */
    protected $table = 'member_tag_permission';

    /**
     * 可批量赋值的字段
     */
    protected $fillable = [
        'tag_id',
        'menu_id',
    ];

    protected $casts = [
        'menu_id'   => 'string',
        'tag_id'    => 'string',
        'tenant_id' => 'string',
    ];
}
