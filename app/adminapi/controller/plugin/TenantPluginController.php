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

namespace app\adminapi\controller\plugin;

use app\adminapi\controller\Base;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\service\admin\plugin\TenantPluginService;
use core\business\plugin\traits\SseStreamTrait;
use core\foundation\tool\Sse;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;
use support\Response;

#[OA\Tag(name: '租户插件', description: '应用管理-租户插件')]
#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class TenantPluginController extends Base
{
    use SseStreamTrait;
    public function __construct(
        private readonly TenantPluginService $tenantPluginService
    ) {
    }

    #[OA\Get(
        path: '/tenant/plugin',
        summary: '获取租户可用插件列表',
        tags: ['租户插件'],
        parameters: [
            new OA\Parameter(name: 'keyword', description: '搜索关键词', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('tenant:plugin:list')]
    #[SimpleResponse(example: '{"code": 0,"msg": "ok","data": []}')]
    public function index(Request $request): Response
    {
        $tenantId = $this->getTenantId();
        $keyword  = $request->input('keyword', '');
        $result   = $this->tenantPluginService->getAvailablePlugins($tenantId, $keyword);
        return Json::success('ok', $result);
    }

    #[OA\Put(
        path: '/tenant/plugin/{key}/status',
        summary: '切换插件启用/停用',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'status', description: '0=停用 1=启用', type: 'integer'),
                    ]
                )
            )
        ),
        tags: ['租户插件'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('tenant:plugin:toggle-status')]
    #[SimpleResponse(example: '{"code": 0,"msg": "操作成功"}')]
    public function toggleStatus(Request $request): Response
    {
        $key    = $request->route->param('key');
        $status = (int) $request->input('status', 0);
        $tenantId = $this->getTenantId();

        $this->tenantPluginService->toggleStatus($tenantId, $key, $status);
        return Json::success('操作成功');
    }

    #[OA\Get(
        path: '/tenant/plugin/{key}/detail',
        summary: '获取插件详情（含隔离模式信息）',
        tags: ['租户插件'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('tenant:plugin:detail')]
    #[SimpleResponse(example: '{"code": 0,"msg": "ok","data": {}}')]
    public function detail(Request $request): Response
    {
        $key      = $request->route->param('key');
        $tenantId = $this->getTenantId();

        $result = $this->tenantPluginService->getDetail($tenantId, $key);
        return Json::success('ok', $result);
    }

    #[OA\Get(
        path: '/tenant/plugin/{key}/cleanup-plan',
        summary: '卸载前预览清理计划',
        tags: ['租户插件'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('tenant:plugin:cleanup-plan')]
    #[SimpleResponse(example: '{"code": 0,"msg": "ok","data": {"items": [],"mode": "field"}}')]
    public function cleanupPlan(Request $request): Response
    {
        $key      = $request->route->param('key');
        $tenantId = $this->getTenantId();

        $result = $this->tenantPluginService->getCleanupPlan($tenantId, $key);
        return Json::success('ok', $result);
    }

    #[OA\Get(
        path: '/tenant/plugin/{key}/upgrade-logs',
        summary: '租户插件升级日志',
        tags: ['租户插件'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('tenant:plugin:list')]
    #[SimpleResponse(example: '{"code": 0,"msg": "ok","data": {"version_list": []}}')]
    public function upgradeLogs(Request $request): Response
    {
        $key = $request->route->param('key');
        $pluginService = \support\Container::make(\app\service\core\plugin\PluginService::class);
        $result = $pluginService->getUpgradeLogs($key);
        return Json::success('ok', ['version_list' => $result]);
    }

    #[OA\Get(
        path: '/tenant/plugin/check-environment',
        summary: '租户插件安装前检查(版本/菜单源/授权/租户类型等场景信息)',
        tags: ['租户插件'],
        parameters: [
            new OA\Parameter(name: 'code', description: '插件标识', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('tenant:plugin:install')]
    #[SimpleResponse(example: '{"code": 0,"msg": "ok","data": {"items": [],"isolation_mode": "field"}}')]
    public function checkEnvironment(Request $request): Response
    {
        $code = (string) $request->input('code', '');
        $tenantId = $this->getTenantId();
        $result = $this->tenantPluginService->checkEnvironment($code, $tenantId);
        return Json::success('ok', $result);
    }

    #[OA\Get(
        path: '/tenant/plugin/{key}/install',
        summary: '租户安装插件(SSE流式)',
        tags: ['租户插件'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('tenant:plugin:install')]
    public function install(Request $request): void
    {
        $key      = $request->route->param('key');
        $tenantId = $this->getTenantId();

        $connection = $request->connection;

        // 发送 SSE 响应头
        $this->sendSseHeaders($connection);

        try {
            // 延长执行时间，防止长时间安装被中断
            set_time_limit(0);

            $connection->send(Sse::progress('开始安装插件「' . $key . '」', 0));

            $generator = $this->tenantPluginService->install($key, $tenantId);
            foreach ($generator as $chunk) {
                $connection->send($chunk);
            }
        } catch (\Throwable $e) {
            $connection->send(Sse::error('安装失败：' . $e->getMessage()));
        }
    }

    #[OA\Get(
        path: '/tenant/plugin/{key}/uninstall',
        summary: '租户卸载插件(SSE流式)',
        tags: ['租户插件'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('tenant:plugin:uninstall')]
    public function uninstall(Request $request): void
    {
        $key      = $request->route->param('key');
        $tenantId = $this->getTenantId();

        $connection = $request->connection;

        // 发送 SSE 响应头
        $this->sendSseHeaders($connection);

        try {
            set_time_limit(0);
            $generator = $this->tenantPluginService->uninstall($key, $tenantId);
            foreach ($generator as $chunk) {
                $connection->send($chunk);
            }
        } catch (\Throwable $e) {
            $connection->send(Sse::error('卸载失败：' . $e->getMessage()));
        }
    }

    // ============================================================
    // WP7: 租户更新插件(SSE 流式) + 三态查询
    // ============================================================

    #[OA\Get(
        path: '/tenant/plugin/{key}/update',
        summary: '租户更新插件(SSE流式,委派 Orchestrator)',
        tags: ['租户插件'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'version', description: '目标版本(可选,默认取平台 manifest 当前版本)', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('tenant:plugin:update')]
    public function update(Request $request): void
    {
        $key      = $request->route->param('key');
        $tenantId = $this->getTenantId();
        $version  = (string)$request->input('version', '');

        $connection = $request->connection;
        $this->sendSseHeaders($connection);

        try {
            set_time_limit(0);
            $connection->send(Sse::progress("开始更新插件「{$key}」", 0));

            // 先用 Service.update 拿最终结果, 包成 SSE 帧流
            $steps = [
                ['msg' => "检查版本: 平台→{$version}", 'pct' => 10],
            ];

            // 三态前置检查
            $pluginService = \support\Container::make(\app\service\core\plugin\PluginService::class);
            $platformVersion = $version !== '' ? $version : ($pluginService->getConfig($key)['version'] ?? '1.0.0');
            $status = $this->tenantPluginService->getInstallStatus($tenantId, $key, $platformVersion);
            if ($status === 'available') {
                $connection->send(Sse::error('插件尚未安装,无法更新', [
                    'plugin' => $key,
                    'hint'   => '请先使用 install 端点安装',
                ]));
                return;
            }
            if ($status === 'installed') {
                $connection->send(Sse::progress('当前已是最新版本,跳过更新', 100, [
                    'plugin'   => $key,
                    'skipped'  => true,
                ]));
                return;
            }

            // 走 Orchestrator 真正执行
            $result = $this->tenantPluginService->update($key, $tenantId, $platformVersion);

            $connection->send(Sse::progress(
                sprintf("更新完成: 租户=%s 版本=%s", $tenantId, $result['version'] ?? '?'),
                100,
                $result
            ));
        } catch (\Throwable $e) {
            $connection->send(Sse::error('更新失败：' . $e->getMessage(), [
                'plugin' => $key,
                'tenant_id' => $tenantId,
            ]));
        }
    }

    #[OA\Get(
        path: '/tenant/plugin/{key}/status',
        summary: '租户插件三态(available/installed/upgradeable)',
        tags: ['租户插件'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('tenant:plugin:status')]
    #[SimpleResponse(example: '{"code":0,"msg":"ok","data":{"status":"installed","current_version":"1.0.0","platform_version":"1.0.1"}}')]
    public function installStatus(Request $request): Response
    {
        $key      = $request->route->param('key');
        $tenantId = $this->getTenantId();

        $pluginService = \support\Container::make(\app\service\core\plugin\PluginService::class);
        $platformVersion = (string)($pluginService->getConfig($key)['version'] ?? '');

        $status = $this->tenantPluginService->getInstallStatus($tenantId, $key, $platformVersion);

        // 顺手把当前租户记录的 version 也带上
        $record = \app\model\plugin\TenantPlugin::query()
            ->where('tenant_id', $tenantId)
            ->where('plugin_key', $key)
            ->first(['version', 'sync_status', 'isolation_mode']);

        return Json::success('ok', [
            'status'           => $status,
            'plugin_key'       => $key,
            'tenant_id'        => $tenantId,
            'current_version'  => $record['version'] ?? null,
            'platform_version' => $platformVersion,
            'sync_status'      => $record['sync_status'] ?? null,
            'isolation_mode'   => $record['isolation_mode'] ?? null,
        ]);
    }

    #[OA\Put(
        path: '/tenant/plugin/{key}/ignore-upgrade',
        summary: '租户端忽略/取消忽略插件升级版本',
        tags: ['租户插件'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'version', description: '要忽略的版本, 留空表示取消忽略', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('tenant:plugin:update')]
    public function ignoreUpgrade(Request $request): Response
    {
        $key      = $request->route->param('key');
        $version  = trim((string)$request->input('version', ''));
        $tenantId = $this->getTenantId();

        try {
            $this->tenantPluginService->ignoreUpgrade($tenantId, $key, $version === '' ? null : $version);
            return Json::success('ok', [
                'plugin_key'      => $key,
                'tenant_id'       => $tenantId,
                'ignored_version' => $version === '' ? null : $version,
            ]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/tenant/plugin/{key}/config',
        summary: '读取租户插件配置(按隔离模式存储在租户侧)',
        tags: ['租户插件'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('tenant:plugin:detail')]
    public function config(Request $request): Response
    {
        $key      = $request->route->param('key');
        $tenantId = $this->getTenantId();

        $config = $this->tenantPluginService->getTenantPluginConfig($tenantId, $key);

        return Json::success('ok', [
            'plugin_key' => $key,
            'tenant_id'  => $tenantId,
            'config'     => $config,
        ]);
    }

    #[OA\Put(
        path: '/tenant/plugin/{key}/config',
        summary: '保存租户插件配置(按隔离模式存储在租户侧)',
        tags: ['租户插件'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('tenant:plugin:update')]
    public function saveConfig(Request $request): Response
    {
        $key      = $request->route->param('key');
        $tenantId = $this->getTenantId();
        $config   = $request->input('config', []);

        if (!is_array($config)) {
            return Json::fail('config 必须为对象');
        }

        try {
            $this->tenantPluginService->saveTenantPluginConfig($tenantId, $key, $config);
            return Json::success('ok', [
                'plugin_key' => $key,
                'tenant_id'  => $tenantId,
            ]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 获取当前租户ID（从租户上下文获取）
     *
     * 管理端请求的租户上下文由 AccessTokenMiddleware 从 token.current_tenant 写入 TenantContext，
     * request()->tenantId 未赋值，直接读取会得到空字符串，导致租户插件查询按 tenant_id='' 过滤而查不到记录。
     */
    private function getTenantId(): string
    {
        $tenantId = \core\business\tenant\context\TenantContext::getTenantId();
        return $tenantId !== null ? (string) $tenantId : '';
    }
}
