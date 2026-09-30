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

namespace app\api\middleware;


use core\foundation\exception\handler\UnauthorizedHttpException;
use core\security\jwt\JwtToken;
use core\foundation\tool\Json;
use madong\swagger\attribute\AllowAnonymous;
use madong\swagger\helper\AnnotationHelper;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * ApiAccessToken 中间件（JWT Token 验证）
 * 功能：
 * 1. 检查请求是否需要跳过 Token 验证（通过 SkipAuth 注解）
 * 2. 验证 JWT Token 有效性，无效则返回 401
 */
final class ApiAccessTokenMiddleware implements MiddlewareInterface
{

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
            $jwt = new JwtToken();
            $userId = $jwt->id();
            if (empty($userId)) {
                throw new UnauthorizedHttpException();
            }
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), [], 401);
        }
        return $handler($request);
    }

}