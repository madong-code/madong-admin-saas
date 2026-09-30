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

namespace app\middleware\traits;

use app\model\system\config\Config;
use core\business\tenant\context\TenantContext;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * 站点开关公共 Trait
 *
 * 封装 getConfigClosedMessage / getTenantConfigClosedMessage / buildMaintenanceResponse 方法，
 * 供 PlatformSwitchMiddleware、SiteSwitchMiddleware、TenantSiteSwitchMiddleware 共用。
 */
trait SiteSwitchTrait
{
    /**
     * 检查指定分组的站点开关是否关闭
     *
     * @param string $groupCode 分组编码
     * @return string|null 关闭时返回维护消息，开启或未配置时返回 null
     */
    protected function getConfigClosedMessage(string $groupCode): ?string
    {
        try {
            $configModel = Config::withoutGlobalScopes()
                ->whereNull('tenant_id')
                ->where('code', 'site_setting')
                ->where('group_code', $groupCode)
                ->first();
        } catch (\Exception $e) {
            return null;
        }

        if (!$configModel) {
            return null;
        }

        $content = $configModel->getRawOriginal('content');
        if (empty($content)) {
            return null;
        }

        $config = is_string($content) ? json_decode($content, true) : $content;
        if (!is_array($config) || !array_key_exists('site_open', $config)) {
            return null;
        }

        $siteOpen = $config['site_open'];
        if ((string)$siteOpen === '1') {
            return null;
        }

        return $config['maintenance_message'] ?? '站点维护中，请稍后再试...';
    }

    /**
     * 检查指定租户的站点开关是否关闭
     *
     * @param string $groupCode 分组编码
     * @param int|string|null $tenantId 租户 ID，为 null 时从 TenantContext 获取
     * @return string|null 关闭时返回维护消息，开启或未配置时返回 null
     */
    protected function getTenantConfigClosedMessage(string $groupCode, int|string|null $tenantId = null): ?string
    {
        $tenantId ??= TenantContext::getTenantId();
        if (empty($tenantId)) {
            return null;
        }

        try {
            $configModel = Config::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('code', 'site_setting')
                ->where('group_code', $groupCode)
                ->first();
        } catch (\Exception $e) {
            return null;
        }

        if (!$configModel) {
            return null;
        }

        $content = $configModel->getRawOriginal('content');
        if (empty($content)) {
            return null;
        }

        $config = is_string($content) ? json_decode($content, true) : $content;
        if (!is_array($config) || !array_key_exists('site_open', $config)) {
            return null;
        }

        $siteOpen = $config['site_open'];
        if ((string)$siteOpen === '1') {
            return null;
        }

        return $config['maintenance_message'] ?? '站点维护中，请稍后再试...';
    }

    /**
     * 构建维护中响应
     *
     * 根据请求 Accept 头自动决定响应格式：
     * - text/html → 返回美观的 HTML 维护页面
     * - 其他 → 返回 JSON
     *
     * @param string $message
     * @param Request $request
     * @return Response
     */
    protected function buildMaintenanceResponse(string $message, Request $request): Response
    {
        // 浏览器直接访问时返回 HTML 页面
        if (str_contains($request->header('accept', ''), 'text/html')) {
            $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统维护中</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "PingFang SC", "Microsoft YaHei", sans-serif;
            background: linear-gradient(145deg, #0b1120 0%, #111c2f 50%, #0a1628 100%);
            position: relative;
            overflow: hidden;
        }
        /* 网格背景 */
        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(55, 65, 81, 0.15) 1px, transparent 1px),
                linear-gradient(90deg, rgba(55, 65, 81, 0.15) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(ellipse 80% 60% at 50% 50%, black 30%, transparent 70%);
            -webkit-mask-image: radial-gradient(ellipse 80% 60% at 50% 50%, black 30%, transparent 70%);
        }
        /* 光晕 */
        body::after {
            content: '';
            position: absolute;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.12), transparent 70%);
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            animation: glow 6s ease-in-out infinite alternate;
        }
        @keyframes glow {
            0% { transform: translate(-50%, -50%) scale(1); opacity: 0.6; }
            100% { transform: translate(-50%, -50%) scale(1.4); opacity: 1; }
        }
        @keyframes floatY {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(24px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .card {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 28px;
            padding: 56px 64px;
            max-width: 460px;
            text-align: center;
            box-shadow: 0 0 0 1px rgba(255,255,255,0.03), 0 30px 80px rgba(0,0,0,0.5);
            animation: fadeIn 0.7s ease-out;
        }
        .icon-orb {
            width: 96px; height: 96px;
            margin: 0 auto 28px;
            position: relative;
            animation: floatY 4s ease-in-out infinite;
        }
        .icon-orb .ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 1.5px solid rgba(59, 130, 246, 0.2);
        }
        .icon-orb .ring:nth-child(1) { inset: 0; animation: spin 8s linear infinite; }
        .icon-orb .ring:nth-child(2) { inset: 6px; animation: spin 12s linear infinite reverse; }
        .icon-orb .ring:nth-child(3) { inset: 12px; animation: spin 6s linear infinite; }
        .icon-orb .ring-inner {
            position: absolute;
            inset: 18px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(99, 102, 241, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon-orb .ring-inner svg {
            width: 32px; height: 32px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .ring-thumb {
            position: absolute;
            width: 8px; height: 8px;
            background: #3b82f6;
            border-radius: 50%;
            top: -4px; left: 50%;
            transform: translateX(-50%);
            box-shadow: 0 0 12px rgba(59, 130, 246, 0.5);
        }
        h1 {
            font-size: 22px;
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }
        .sub {
            font-size: 13px;
            color: #64748b;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 28px;
        }
        .msg-box {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 12px;
            padding: 16px 20px;
            font-size: 14px;
            color: #94a3b8;
            line-height: 1.7;
        }
        .footer {
            margin-top: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .footer .dot {
            display: block;
            width: 6px; height: 6px;
            background: #22c55e;
            border-radius: 50%;
            animation: pulse 1.8s ease-in-out infinite;
        }
        .footer span {
            font-size: 12px;
            color: #475569;
            letter-spacing: 1px;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.5); }
            50% { box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-orb">
            <div class="ring"><div class="ring-thumb"></div></div>
            <div class="ring"><div class="ring-thumb"></div></div>
            <div class="ring"><div class="ring-thumb"></div></div>
            <div class="ring-inner">
                <svg viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 20h9"/>
                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                </svg>
            </div>
        </div>
        <h1>系统升级维护中</h1>
        <p class="sub">Scheduled Maintenance</p>
        <div class="msg-box">{$message}</div>
        <div class="footer">
            <i class="dot"></i>
            <span>正在处理，请稍候</span>
        </div>
    </div>
</body>
</html>
HTML;
            return new Response(503, ['Content-Type' => 'text/html; charset=utf-8'], $html);
        }

        return new Response(503, ['Content-Type' => 'application/json'],
            json_encode(['code' => -1, 'msg' => $message], JSON_UNESCAPED_UNICODE)
        );
    }
}
