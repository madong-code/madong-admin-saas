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

namespace app\api\controller\system;

use app\api\controller\Base;
use app\model\tenant\Tenant;
use core\business\tenant\context\TenantContext;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use support\Request;

/**
 * 租户运行时识别控制器
 *
 * 公开端点：让 SPA 启动时能拿到「当前域名对应的租户 ID」，
 * 写入前端 Pinia store，后续所有请求自动带 X-Tenant-Id。
 *
 * ⚠️ 重要：此端点**不走** TenantIdentifyMiddleware
 *    （否则死循环：为了识别租户而调用了识别租户的中间件）。
 *    TenantIdentifyMiddleware 内部需显式跳过此路径。
 */
final class TenantController extends Base
{
    public function __construct() {}

    /**
     * 获取当前域名对应的租户信息
     *
     * 多租户模式下：通过 Host 头匹配 Tenant.domain 字段。
     * 单体模式下：直接返回 {is_multi_mode: false}，无需租户信息。
     */
    #[OA\Get(
        path: "/system/current-tenant",
        summary: "获取当前域名对应的租户信息",
        tags: ['系统配置'],
    )]
    #[SimpleResponse(
        schema: [],
        example: '{"tenant_id":"1001","tenant_name":"租户A","domain":"tenant-a.com","is_multi_mode":true,"matched":true}',
    )]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function current(Request $request): \support\Response
    {
        try {
            // 单体模式 — 无需租户识别
            if (TenantContext::isSingleMode()) {
                return Json::success('操作成功', [
                    'tenant_id'     => null,
                    'tenant_name'   => null,
                    'domain'        => null,
                    'is_multi_mode' => false,
                    'matched'       => false,
                ]);
            }

            // 多租户模式 — 手动从 Host 匹配（不走 TenantIdentifyMiddleware，死循环保护）
            $host = $request->host();
            $host = preg_replace('/:\d+$/', '', $host);
            $tenant = Tenant::findByDomain($host);

            return Json::success('操作成功', [
                'tenant_id'     => $tenant?->id,
                'tenant_name'   => $tenant?->name,
                'domain'        => $host,
                'is_multi_mode' => true,
                'matched'       => (bool) $tenant,
            ]);
        } catch (\Throwable $e) {
            // 数据库未就绪 / 表不存在 / 任何异常 — gracefully 返回未匹配
            return Json::success('操作成功', [
                'tenant_id'     => null,
                'tenant_name'   => null,
                'domain'        => preg_replace('/:\d+$/', '', $request->host()),
                'is_multi_mode' => !TenantContext::isSingleMode(),
                'matched'       => false,
            ]);
        }
    }
}
