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

            // 校验 admin_type，仅允许平台管理员访问
            $payload = $jwt->getPayloadFromRequest();
            // JWT 解码后 nested 对象可能仍是 stdClass，转为数组
            $ext     = isset($payload['extra']) ? (array)$payload['extra'] : $payload;
            $types   = $ext['types'] ?? [];
            $types   = is_array($types) ? $types : (array)$types;
            if (!in_array('platform', $types)) {
                // 启用了多租户时，必须拥有 platform 类型才能访问
                if (config('tenant.enable')!== true) {
                    return Json::fail('无权访问平台管理端', [], 403);
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
