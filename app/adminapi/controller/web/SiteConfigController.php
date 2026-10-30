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

namespace app\adminapi\controller\web;

use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\service\admin\web\SiteConfigService;
use core\foundation\tool\Json;
use madong\swagger\attribute\Permission;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;

/**
 * 站点设置（网站设置→站点设置；页脚版权 / 备案 / 入口开关 / 百度统计）
 *
 * 多租户：站点设置按登录租户隔离存储（sys_config 走 TenantScope），
 * 平台态（超管/无租户上下文）读写的是 tenant_id 为空的平台级记录。
 */
#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class SiteConfigController
{
    public function __construct(protected SiteConfigService $service)
    {
    }

    #[OA\Get(path: '/web/site-config', summary: '站点设置详情', tags: ['站点设置'])]
    #[Permission(code: 'web:site:read')]
    #[SimpleResponse(schema: [], example: [])]
    public function show(): \support\Response
    {
        try {
            return Json::success('获取成功', $this->service->get());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/web/site-config',
        summary: '保存站点设置（白名单 merge，不影响 site_open/seo_* 等其他键）',
        tags: ['站点设置'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'site_open', type: 'integer', example: 1),
                new OA\Property(property: 'site_url', type: 'string', example: ''),
                new OA\Property(property: 'site_name', type: 'string', example: 'Madong'),
                new OA\Property(property: 'site_logo', type: 'string', example: ''),
                new OA\Property(property: 'site_copyright', type: 'string', example: ''),
                new OA\Property(property: 'site_record_no', type: 'string', example: ''),
                new OA\Property(property: 'site_icp_url', type: 'string', example: 'https://beian.miit.gov.cn/'),
                new OA\Property(property: 'site_network_security', type: 'string', example: ''),
                new OA\Property(property: 'site_network_security_url', type: 'string', example: ''),
                new OA\Property(property: 'show_apply', type: 'boolean', example: true),
                new OA\Property(property: 'show_powered', type: 'boolean', example: true),
                new OA\Property(property: 'contact_email', type: 'string', example: ''),
                new OA\Property(property: 'tongji_enabled', type: 'boolean', example: false),
                new OA\Property(property: 'tongji_key', type: 'string', example: ''),
            ])
        ),
    )]
    #[Permission(code: 'web:site:update')]
    #[SimpleResponse(schema: [], example: [])]
    public function save(Request $request): \support\Response
    {
        try {
            $data = $this->service->save((array) $request->post());
            return Json::success('操作成功', $data);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}
