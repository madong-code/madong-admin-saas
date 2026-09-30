<?php
/**
 * 审核模块配置文件
 * 配置说明：
 * 1. 插件可以独立配置自己的审核类型和字段映射
 * 2. 系统会自动扫描并合并所有插件的配置
 * 3. 插件配置路径：plugin/[name]/config/review.php
 */

return [
    // 审批流模块配置
    'flow'                   => [
        // 是否启用审批流模式（全局默认开关）
        // 单个审核类型可在各自 review.php 的 types[type]['flow']['enabled'] 覆盖（全局+类型覆盖）
        'enabled' => env('REVIEW_FLOW_ENABLED', false),

        // 审批流网关类（实现 core\interface\review\ApprovalFlowGateway）
        // 默认占位实现：simple 模式本地闭环；workflow 模式仅记录日志，等待真实引擎插件换装。
        'gateway' => env('REVIEW_FLOW_GATEWAY', \app\service\admin\content\review\NullApprovalFlowGateway::class),

        // 审批流服务类（第三方审批流模块提供，预留）
        'service' => env('REVIEW_FLOW_SERVICE', null),

        // 审批流模型类（第三方审批流模块提供，预留）
        'model'   => env('REVIEW_FLOW_MODEL', null),
    ],

    // 自动审核配置
    'auto_review'            => [
        // 自动审核人ID（默认0表示系统）
        'reviewer_id' => env('REVIEW_AUTO_REVIEWER_ID', 0),
    ],

    // 审核类型配置（根据业务需求扩展）
    // 注意：插件的审核类型配置在插件目录的 config/review.php 中
    // 系统会自动扫描并合并所有插件的配置
    'types'                  => [
        'comment' => [
            'name'        => '评论审核',
            'model'       => 'app\\model\\comment\\Comment',
            'auto_submit' => true, // 创建时自动提交审核
        ],
        // 'question' 类型由官方插件提供，在 plugin/official/config/review.php 中配置
        // 可以继续添加其他审核类型
    ],

    // 审核状态配置（使用枚举值）
    'status'                 => [
        'pending'  => 0,   // 待审核
        'approved' => 1,  // 已通过
        'rejected' => 2,  // 已拒绝
        'canceled' => 3,  // 已取消
    ],

    // ==================== 新增：字段映射配置 ====================

    // 默认字段映射（优先级最低，用于通用映射）
    'default_field_mappings' => [
        'title'     => [
            'type'     => 'attribute',      // attribute/relation/callback
            'source'   => 'title',        // 字段名或关联名
            'fallback' => '未命名',     // 默认值
        ],
        'content'   => [
            'type'     => 'attribute',
            'source'   => 'content',
            'fallback' => '无内容',
        ],
        'applicant' => [
            'type'     => 'attribute',
            'source'   => 'author',       // 例如：author、creator、user
            'fallback' => '未知',
        ],
    ],

    // 类型特定字段映射（优先级最高）
    'field_mappings'         => [
        // 系统内置类型的映射示例
        'comment' => [
            'model'     => 'app\\model\\comment\\Comment',
            'title'     => [
                'type'     => 'callback',
                'callback' => function ($model) {
                    // 回调函数可以任意处理
                    return '评论：' . \Illuminate\Support\Str::limit($model->content, 30);
                },
                'fallback' => '无标题评论',
            ],
            'content'   => [
                'type'     => 'attribute',
                'source'   => 'content',
                'fallback' => '无内容',
            ],
            'applicant' => [
                'type'      => 'relation',
                'source'    => 'author',
                'attribute' => 'username',
                'fallback'  => '匿名用户',
            ],
        ],
        // 'question' 类型的字段映射由官方插件提供，在 plugin/official/config/review.php 中配置
    ],

    // ==================== 配置扫描 ====================

    // 是否自动扫描插件配置（默认开启）
    'scan_plugins'           => env('REVIEW_SCAN_PLUGINS', true),

    // 插件配置文件名
    'plugin_config_file'     => 'review.php',

    // 忽略的插件（数组）
    'ignored_plugins'        => [
        'example',  // 示例插件
    ],
];
