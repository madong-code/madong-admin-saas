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
namespace app\adminapi\middleware;

use app\adminapi\middleware\helper\SseHelper;
use app\middleware\traits\PlaygroundTrait;
use app\model\tenant\Tenant;
use core\foundation\exception\handler\UnauthorizedHttpException;
use core\security\jwt\JwtToken;
use core\business\tenant\context\TenantContext;
use core\business\tenant\TenantConnectionManager;
use core\foundation\tool\Json;
use madong\swagger\attribute\AllowAnonymous;
use madong\swagger\helper\AnnotationHelper;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * AccessToken 中间件（JWT Token 验证 + 超管 AdminMode + database 模式连接初始化）
 * 功能：
 * 1. 检查请求是否需要跳过 Token 验证
 * 2. 验证 JWT Token 有效性
 * 3. 超管自动设置 TenantContext::AdminMode，跳过租户隔离
 * 4. 从 token 中提取租户信息，database 模式建立租户库连接
 */
#[\Attribute]
final class AccessTokenMiddleware implements MiddlewareInterface
{
    use PlaygroundTrait;

    public function process(Request $request, callable $handler): Response
    {
        $route = $request->route;
        if (!$route || !isset($request->action)) {
            return $handler($request);
        }

        $controllerClass = $request->controller;
        $action          = $request->action;

        $skipAuth = AnnotationHelper::getMethodAnnotation($controllerClass, $action, AllowAnonymous::class);
        if ($skipAuth && !$skipAuth->requireToken) {
            return $handler($request);
        }

        try {
            $jwt    = new JwtToken();
            $userId = $jwt->id();
            if (empty($userId)) {
                throw new UnauthorizedHttpException();
            }

            // Playground 环境：按路由规则拦截（命中则抛异常，由下面 catch 统一处理）
            $this->checkPlaygroundRestriction($request);

            // 从 JWT payload 中提取用户扩展信息
            $payload = $jwt->getPayloadFromRequest();
            $ext     = $payload['extra'] ?? $payload;

            // 平台级超管设置 AdminMode，跳过租户隔离（由 tenant.field_isolation.admin_bypass 控制）
            // 仅 admin_types 包含 platform/root 的平台超管才跳过，租户级超管（is_super=1 但无平台类型）仍受隔离
            if (!empty($ext['is_super'])) {
                $adminTypes = $ext['admin_types'] ?? [];
                if ((in_array('platform', $adminTypes) || in_array('root', $adminTypes))
                    && config('tenant.field_isolation.admin_bypass', false)
                ) {
                    TenantContext::setAdminMode(true);
                }
            }

            // 从 token 中的 current_tenant 提取租户ID，按隔离模式建立上下文
            $currentTenant = $ext['current_tenant'] ?? null;
            if ($currentTenant && !empty($currentTenant['id'])) {
                $tenantId = $currentTenant['id'];
                $dbMode   = $currentTenant['database_mode'] ?? null;

                if ($dbMode === Tenant::MODE_DATABASE) {
                    // database 模式：建立独立库连接
                    TenantConnectionManager::setCurrentConnection($tenantId, false);
                } else {
                    // field 模式（或其它）：仅设置租户上下文，使用主库 + tenant_id 字段隔离
                    TenantContext::setTenant($tenantId);
                    TenantContext::setIsolationMode('field');
                }
            }
        } catch (\Exception $e) {
            // SSE 请求返回单个带 Content-Length 的完整 event-stream 错误响应
            // 注意: 不能走 sendSseErrorViaConnection(无 Content-Length 的多帧直发经 nginx 代理会挂起等待连接关闭)
            if (SseHelper::isSseRequest($request)) {
                return SseHelper::createSseErrorResponse($e->getMessage(), $request->input('uuid'));
            }
            // Playground 限制不设 HTTP 401，其余异常保持 401
            $code = $e instanceof \RuntimeException ? -1 : 401;
            return Json::fail($e->getMessage(), [], $code);
        }
        return $handler($request);
    }
}
