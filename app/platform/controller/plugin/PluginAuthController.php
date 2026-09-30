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

namespace app\platform\controller\plugin;

use app\platform\controller\Base;
use app\platform\middleware\AccessTokenMiddleware;
use app\service\platform\plugin\PluginAuthService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;

#[OA\Tag(name: '应用授权', description: '平台端-应用授权')]
#[Middleware(AccessTokenMiddleware::class)]
final class PluginAuthController extends Base
{
    public function __construct(
        private readonly PluginAuthService $authService
    ) {
    }

    #[OA\Get(
        path: '/plugin/auth',
        summary: '获取应用授权信息',
        tags: ['应用授权']
    )]
    #[Permission('platform:plugin:auth:read')]
    #[SimpleResponse(example: '{"code": 0,"msg": "ok","data": {"company_name": "-","domain": "-","auth_code": ""}}')]
    public function index(Request $request): \support\Response
    {
        $data = $this->authService->getAuthInfo();
        return Json::success('ok', $data);
    }
}
