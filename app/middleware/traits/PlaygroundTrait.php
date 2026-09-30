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

namespace app\middleware\traits;

use core\security\jwt\JwtToken;
use Webman\Http\Request;

/**
 * Playground（演示/沙盒）环境公共 Trait
 *
 * 封装 Playground 环境判断逻辑，供 AccessToken 中间件及其他需要限制的中间件共用。
 * 配置见 config/playground.php
 */
trait PlaygroundTrait
{
    /**
     * 检查 Playground 环境是否开启
     *
     * @return bool
     */
    protected function isPlaygroundEnabled(): bool
    {
        return config('playground.enable', false) === true;
    }

    /**
     * 检查当前请求用户是否为 Playground 豁免用户
     *
     * @return bool
     */
    protected function isPlaygroundBypassUser(): bool
    {
        try {
            $payload = (new JwtToken())->getPayloadFromRequest();
            if (!empty($payload['id'])) {
                $bypassUids = config('playground.bypass_uids', [1]);
                return in_array((int)$payload['id'], $bypassUids, true);
            }
        } catch (\Exception) {
            // Token 无效或无法解析时，不豁免
        }
        return false;
    }

    /**
     * 在 Playground 环境下按路由规则拦截请求
     *
     * - 非 Playground 环境 → 正常返回
     * - Playground + 豁免用户 → 正常返回
     * - Playground + 非豁免用户 + 匹配受限路由 + 匹配受限方法 → 抛异常
     * - Playground + 非豁免用户 + 未匹配受限路由 → 正常返回
     *
     * @param Request $request 当前请求对象
     * @throws \RuntimeException 命中限制时抛出，由外层 catch 统一处理
     */
    protected function checkPlaygroundRestriction(Request $request): void
    {
        if (!$this->isPlaygroundEnabled()) {
            return;
        }

        if ($this->isPlaygroundBypassUser()) {
            return;
        }

        $currentPath = $request->path();
        $method      = strtoupper($request->method());
        $message     = config('playground.message', '演示环境,不支持当前操作');
        $methods     = config('playground.methods', ['PUT', 'POST', 'DELETE']);
        $routes      = config('playground.routes', []);

        // 按路由单独指定的方法（覆盖全局 methods, 适用于 GET 类敏感操作）
        $routeMethods = config('playground.route_methods', []);

        foreach ($routes as $pattern) {
            if (preg_match("#^$pattern$#", $currentPath)) {
                if (in_array($method, $methods, true)) {
                    throw new \RuntimeException($message);
                }
            }
        }

        foreach ($routeMethods as $pattern => $limitedMethods) {
            if (preg_match("#^$pattern$#", $currentPath)) {
                if (in_array($method, (array)$limitedMethods, true)) {
                    throw new \RuntimeException($message);
                }
            }
        }
    }
}
