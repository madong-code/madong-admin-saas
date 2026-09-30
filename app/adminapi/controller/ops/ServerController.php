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

namespace app\adminapi\controller\ops;

use app\adminapi\controller\Crud;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\adminapi\schema\response\monitor\ServerMonitorResponse;
use core\infrastructure\monitor\ServerMonitor;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\Container;
use support\Request;
use support\annotation\Middleware;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class ServerController extends Crud
{

    #[OA\Get(
        path: '/ops/server',
        summary: '性能监控',
        tags: ['系统监控']
    )]
    #[Permission(code: 'monitor:server:read')]
    #[SimpleResponse(schema:ServerMonitorResponse::class, example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function index(Request $request): \support\Response
    {
        try {
            /** @var ServerMonitor $service */
            $service    = Container::get(ServerMonitor::class);
            $serverInfo = [
                'cpu'    => $service->getCpuInfo(),
                'memory' => $service->getMemoryInfo(),
                'disk'   => $service->getDiskInfo(),
                'php'    => $service->getPhpInfo(),
            ];

            return Json::success('ok', $serverInfo);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), [], $e->getCode());
        }
    }

}
