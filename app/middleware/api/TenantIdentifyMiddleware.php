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
namespace app\middleware\api;

use app\model\tenant\Tenant;
use core\business\tenant\context\TenantContext;
use core\business\tenant\TenantConnectionManager;
use core\foundation\exception\handler\TenantException;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

/**
 * 前端 API 租户识别中间件
 *
 * 按以下优先级识别当前租户：
 * 1. 请求头 X-Tenant-Id
 * 2. 请求参数 tenant_id
 * 3. 请求域名（通过 Tenant::findByDomain 自动匹配）
 *
 * 仅多租户模式下生效，单体模式直接跳过。
 * 多租户模式下无法识别租户时将抛出 TenantException。
 * 用于 api 路由组，为租户级站点开关等中间件提供上下文。
 *
 * @author Mr.April
 * @since  1.0
 */
class TenantIdentifyMiddleware implements MiddlewareInterface
{
    /**
     * 跳过此中间件的路径前缀（不需要租户上下文的公开端点）
     *
     * webman 的 $request->path() 返回不带 pathPrefix('/api') 的路径，
     * 所以 skip 用 controller 级路径（如 /system/current-tenant）。
     *
     * /system/current-tenant —— 此端点自己手动调 Tenant::findByDomain()，
     *   如果 TenantIdentifyMiddleware 也来跑一遍会形成死循环。
     */
    private const SKIP_PREFIXES = [
        '/system/current-tenant',
    ];

    public function process(Request $request, callable $next): Response
    {
        // 死循环保护：current-tenant 端点自己就是用来识别租户的，不能再跑本中间件
        // webman 的 $request->path() 不带 pathPrefix('/api')，
        // 但有些中间件场景下 path 可能已拼上前缀，两种都检查
        $path = '/' . ltrim($request->path(), '/');
        $fullPath = '/' . ltrim($request->pathPrefix() . $request->path(), '/');
        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix) || str_starts_with($fullPath, $prefix)) {
                return $next($request);
            }
        }

        // 单体模式下跳过
        if (TenantContext::isSingleMode()) {
            return $next($request);
        }

        // 多租户模式下，必须识别出当前请求所属的租户
        $tenantId = $this->resolveTenantId($request);

        // 开发/localhost 环境下自动回退到第一个激活的租户
        // 条件：debug 模式 或 配置了 auto_select_first 或 请求 host 是 localhost/127.0.0.1
        if (empty($tenantId)) {
            $host = preg_replace('/:\d+$/', '', $request->host());
            $isLocalhost = in_array($host, ['127.0.0.1', 'localhost'], true);
            $autoSelect = $isLocalhost
                || config('app.debug', false)
                || config('tenant.auto_select_first', false);
            if ($autoSelect) {
                try {
                    $firstTenant = Tenant::where('status', Tenant::STATUS_ACTIVE)->orderBy('id')->first();
                    if ($firstTenant) {
                        $tenantId = $firstTenant->id;
                    }
                } catch (\Throwable $e) {
                    // 数据库/表未就绪时静默跳过
                }
            }
        }

        if (empty($tenantId)) {
            throw new TenantException('无法识别租户', ['host' => $request->host()], 401);
        }

        // 注入租户上下文
        TenantContext::setTenant($tenantId);

        // 根据租户隔离模式设置连接上下文
        $tenantInfo = TenantContext::getTenantInfo();
        if ($tenantInfo) {
            $dbMode = $tenantInfo['database_mode'] ?? '';
            if ($dbMode === Tenant::MODE_DATABASE) {
                TenantConnectionManager::setCurrentConnection($tenantId, false);
            } else {
                TenantContext::setIsolationMode('field');
            }
        }

        return $next($request);
    }

    /**
     * 按优先级依次尝试各识别方式
     *
     * @return string|null 识别到的租户ID，未识别到返回 null
     */
    private function resolveTenantId(Request $request): ?string
    {
        // 优先级 1&2: 请求头 / 请求参数
        $tenantId = $request->header('X-Tenant-Id')
            ?? $request->input('tenant_id')
            ?? null;

        if (!empty($tenantId)) {
            return $tenantId;
        }

        // 优先级 3: 请求域名识别
        return $this->identifyByDomain($request);
    }

    /**
     * 通过请求域名识别租户
     *
     * 从请求 Host 中提取域名，与 saas_tenant 表的 domain 字段匹配。
     * 需在 config/tenant.php 中启用 features.multi_domain 特性开关。
     */
    private function identifyByDomain(Request $request): ?string
    {
        // 检查多域名特性开关
        if (!config('tenant.features.multi_domain', false)) {
            return null;
        }

        $host = $request->host();

        if (empty($host)) {
            return null;
        }

        // 去除端口号（如 example.com:8080 → example.com）
        $host = preg_replace('/:\d+$/', '', $host);

        // 通过域名查找激活状态的租户
        $tenant = Tenant::findByDomain($host);

        // Tenant::id 是 bigint unsigned，PHP 返回 int；方法签名要求 ?string
        return $tenant ? (string) $tenant->id : null;
    }
}
