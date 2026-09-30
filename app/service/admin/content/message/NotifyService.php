<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息通知服务
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\service\admin\content\message;

use app\dao\content\message\MessageDao;
use app\enum\system\MessageStatus;
use app\model\content\message\Category;
use app\model\content\message\Definition;
use app\model\content\message\Message;
use core\communication\notify\enum\PushClientType;
use core\communication\notify\Notification as NotifyFacade;
use core\foundation\base\BaseService;
use madong\helper\Arr;

/**
 * 消息通知服务
 */
class NotifyService extends BaseService
{
    protected SubscribeService $subscribeService;

    public function __construct(MessageDao $dao, SubscribeService $subscribeService)
    {
        $this->dao                = $dao;
        $this->subscribeService   = $subscribeService;
    }

    public function getList(array $where, int $page = 1, int $limit = 15, string $order = 'created_at desc', array $with = [], array $extraParams = []): array
    {
        $query = Message::where($where);

        if (!empty($extraParams['category_id'] ?? null)) {
            $query->where('category_id', $extraParams['category_id']);
        }
        if (!empty($extraParams['definition_id'] ?? null)) {
            $query->where('definition_id', $extraParams['definition_id']);
        }
        if (!empty($extraParams['module_id'] ?? null)) {
            $query->where('definition_id', $extraParams['module_id']); // 兼容旧参数
        }
        if (isset($extraParams['status']) && $extraParams['status'] !== '') {
            $query->where('status', $extraParams['status'] === 'unread' ? MessageStatus::UNREAD->value : MessageStatus::READ->value);
        }
        if (!empty($extraParams['keyword'] ?? null)) {
            $keyword = $extraParams['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                   ->orWhere('content', 'like', "%{$keyword}%");
            });
        }

        $total = $query->count();
        $list  = $query->with(array_merge(['sender'], $with))
                        ->orderByRaw($order)
                        ->paginate($limit, ['*'], 'page', $page)
                        ->items();

        // 为每条消息补充定义导航信息
        $list = $this->attachDefinitionNavInfo($list);

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 为消息列表补充定义导航信息
     */
    protected function attachDefinitionNavInfo(array $list): array
    {
        $categoryNameCache = [];
        $definitionNavCache = [];

        foreach ($list as &$item) {
            // 缓存分类名
            $catId = $item['category_id'] ?? null;
            if ($catId && !isset($categoryNameCache[$catId])) {
                $cat = Category::find($catId);
                $categoryNameCache[$catId] = $cat ? $cat->name : '';
            }
            $item['category_name'] = $catId ? ($categoryNameCache[$catId] ?? '') : '';

            // 缓存定义导航信息
            $defId = $item['definition_id'] ?? null;
            if ($defId) {
                if (!isset($definitionNavCache[$defId])) {
                    $def = Definition::find($defId);
                    $definitionNavCache[$defId] = $def ? [
                        'nav_type'  => $def->nav_type,
                        'nav_value' => $def->nav_value,
                        'name'      => $def->name,
                    ] : null;
                }

                $navInfo = $definitionNavCache[$defId] ?? null;
                if ($navInfo) {
                    if (empty($item['action_url']) && !empty($navInfo['nav_value'])) {
                        $item['action_url'] = $navInfo['nav_value'];
                    }
                    $item['definition_name'] = $navInfo['name'];
                    $item['module_name']     = $navInfo['name']; // 兼容旧前端
                }
            }
        }
        unset($item);

        return $list;
    }

    public function getDetail($id): array
    {
        $model = Message::with(['sender'])->findOrFail($id);
        return $model->toArray();
    }

    public function markRead($id): bool
    {
        $model = Message::findOrFail($id);
        $model->status  = MessageStatus::READ->value;
        $model->read_at = time();
        return $model->save();
    }

    public function batchMarkRead(array $ids): int
    {
        return Message::whereIn('id', $ids)
                    ->where('status', MessageStatus::UNREAD->value)
                    ->update(['status' => MessageStatus::READ->value, 'read_at' => time()]);
    }

    public function markAllRead(string $userId): int
    {
        return Message::where('receiver_id', $userId)
                    ->where('status', MessageStatus::UNREAD->value)
                    ->update(['status' => MessageStatus::READ->value, 'read_at' => time()]);
    }

    public function delete($id): int|bool
    {
        return Message::destroy($id);
    }

    public function batchDelete(array $ids): int
    {
        Message::destroy($ids);
        return 1;
    }

    public function getUnreadCount(string $userId, ?string $tenantId = null): array
    {
        $query = Message::where('receiver_id', $userId)
                        ->where('status', MessageStatus::UNREAD->value);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $total = $query->count();

        $categoryCounts = Message::where('receiver_id', $userId)
                                ->where('status', MessageStatus::UNREAD->value)
                                ->whereNotNull('category_id')
                                ->groupBy('category_id')
                                ->selectRaw('category_id, count(*) as cnt')
                                ->pluck('cnt', 'category_id')
                                ->toArray();

        $categories = [];
        foreach ($categoryCounts as $id => $count) {
            $cat = Category::find($id);
            $categories[] = [
                'category_id' => (int)$id,
                'key'         => $cat ? $cat->key : '',
                'name'        => $cat ? $cat->name : '',
                'count'       => $count,
            ];
        }

        return ['total' => $total, 'categories' => $categories];
    }

    /**
     * 发送消息（兼容旧参数）
     */
    public function send(
        PushClientType  $clientType,
        string          $businessModule,
        string          $tenantId,
        string|array    $userIds,
        string          $event = 'message',
        array           $data = [],
        array           $messageData = [],
        ?string         $socketId = null
    ): array {
        $userIds = Arr::normalize($userIds);

        // 兼容处理：支持 definition_id（新）和 module_id/category_key/module_key（旧）
        $defId = $messageData['definition_id'] ?? null;
        if (!$defId) {
            $catId = $messageData['category_id'] ?? null;
            if (!$catId && !empty($messageData['category_key'] ?? null)) {
                $cat = Category::where('key', $messageData['category_key'])->first();
                $catId = $cat ? $cat->id : null;
            }

            $modId = $messageData['module_id'] ?? null;
            if (!$modId && !empty($messageData['module_key'] ?? null) && $catId) {
                $mod = Definition::where('category_id', $catId)
                    ->where('key', $messageData['module_key'])
                    ->first();
                $modId = $mod ? $mod->id : null;
            }

            $defId = $modId;
        }

        if ($defId) {
            $filteredIds = [];
            foreach ($userIds as $uid) {
                if ($this->subscribeService->isSubscribed($uid, $defId, $tenantId)) {
                    $filteredIds[] = $uid;
                }
            }
            $userIds = $filteredIds;
        }

        if (empty($userIds)) {
            return ['push_count' => 0, 'messages' => []];
        }

        return NotifyFacade::sendAndRecord(
            $clientType, $businessModule, $tenantId, $userIds, $event, $data, $messageData, $socketId
        );
    }
}
