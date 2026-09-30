<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息通知控制器
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\adminapi\controller\content\message;

use app\adminapi\CurrentUser;
use app\service\admin\content\message\NotifyService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Container;
use support\Request;

#[Middleware(
    \app\adminapi\middleware\AccessTokenMiddleware::class,
    \app\adminapi\middleware\PermissionMiddleware::class,
    \app\adminapi\middleware\OperationMiddleware::class,
)]
final class NotifyController
{
    #[OA\Get(
        path: '/content/message/notify',
        summary: '消息列表',
        tags: ['消息通知'],
    )]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    #[SimpleResponse(schema: [], example: [])]
    public function index(Request $request): \support\Response
    {
        $uid    = Container::make(CurrentUser::class)->id();
        $params = $request->get();

        $service = Container::make(NotifyService::class);
        $result  = $service->getList(
            ['receiver_id' => $uid],
            (int)($params['page'] ?? 1),
            (int)($params['limit'] ?? 15),
            $params['order'] ?? 'created_at desc',
            [],
            $params
        );

        return Json::success($result);
    }

    #[OA\Get(
        path: '/content/message/notify/{id}',
        summary: '消息详情',
        tags: ['消息通知'],
    )]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    #[SimpleResponse(schema: [], example: [])]
    public function show(Request $request): \support\Response
    {
        $id      = $request->route->param('id');
        $service = Container::make(NotifyService::class);
        return Json::success($service->getDetail($id));
    }

    #[OA\Put(
        path: '/content/message/notify/{id}/read',
        summary: '标记已读',
        tags: ['消息通知'],
    )]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success'])]
    public function markRead(Request $request): \support\Response
    {
        $id      = $request->route->param('id');
        $service = Container::make(NotifyService::class);
        $service->markRead($id);
        return Json::success('ok');
    }

    #[OA\Put(
        path: '/content/message/notify/batch-read',
        summary: '批量标记已读',
        tags: ['消息通知'],
    )]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success'])]
    public function batchRead(Request $request): \support\Response
    {
        $params  = $request->post();
        $ids     = $params['ids'] ?? [];
        $service = Container::make(NotifyService::class);
        $service->batchMarkRead($ids);
        return Json::success('ok');
    }

    #[OA\Put(
        path: '/content/message/notify/read-all',
        summary: '全部标记已读',
        tags: ['消息通知'],
    )]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success'])]
    public function readAll(Request $request): \support\Response
    {
        $uid     = Container::make(CurrentUser::class)->id();
        $service = Container::make(NotifyService::class);
        $service->markAllRead($uid);
        return Json::success('ok');
    }

    #[OA\Delete(
        path: '/content/message/notify/{id}',
        summary: '删除消息',
        tags: ['消息通知'],
    )]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success'])]
    public function destroy(Request $request): \support\Response
    {
        $id      = $request->route->param('id');
        $service = Container::make(NotifyService::class);
        $service->delete($id);
        return Json::success('ok');
    }

    #[OA\Delete(
        path: '/content/message/notify/batch-delete',
        summary: '批量删除消息',
        tags: ['消息通知'],
    )]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success'])]
    public function batchDelete(Request $request): \support\Response
    {
        $params  = $request->delete();
        $ids     = $params['ids'] ?? [];
        $service = Container::make(NotifyService::class);
        $service->batchDelete($ids);
        return Json::success('ok');
    }

    #[OA\Get(
        path: '/content/message/notify/unread-count',
        summary: '未读数统计',
        tags: ['消息通知'],
    )]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    #[SimpleResponse(schema: [], example: [])]
    public function unreadCount(Request $request): \support\Response
    {
        $uid     = Container::make(CurrentUser::class)->id();
        $service = Container::make(NotifyService::class);
        return Json::success($service->getUnreadCount($uid));
    }
}
