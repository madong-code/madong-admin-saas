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
namespace app\api\listener\member;

use app\api\event\member\MemberInfoFetchedEvent;
use app\model\member\MemberTag;
use core\foundation\base\BaseListener;
use core\infrastructure\logger\Logger;
use support\Container;

/**
 * 会员标签权限监听器
 *
 * 监听用户信息获取事件，自动添加标签权限
 */
class MemberTagPermissionListener extends BaseListener
{
    protected function process($event): void
    {
        $memberId = $event->memberId;

        // 查询会员的所有启用标签及权限
        $tags = MemberTag::with(['permissions'])
            ->whereHas('members', function ($query) use ($memberId) {
                $query->where('member_id', $memberId);
            })
            ->where('enabled', 1)
            ->get();

        if ($tags->isEmpty()) {
            return;
        }

        // 收集所有权限
        $permissions = [];
        foreach ($tags as $tag) {
            foreach ($tag->permissions as $permission) {
                // permission 是 Menu 对象，使用 code 字段
                $permissions[] = $permission->code;
            }
        }

        // 添加到事件
        if (!empty($permissions)) {
            $event->addPermissions(array_unique($permissions));
        }
    }
}
