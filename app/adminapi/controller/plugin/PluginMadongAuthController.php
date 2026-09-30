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

namespace app\adminapi\controller\plugin;

use app\adminapi\controller\Base;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\service\core\plugin\PluginRemoteService;
use core\foundation\tool\Json;
use madong\swagger\annotation\auth\AllowAnonymous;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;

#[OA\Tag(name: '应用授权', description: '应用管理-授权信息')]
#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class PluginMadongAuthController extends Base
{
    public function __construct(PluginRemoteService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/plugin/auth-info',
        summary: '获取插件授权信息',
        tags: ['应用授权']
    )]
    #[Permission('madong:delegation:read')]
    #[AllowAnonymous(requireToken: true, requirePermission: true)]
    #[SimpleResponse(example: '{"code": 0,"msg": "ok","data": {"company_name": "-","domain": "-","auth_code": ""}}')]
    public function read(Request $request): \support\Response
    {
        $data = [
            'company_name' => config('madong.company_name', '-'),
            'domain'       => config('madong.domain', '-'),
            'auth_code'    => config('madong.auth_code', ''),
        ];
        return Json::success('ok', $data);
    }

    #[OA\Post(
        path: '/plugin/auth-info',
        summary: '设置插件授权信息',
        tags: ['应用授权'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'auth_code', description: '授权码', type: 'string'),
                        new OA\Property(property: 'auth_secret', description: '授权密钥', type: 'string'),
                    ]
                )
            )
        )
    )]
    #[Permission('madong:delegation:setting')]
    #[SimpleResponse(example: '{"code": 0,"msg": "设置成功"}')]
    public function store(Request $request): \support\Response
    {
        try {
            $authCode   = $request->input('auth_code', '');
            $authSecret = $request->input('auth_secret', '');

            if (empty($authCode) || empty($authSecret)) {
                return Json::fail('授权码和授权密钥不能为空');
            }

            // 远程验证授权
            $this->service->verifyRemoteAuthorization($authCode, $authSecret);

            // 写入本地配置文件
            $this->updateMadongConfig($authCode, $authSecret);

            return Json::success('设置成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 更新 madong.php 配置文件中的授权信息
     */
    private function updateMadongConfig(string $authCode, string $authSecret): void
    {
        $configPath = \core\business\plugin\PluginPath::configPath('madong.php');
        if (!file_exists($configPath)) {
            throw new \Exception('madong.php配置文件不存在');
        }

        $configContent = file_get_contents($configPath);

        $replacements = [
            'auth_code'   => $authCode,
            'auth_secret' => $authSecret,
        ];

        foreach ($replacements as $key => $value) {
            $pattern       = '/\'' . $key . '\'\s*=>\s*env\(\'madong\..*?\'\s*,\s*\'(.*?)\'\)/';
            $replacement   = "'" . $key . "' => env('madong." . ($key === 'auth_code' ? 'code' : 'secret') . "', '" . $value . "')";
            $configContent = preg_replace($pattern, $replacement, $configContent);
        }

        file_put_contents($configPath, $configContent);
    }
}
