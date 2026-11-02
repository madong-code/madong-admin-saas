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
namespace app\platform\middleware;

use app\middleware\traits\PlaygroundTrait;
use app\adminapi\middleware\helper\SseHelper;
use core\foundation\exception\handler\UnauthorizedHttpException;
use core\security\jwt\JwtToken;
use core\foundation\tool\Json;
use madong\swagger\attribute\AllowAnonymous;
use madong\swagger\helper\AnnotationHelper;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * AccessToken 中间件（JWT Token 验证）
 * platformapi 专用认证中间件
 *
 * 权限策略（当前阶段）：平台端暂不启用 RBAC，不做权限码校验。
 * - 只校验身份有效性 + 管理员类型（platform/root）
 * - 平台超管（is_super=1）默认拥有全部权限，前端权限码返回 ['*']
 * - 控制器上的 #[Permission] 注解暂时不参与校验，仅保留作为后续接入 RBAC 的元数据
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
        $action = $request->action;

        $skipAuth = AnnotationHelper::getMethodAnnotation($controllerClass, $action, AllowAnonymous::class);
        if ($skipAuth && !$skipAuth->requireToken) {
            return $handler($request);
        }

        try {
            $jwt     = new JwtToken();
            $userId  = $jwt->id();

            if (empty($userId)) {
                throw new UnauthorizedHttpException();
            }

            // Playground 环境：按路由规则拦截（命中则抛异常，由下面 catch 统一处理）
            $this->checkPlaygroundRestriction($request);

            // 校验管理员类型，仅允许平台管理员（platform/root）访问
            // 注意：必须无条件校验，不能依赖 tenant.enable，
            // 否则多租户模式下任意租户 Token 均可访问平台管理端
            $payload    = $jwt->getPayloadFromRequest();
            // JWT 解码后 nested 对象可能仍是 stdClass，转为数组
            $ext        = isset($payload['extra']) ? (array)$payload['extra'] : $payload;
            $adminTypes = $this->resolveAdminTypes($ext);
            if (!$this->hasAdminType($adminTypes, 'platform') && !$this->hasAdminType($adminTypes, 'root')) {
                return Json::fail('无权访问平台管理端', [], 403);
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

    /**
     * 解析 JWT 载荷中的管理员类型编码
     *
     * 兼容两种来源：
     * - 平台端登录写入 types
     * - 租户端登录写入 admin_types
     *
     * @param array $ext JWT payload（extra 段）
     *
     * @return array
     */
    private function resolveAdminTypes(array $ext): array
    {
        $types = $ext['admin_types'] ?? $ext['types'] ?? [];
        if (is_object($types)) {
            $types = (array)$types;
        }
        return is_array($types) ? $types : (array)$types;
    }

    /**
     * 判断类型集合中是否包含指定编码
     *
     * 支持两种结构：
     * - 字符串数组: ['admin', 'platform']
     * - 对象数组: [['id' => 1, 'code' => 'platform']]
     *
     * @param array  $types
     * @param string $code
     *
     * @return bool
     */
    private function hasAdminType(array $types, string $code): bool
    {
        foreach ($types as $type) {
            if (is_array($type) && ($type['code'] ?? null) === $code) {
                return true;
            }
            if (is_object($type) && (($type->code ?? null) === $code)) {
                return true;
            }
            if (is_string($type) && $type === $code) {
                return true;
            }
        }
        return false;
    }
}
