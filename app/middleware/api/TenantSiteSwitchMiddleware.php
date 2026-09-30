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
 * 租户级站点开关中间件
 *
 * 检查当前租户的 site_setting 配置。
 * 仅多租户模式下生效，单体模式下自动跳过。
 * 租户关闭自己站点时仅拦截前端请求，管理端不受影响。
 * 建议注册到 api 模块中间件组，在 TenantMiddleware 之后。
 *
 * @author Mr.April
 * @since  1.0
 */
class TenantSiteSwitchMiddleware implements MiddlewareInterface
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

        // 单体模式下，租户级开关无意义，直接跳过
        if (TenantContext::isSingleMode()) {
            return $handler($request);
        }

        // 租户级关闭 — 仅前端请求拦截
        $message = $this->getTenantConfigClosedMessage('default');
        if ($message !== null) {
            return $this->buildMaintenanceResponse($message, $request);
        }

        return $handler($request);
    }
}
