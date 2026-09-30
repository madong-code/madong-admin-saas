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

namespace app\middleware\api;

use app\middleware\traits\SiteSwitchTrait;
use core\business\tenant\context\TenantContext;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 站点开关中间件（单体级）
 *
 * 检查 default 分组的 site_setting 配置。
 * 仅单体模式下生效，多租户模式下自动跳过（由租户级中间件处理）。
 * 单站点关闭时仅拦截前端请求，管理端不受影响。
 * 建议注册到 api 模块中间件组，仅前端路由生效。
 *
 * @author Mr.April
 * @since  1.0
 */
class SiteSwitchMiddleware implements MiddlewareInterface
{
    use SiteSwitchTrait;

    /**
     * 不触发站点开关检查的路径前缀（只排除安装接口，避免安装失败）
     */
    protected array $except = [
        '/install',
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

        // 非单体模式（多租户模式）下，default 站点开关由租户自己管理，跳过
        if (!TenantContext::isSingleMode()) {
            return $handler($request);
        }

        // 单体级关闭 — 仅前端请求拦截
        $message = $this->getConfigClosedMessage('default');
        if ($message !== null) {
            return $this->buildMaintenanceResponse($message, $request);
        }

        return $handler($request);
    }
}
