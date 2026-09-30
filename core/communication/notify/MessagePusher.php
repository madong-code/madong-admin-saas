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
namespace core\communication\notify;

use app\adminapi\event\content\MessagePushEvent;
use app\enum\system\MessagePriority;
use app\model\content\message\Definition;
use app\model\content\message\Template;
use core\business\tenant\context\TenantContext;
use core\communication\notify\enum\PushClientType;
use core\infrastructure\logger\Logger;
use madong\helper\Arr;

class MessagePusher
{
    /**
     * 定义缓存 key=definitionKey → Definition|null
     *
     * @var array<string, Definition|null>
     */
    private array $definitionCache = [];

    /**
     * 模板缓存 key=definitionId → content_template|null
     *
     * @var array<string, string|null>
     */
    private array $templateCache = [];

    /**
     * 默认业务模块
     */
    private const DEFAULT_MODULE = 'admin';

    /**
     * 默认事件类型
     */
    private const DEFAULT_EVENT = 'message';

    /**
     * 构造函数
     */
    public function __construct()
    {
    }

    /**
     * 创建实例（便于链式调用）
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * 发送消息通知
     *
     * @param string|array $userIds       接收者ID 或 ID数组
     * @param string       $definitionKey 消息定义 key（如 approval_wait, daily_notice）
     *                                     由 核心/插件 在 resource/data/message/category.php 中定义，
     *                                     安装时自动导入 sys_message_definition 表
     * @param string       $title         消息标题
     * @param string       $content       消息内容
     * @param string|null  $relatedId     关联业务ID（如订单ID、审批ID）
     * @param string|null  $actionUrl     跳转链接
     * @param array        $options       可选参数：
     *                                    - priority      (int)               消息优先级，默认 NORMAL(3)
     *                                    - sender_id     (string|int|null)   发送者ID
     *                                    - tenant_id     (string|null)       目标租户ID
     *                                                                        null=自动从上下文获取
     *                                                                        单租户模式自动设为 '*'
     *                                    - force         (bool)              是否强制推送，
     *                                                                        true=跳过订阅过滤
     *                                    - module        (string)            业务模块标识，默认 'admin'
     *                                    - event         (string)            推送事件类型，默认 'message'
     *                                    - extra_data    (array)             附加推送数据（同时写入 DB extra_data 字段和实时推送）
     *                                    - related_type  (string)            关联业务类型，如 'order'、'approval'
     *                                    - action_params (array|string)      跳转参数（写入 DB action_params 字段）
     *                                    - scene         (string)            场景标识（用于日志追踪）
     *
     * @return array { push_count: int, messages: array }
     */
    public function send(
        string|array $userIds,
        string       $definitionKey,
        string       $title,
        string       $content,
        ?string      $relatedId = null,
        ?string      $actionUrl = null,
        array        $options = []
    ): array {
        // 1. 解析消息定义 —— 从 DB 自动发现，无论是核心定义还是插件定义
        //    绕过租户作用域：消息定义是系统级数据，不受租户隔离影响
        $definition = $this->resolveDefinition($definitionKey);
        if ($definition === null) {
            Logger::warning("MessagePusher: 消息定义未找到", ['key' => $definitionKey]);
            return ['push_count' => 0, 'messages' => []];
        }

        // 2. 构建消息记录数据
        $messageData = [
            'definition_id' => $definition->id,
            'category_id'   => $definition->category_id,
            'title'         => $title,
            'content'       => $content,
            'priority'      => $options['priority'] ?? MessagePriority::NORMAL->value,
            'related_id'    => $relatedId,
        ];

        // 可选字段
        if (isset($options['sender_id'])) {
            $messageData['sender_id'] = $options['sender_id'];
        }
        if (isset($options['related_type'])) {
            $messageData['related_type'] = $options['related_type'];
        }
        if ($actionUrl !== null) {
            $messageData['action_url'] = $actionUrl;
        }
        if (isset($options['action_params'])) {
            $messageData['action_params'] = is_array($options['action_params'])
                ? json_encode($options['action_params'], JSON_UNESCAPED_UNICODE)
                : $options['action_params'];
        }
        if (!empty($options['extra_data'])) {
            $messageData['extra_data'] = is_array($options['extra_data'])
                ? json_encode($options['extra_data'], JSON_UNESCAPED_UNICODE)
                : $options['extra_data'];
        }

        // 3. 确定目标租户
        //    显式传入 > 自动从上下文解析 > 单租户默认 '*'
        $tenantId = $this->resolveTenantId($options['tenant_id'] ?? null);

        // 4. 组装推送数据
        $pushData = array_merge(
            $options['extra_data'] ?? [],
            array_filter([
                'related_id'     => $relatedId,
                'action_url'     => $actionUrl,
                'definition_key' => $definitionKey,
            ], fn($v) => $v !== null)
        );

        // 5. 判断是否强制推送（跳过订阅过滤）
        $isForce = !empty($options['force']);

        // 6. 构造事件 → dispatch，走现有事件监听链路
        $event = new MessagePushEvent(
            PushClientType::BACKEND,                        // clientType
            $options['module'] ?? self::DEFAULT_MODULE,     // businessModule
            $tenantId,                                       // tenantId
            $userIds,                                        // userIds
            $options['event'] ?? self::DEFAULT_EVENT,       // event
            $pushData,                                       // data
            $messageData,                                    // messageData
            null,                                            // socketId
            $options['scene'] ?? '',                         // scene
            $isForce,                                        // isForce
        );

        $event->dispatch();


        Logger::info("MessagePusher: 消息已投递", [
            'definition_key' => $definitionKey,
            'definition_id'  => $definition->id,
            'title'          => $title,
            'user_count'     => count(Arr::normalize($userIds)),
            'tenant_id'      => $tenantId,
            'force'          => $isForce,
            'scene'          => $options['scene'] ?? '',
        ]);

        return ['push_count' => 0, 'messages' => []];
    }

    /**
     * 基于内联模版渲染发送消息
     *
     * 模版字符串直接由业务代码传入（不从数据库加载），
     * 将 {变量名} 替换为实际值后作为消息内容发送。
     * 适合模版不固定、临时拼接的场景。
     *
     * 示例：
     * ```php
     * MessagePusher::sendWithContent($userId, 'approval_wait', '审批通知',
     *     '您的订单 {order_no} 已通过 {operator} 审批',
     *     ['{order_no}' => 'ORD2025...', '{operator}' => '张三'],
     *     $orderId
     * );
     * ```
     *
     * @param string|array $userIds       接收者ID 或 ID数组
     * @param string       $definitionKey 消息定义 key
     * @param string       $title         消息标题（也支持 {变量} 替换）
     * @param string       $content       消息内容模版字符串，含 {变量名} 占位符
     * @param array        $contentVars   变量映射
     *                                    如 ['{order_no}' => 'ORD2025...', '{operator}' => '张三']
     * @param string|null  $relatedId     关联业务ID
     * @param string|null  $actionUrl     跳转链接
     * @param array        $options       同 send() 的 options
     *
     * @return array
     */
    public function sendWithContent(
        string|array $userIds,
        string       $definitionKey,
        string       $title,
        string       $content,
        array        $contentVars,
        ?string      $relatedId = null,
        ?string      $actionUrl = null,
        array        $options = []
    ): array {
        $title   = !empty($contentVars) ? strtr($title, $contentVars) : $title;
        $content = !empty($contentVars) ? strtr($content, $contentVars) : $content;

        return $this->send(
            $userIds,
            $definitionKey,
            $title,
            $content,
            $relatedId,
            $actionUrl,
            $options
        );
    }

    /**
     * 基于数据库模板渲染发送消息
     *
     * 从 sys_message_template 加载 definition 关联的模板，
     * 将 {变量名} 替换为实际值后作为消息内容发送。
     *
     * @param string|array $userIds       接收者ID 或 ID数组
     * @param string       $definitionKey 消息定义 key
     * @param array        $templateVars  模板变量映射
     *                                    如 ['{applicant}' => '张三', '{type}' => '请假']
     * @param string|null  $relatedId     关联业务ID
     * @param string|null  $actionUrl     跳转链接
     * @param array        $options       同 send() 的 options，额外支持：
     *                                    - title (string): 覆盖标题，默认取定义名
     *
     * @return array
     */
    public function sendWithTemplate(
        string|array $userIds,
        string       $definitionKey,
        array        $templateVars,
        ?string      $relatedId = null,
        ?string      $actionUrl = null,
        array        $options = []
    ): array {
        $definition = $this->resolveDefinition($definitionKey);
        if ($definition === null) {
            return ['push_count' => 0, 'messages' => []];
        }

        // 渲染标题
        $title = $options['title'] ?? $definition->name;
        if (!empty($templateVars)) {
            $title = strtr($title, $templateVars);
        }

        // 渲染内容（从模板加载）
        $content = $options['content'] ?? '';
        if (empty($content)) {
            $templateContent = $this->resolveTemplate($definition->id);
            if ($templateContent !== null) {
                $content = strtr($templateContent, $templateVars);
            }
        }

        // 移除 options 中的 title/content（避免传给 send 污染 messageData）
        $sendOptions = $options;
        unset($sendOptions['title'], $sendOptions['content']);

        return $this->send(
            $userIds,
            $definitionKey,
            $title,
            $content,
            $relatedId,
            $actionUrl,
            $sendOptions
        );
    }

    // ================================================================
    //  内部方法
    // ================================================================

    /**
     * 解析消息定义
     *
     * 按 definitionKey 从 sys_message_definition 表查找。
     * 无论是核心定义还是插件定义，统一从数据库自动发现。
     * 同请求内结果做静态缓存，避免重复查库。
     */
    private function resolveDefinition(string $definitionKey): ?Definition
    {
        if (array_key_exists($definitionKey, $this->definitionCache)) {
            return $this->definitionCache[$definitionKey];
        }

        // 绕过租户作用域：消息定义是系统级数据（tenant_id=0），不受租户隔离影响
        $definition = Definition::withoutGlobalScopes()
            ->where('key', $definitionKey)
            ->where('enabled', 1)
            ->first();

        $this->definitionCache[$definitionKey] = $definition;

        if ($definition === null) {
            Logger::warning("MessagePusher: 消息定义未找到", ['key' => $definitionKey]);
        }

        return $definition;
    }

    /**
     * 获取定义的默认模板内容（system 渠道优先）
     */
    private function resolveTemplate(int|string $definitionId): ?string
    {
        $cacheKey = (string)$definitionId;
        if (array_key_exists($cacheKey, $this->templateCache)) {
            return $this->templateCache[$cacheKey];
        }

        // 绕过租户作用域：模板是系统级数据，不受租户隔离影响
        $template = Template::withoutGlobalScopes()
            ->whereHas('definitions', function ($q) use ($definitionId) {
                $q->where('definition_id', $definitionId);
            })
            ->where('enabled', 1)
            ->orderByRaw("FIELD(type, 'system', 'email', 'sms', 'webhook')")
            ->first();

        $this->templateCache[$cacheKey] = $template?->content_template;

        return $this->templateCache[$cacheKey];
    }

    /**
     * 解析租户ID
     *
     * 支持三种模式：
     * 1. 显式传入 tenantId → 直接使用
     * 2. 自动从当前请求上下文获取
     * 3. 单租户模式 / 无上下文 → '*'（通配符）
     *
     * @param string|null $tenantId 显式传入的租户ID，null=自动解析
     */
    private function resolveTenantId(?string $tenantId = null): string
    {
        // 显式传入优先
        if ($tenantId !== null && $tenantId !== '') {
            return $tenantId;
        }

        // 单租户模式
        if (TenantContext::isSingleMode()) {
            return '*';
        }

        // 从上下文获取
        $ctxTenantId = TenantContext::getTenantId();
        if ($ctxTenantId !== null && $ctxTenantId !== '') {
            return (string)$ctxTenantId;
        }

        return '*';
    }

    /**
     * 清理实例缓存（主要用于测试）
     */
    public function clearCache(): void
    {
        $this->definitionCache = [];
        $this->templateCache = [];
    }
}
