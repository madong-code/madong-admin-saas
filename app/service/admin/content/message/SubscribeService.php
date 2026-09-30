<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息订阅服务
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\service\admin\content\message;

use app\dao\content\message\MessageSubscribeDao;
use app\model\content\message\Category;
use app\model\content\message\Definition;
use app\model\content\message\Subscribe;
use core\foundation\base\BaseService;

/**
 * 消息订阅服务
 *
 * 设计原则：默认全量订阅，表中只存储"退订"记录。
 * - 无记录 → 已订阅（默认）
 * - 有记录（该 user_id + definition_id）→ 已退订
 *
 * 优点：新用户无需初始化，数据量与退订操作数成正比。
 */
class SubscribeService extends BaseService
{
    public function __construct(MessageSubscribeDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取用户的所有退订记录
     */
    public function getSubscriptions(string $userId, ?string $tenantId = null): array
    {
        $query = Subscribe::where('user_id', $userId);
        if ($tenantId !== null) {
            $query->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            });
        } else {
            $query->whereNull('tenant_id');
        }
        return $query->get()->toArray();
    }

    /**
     * 获取扁平化的模块订阅列表（分页+搜索）
     *
     * 使用模型关联查询，返回每条定义+分类名+模版内容+订阅状态。
     * - is_subscribed=true  → 无退订记录（默认已订阅）
     * - is_subscribed=false → 有退订记录（已退订）
     *
     * 搜索：按分类名（category.name）模糊匹配
     *
     * @param string      $userId   用户ID
     * @param string|null $tenantId 租户ID
     * @param int         $page     页码
     * @param int         $limit    每页条数
     * @param string|null $keyword  搜索关键词（匹配分类名）
     *
     * 返回格式: { items: [...], total: int }
     */
    public function getSubscriptionsGrouped(
        string $userId,
        ?string $tenantId = null,
        int $page = 1,
        int $limit = 15,
        ?string $keyword = null,
    ): array {
        // 1. 获取用户的退订 definition_id 集合
        $unsubscribedIds = Subscribe::where('user_id', $userId)
            ->when($tenantId !== null, function ($q) use ($tenantId) {
                $q->where(fn($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId));
            }, function ($q) {
                $q->whereNull('tenant_id');
            })
            ->pluck('definition_id')
            ->map(fn($id) => (int)$id)
            ->toArray();
        $unsubscribedSet = array_flip($unsubscribedIds);

        // 2. 使用模型关联查询定义 + 分类 + 模板
        $defQuery = Definition::with([
            'category',
            'templates' => function ($q) {
                $q->where('enabled', 1)->orderByRaw("FIELD(type, 'system', 'email', 'sms', 'webhook')");
            },
        ])
            ->where('enabled', 1)
            ->whereHas('category', function ($q) use ($keyword) {
                $q->where('enabled', 1)->where('pid', 0);
                if ($keyword !== null && $keyword !== '') {
                    $q->where('name', 'like', "%{$keyword}%");
                }
            })
            ->orderBy(
                Category::select('sort')
                    ->whereColumn('id', 'sys_message_definition.category_id')
                    ->limit(1)
            )
            ->orderBy('sort');

        // 3. 分页
        $total = $defQuery->count();
        /** @var \Illuminate\Database\Eloquent\Collection|Definition[] $rows */
        $rows = $defQuery->forPage($page, $limit)->get();

        // 4. 组装扁平列表
        $items = [];
        foreach ($rows as $def) {
            $defId = (int)$def->id;
            $cat = $def->relationLoaded('category') ? $def->category : null;

            // 取第一条启用模板的 content_template（优先 system 渠道）
            $templates = $def->relationLoaded('templates') ? $def->templates : collect();
            $contentTemplate = '';
            if ($templates->isNotEmpty()) {
                // 优先取 type=system 的模板内容
                $sysTemplate = $templates->firstWhere('type', 'system');
                if ($sysTemplate) {
                    $contentTemplate = $sysTemplate->content_template ?? '';
                } else {
                    $contentTemplate = $templates->first()->content_template ?? '';
                }
            }

            $items[] = [
                'definition_id'   => $defId,
                'module_id'       => $defId,
                'module_key'      => $def->key,
                'module_name'     => $def->name,
                'description'     => $def->description ?? '',
                'content_template' => $contentTemplate,
                'category_id'     => (int)$def->category_id,
                'category_key'    => $cat ? $cat->key : '',
                'category_name'   => $cat ? $cat->name : '',
                'is_subscribed'   => !isset($unsubscribedSet[$defId]),
            ];
        }

        return compact('items', 'total');
    }

    /**
     * 设置单个定义的订阅状态
     *
     * @param string $userId
     * @param int|string|null $definitionId
     * @param bool $subscribe true=订阅（删退订记录） false=退订（写退订记录）
     * @param string|null $tenantId
     */
    public function setSubscription(
        string $userId,
        int|string|null $definitionId = null,
        bool $subscribe = true,
        ?string $tenantId = null,
    ): void {
        if ($definitionId === null) {
            throw new \InvalidArgumentException('definition_id 不能为空');
        }

        $query = Subscribe::where('user_id', $userId)
            ->where('definition_id', $definitionId);

        if ($tenantId === null) {
            $query->whereNull('tenant_id');
        } else {
            $query->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            });
        }

        $model = $query->first();

        if ($subscribe) {
            // 用户要订阅 → 删退订记录
            if ($model) {
                $model->delete();
            }
        } else {
            // 用户要退订 → 写退订记录
            if (!$model) {
                $model = new Subscribe();
                $model->fill([
                    'user_id'       => $userId,
                    'definition_id' => $definitionId,
                    'tenant_id'     => $tenantId,
                ]);
                $model->save();
            }
        }
    }

    /**
     * 批量设置订阅状态
     *
     * settings 格式: [{definition_id, is_subscribed}]
     * - is_subscribed=true  → 订阅（删退订记录）
     * - is_subscribed=false → 退订（写退订记录）
     */
    public function batchSetSubscriptions(string $userId, array $settings, ?string $tenantId = null): bool
    {
        try {
            foreach ($settings as $setting) {
                if (isset($setting['definition_id'])) {
                    $defId = $setting['definition_id'];
                } elseif (isset($setting['module_id'])) {
                    $defId = $setting['module_id'];
                } else {
                    continue;
                }

                $this->setSubscription(
                    $userId,
                    $defId,
                    (bool)($setting['is_subscribed'] ?? true),
                    $tenantId,
                );
            }
            return true;
        } catch (\Throwable $e) {
            throw new \Exception('批量设置订阅失败：' . $e->getMessage());
        }
    }

    /**
     * 检查是否已订阅
     *
     * 无记录=已订阅（返回 true），有记录=已退订（返回 false）
     */
    public function isSubscribed(
        string $userId,
        int|string|null $definitionId = null,
        ?string $tenantId = null,
    ): bool {
        if ($definitionId === null) {
            return false;
        }

        // 有记录=已退订，所以无记录时返回 true
        return !Subscribe::where('user_id', $userId)
            ->where('definition_id', $definitionId)
            ->where(function ($q) use ($tenantId) {
                if ($tenantId !== null) {
                    $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
                } else {
                    $q->whereNull('tenant_id');
                }
            })
            ->exists();
    }

    /**
     * 初始化用户订阅
     *
     * 默认全量订阅，无需做任何事。
     * 新用户不需要退订记录，只有主动退订时才会写入。
     */
    public function initUserSubscriptions(string $userId, ?string $tenantId = null): void
    {
        // 默认全量订阅，无需操作
    }
}
