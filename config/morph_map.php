<?php

return [
    /**
     * 多态关联映射配置（主应用）
     *
     * 表中存储的模型别名（key） -> 实际模型类路径（value）
     * 插件可在 plugin/{name}/config/morph_map.php 追加，由 MorphMapBootstrap 合并。
     *
     * 作用：
     * 1. 库表只存短别名，不存完整类路径
     * 2. 改类路径只改配置，无需改库
     * 3. 内容审核 reviewable、评论 parent 等跨插件多态
     * 4. 可审核业务模型 use HasReviewArchives，公开过滤用 scopeApprovedArchive()
     *
     * 使用示例：
     * // Review / ReviewArchive
     * public function reviewable(): MorphTo { return $this->morphTo(); }
     * // 库中 reviewable_type = 'question'（MorphMap 别名）
     */
    'map' => [
        // 会员模块
        'member' => \app\model\member\Member::class,
        'member_withdraw' => \app\model\member\MemberWithdraw::class,

        // 系统管理
        'admin' => \app\model\system\admin\Admin::class
    ],
];
