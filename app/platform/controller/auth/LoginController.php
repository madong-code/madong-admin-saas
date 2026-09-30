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
namespace app\platform\controller\auth;

use app\platform\controller\Base;
use app\platform\validate\auth\LoginValidate;
use app\service\platform\auth\AuthService;
use app\service\platform\system\ConfigService;
use core\foundation\tool\Json;
use core\infrastructure\cache\CacheService;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Container;
use support\Request;

#[OA\Tag(name: '登录', description: '平台登录相关')]
final class LoginController extends Base
{
    public function __construct(AuthService $service)
    {
        $this->service = $service;
    }

    #[OA\Post(
        path: '/login',
        summary: '平台登录',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', description: '用户名/手机号/邮箱', type: 'string'),
                    new OA\Property(property: 'password', description: '密码', type: 'string'),
                    new OA\Property(property: 'captcha', description: '验证码', type: 'string'),
                ]
            )
        ),
        tags: ['登录'],
    )]
    #[AllowAnonymous(requireToken: false, requirePermission: false)]
    #[SimpleResponse(schema: [], example: [])]
    public function login(Request $request): \support\Response
    {
        try {
            $validate = Container::make(LoginValidate::class);
            $validate->scene('login')->check($request->post());

            $result = $this->service->login($request->all());

            return Json::success('登录成功', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], -1);
        }
    }

    #[OA\Post(
        path: '/login/logout',
        summary: '退出登录',
        tags: ['登录'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function logout(Request $request): \support\Response
    {
        try {
            $this->service->logout();
            return Json::success('退出成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/login/site-info',
        summary: '平台站点信息（登录前使用）',
        description: '返回平台级站点配置（code=site_setting_platform），包括站点名称、Logo、Favicon等。',
        tags: ['登录'],
    )]
    #[AllowAnonymous(requireToken: false, requirePermission: false)]
    #[SimpleResponse(schema: ['site_name' => '平台管理', 'site_logo' => '/upload/logo.png', 'site_favicon' => '/favicon.ico'], example: [])]
    public function getSiteInfo(Request $request): \support\Response
    {
        try {
            $configService = Container::get(ConfigService::class);
            $siteInfo = $configService->getSiteInfo();
            return Json::success('ok', $siteInfo);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/login/refresh-token',
        summary: '刷新Token',
        tags: ['登录'],
    )]
    #[AllowAnonymous(requireToken: false, requirePermission: false)]
    #[SimpleResponse(schema: [], example: ['access_token' => 'string', 'refresh_token' => 'string', 'expires_in' => 7200])]
    public function refreshToken(Request $request): \support\Response
    {
        try {
            $result = $this->service->refreshToken();
            return Json::success('ok', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], 401);
        }
    }
}
