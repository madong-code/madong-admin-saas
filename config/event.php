<?php

return [
    'adminapi.login.log' => [
        [\app\adminapi\listener\system\LoginLogListener::class, 'handle'],
    ],
    'adminapi.operation.log' => [
        [\app\adminapi\listener\system\OperationLogListener::class, 'handle'],
    ],

    // ==================== 平台端事件 ====================
    'platform.login.log' => [
        [\app\platform\listener\system\LoginLogListener::class, 'handle'],
    ],
    'platform.operation.log' => [
        [\app\platform\listener\system\OperationLogListener::class, 'handle'],
    ],
    // 平台端菜单徽标装饰：业务监听器可在此为菜单追加/覆盖徽标
    'platform.menu.badge_decorate' => [],

    'adminapi.menu.formatting' => [
        [\app\adminapi\listener\system\MenuFormattingListener::class, 'handle'],
    ],
    // 菜单徽标装饰：业务监听器可在此为菜单追加/覆盖徽标
    'adminapi.menu.badge_decorate' => [
        [\app\adminapi\listener\system\MenuBadgeSampleListener::class, 'handle'],
    ],
    // 积分变动事件
    'adminapi.points.changed' => [
        [\app\adminapi\listener\member\PointsChangedListener::class, 'handle'],
    ],
    // 会员等级更新事件
    'adminapi.member.level.updated' => [
        [\app\adminapi\listener\member\MemberLevelUpdatedListener::class, 'handle'],
    ],
    // 审核事件
    'adminapi.review.approved' => [
        [\app\adminapi\listener\review\ReviewApprovedListener::class, 'handle'],
    ],
    'adminapi.review.rejected' => [
        [\app\adminapi\listener\review\ReviewRejectedListener::class, 'handle'],
    ],
    'adminapi.review.created' => [
        [\app\adminapi\listener\review\ReviewCreatedListener::class, 'handle'],
    ],
    'adminapi.review.canceled' => [
        [\app\adminapi\listener\review\ReviewCanceledListener::class, 'handle'],
    ],

    // 消息推送事件
    'adminapi.message.push' => [
        [\app\adminapi\listener\content\MessagePushListener::class, 'handle'],
    ],

    // 插件生命周期事件
    'plugin.installing' => [],
    'plugin.installed' => [
        [\app\listener\plugin\TenantPluginSyncListener::class, 'handle'],
    ],
    'plugin.uninstalling' => [],
    'plugin.uninstalled' => [
        [\app\listener\plugin\TenantPluginSyncListener::class, 'handle'],
    ],
    'plugin.updating' => [],
    'plugin.updated' => [
        [\app\listener\plugin\TenantPluginSyncListener::class, 'handle'],
    ],
];
