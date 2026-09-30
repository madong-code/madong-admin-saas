<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息订阅控制器
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\adminapi\controller\content\message;

use app\adminapi\CurrentUser;
use app\service\admin\content\message\SubscribeService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Container;
use support\Request;

#[Middleware(
    \app\adminapi\middleware\AccessTokenMiddleware::class,
    \app\adminapi\middleware\PermissionMiddleware::class,
)]
final class SubscribeController
{
    #[OA\Get(
        path: '/content/message/subscribe',
        summary: '我的订阅（分页）',
        tags: ['消息订阅'],
    )]
    #[Permission(code: 'message:subscribe:list')]
    #[SimpleResponse(schema: [], example: [])]
    public function mine(Request $request): \support\Response
    {
        $uid     = Container::make(CurrentUser::class)->id();
        $params  = $request->get();
        $service = Container::make(SubscribeService::class);
        $result  = $service->getSubscriptionsGrouped(
            $uid,
            null,
            (int)($params['page'] ?? 1),
            (int)($params['limit'] ?? 15),
            $params['keyword'] ?? null,
        );
        return Json::success($result);
    }

    #[OA\Post(
        path: '/content/message/subscribe/batch-set',
        summary: '批量设置订阅',
        tags: ['消息订阅'],
    )]
    #[Permission(code: 'message:subscribe:batch-set')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success'])]
    public function batchSet(Request $request): \support\Response
    {
        $uid      = Container::make(CurrentUser::class)->id();
        $params   = $request->post();
        $settings = $params['settings'] ?? [];
        $service  = Container::make(SubscribeService::class);
        $service->batchSetSubscriptions($uid, $settings);
        return Json::success('ok');
    }

    #[OA\Post(
        path: '/content/message/subscribe/init',
        summary: '初始化订阅',
        tags: ['消息订阅'],
    )]
    #[Permission(code: 'message:subscribe:init')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success'])]
    public function init(Request $request): \support\Response
    {
        $uid     = Container::make(CurrentUser::class)->id();
        $service = Container::make(SubscribeService::class);
        $service->initUserSubscriptions($uid);
        return Json::success('ok');
    }
}
