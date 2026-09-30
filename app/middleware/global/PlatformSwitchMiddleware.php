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
 * Official Website: https://madong.tech
 *+------------------
 */

namespace app\middleware\global;

use app\middleware\traits\SiteSwitchTrait;
use core\business\tenant\context\TenantContext;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 平台级站点开关中间件
 *
 * 检查 platform 分组的 site_setting 配置。
 * 平台关闭时拦截所有请求（管理端 + 前端），返回 503。
 * 注册到超全局中间件 @ 下，确保所有路由都经过检查。
 *
 * @author Mr.April
 * @since  1.0
 */
class PlatformSwitchMiddleware implements MiddlewareInterface
{
    use SiteSwitchTrait;

    /**
     * 不触发站点开关检查的路径前缀（只排除安装接口，避免安装失败）
     */
    protected array $except = [
        '/install',
        '/platform',
        '/api'
    ];

    public function process(Request $request, callable $handler): Response
    {
        $path = ltrim($request->path(), '/');

        // 跳过安装路径，避免站点关闭时无法安装
        foreach ($this->except as $prefix) {
            if (str_starts_with($path, ltrim($prefix, '/'))) {
                return $handler($request);
            }
        }

        // 非多租户模式（单体模式）下，平台级开关无意义，直接跳过
        if (TenantContext::isSingleMode()) {
            return $handler($request);
        }

        // 平台级关闭 — 对所有请求生效
        $message = $this->getConfigClosedMessage('platform');
        if ($message !== null) {
            return $this->buildMaintenanceResponse($message, $request);
        }

        return $handler($request);
    }
}
