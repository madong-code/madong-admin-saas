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
 */

namespace app\middleware\global;

use core\foundation\tool\Json;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 安装检查中间件
 *
 * @author Mr.April
 * @since  1.0
 */
class CheckInstallMiddleware implements MiddlewareInterface
{
    /**
     * 安装相关路径前缀（放行不拦截）
     */
    private const INSTALL_PREFIX = 'install';

    public function process(Request $request, callable $handler): Response
    {
        // 安装相关请求直接放行（如 /install、/adminapi/install、/platformapi/install）
        $segments = explode('/', trim($request->path(), '/'));
        if (in_array(self::INSTALL_PREFIX, $segments, true)) {
            return $handler($request);
        }

        // 检查是否已安装
        $lockFile = base_path() . '/install.lock';
        if (file_exists($lockFile)) {
            return $handler($request);
        }

        // 未安装：API/AJAX 请求返回 JSON，页面请求重定向
        if ($this->isApiRequest($request)) {
            return Json::fail('系统未安装，请先完成安装');
        }

        return redirect('/install');
    }

    /**
     * 通过请求头判断是否为 API/AJAX 请求
     */
    private function isApiRequest(Request $request): bool
    {
        // X-Requested-With: XMLHttpRequest（axios 等库默认发送）
        if ($request->header('X-Requested-With') === 'XMLHttpRequest') {
            return true;
        }

        // Accept: application/json（前端 fetch/axios 的常见设置）
        $accept = $request->header('Accept', '');
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        return false;
    }
}
