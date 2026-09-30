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

namespace app\platform\controller\plugin;

use app\platform\controller\Base;
use app\service\platform\plugin\PluginTenantAuthService;
use app\service\admin\plugin\TenantPluginService;
use app\service\core\plugin\PluginBatchExecutor;
use core\business\plugin\traits\SseStreamTrait;
use core\foundation\tool\Json;
use core\foundation\tool\Sse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Container;
use support\Request;

#[OA\Tag(name: '平台-租户授权', description: '平台端应用管理-租户插件授权')]
#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class PluginTenantAuthController extends Base
{
    use SseStreamTrait;

    public function __construct(
        private readonly PluginTenantAuthService $tenantAuthService
    ) {
    }

    #[OA\Get(
        path: '/plugin/tenant-auth',
        summary: '获取租户授权列表',
        tags: ['平台-租户授权'],
        parameters: [
            new OA\Parameter(name: 'page', description: '页码', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limit', description: '每页数量', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tenant_id', description: '租户ID', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('platform:plugin:tenant-auth:list')]
    #[PageResponse(example: '{"code": 0,"msg": "ok","data": {"list": [],"total": 0}}')]
    public function index(Request $request): \support\Response
    {
        $tenantId = $request->input('tenant_id', '');

        $result = $this->tenantAuthService->getAuthList($tenantId);
        // 统一响应字段名: list → items
        if (isset($result['list'])) {
            $result['items'] = $result['list'];
            unset($result['list']);
        }
        return Json::success('ok', $result);
    }

    #[OA\Post(
        path: '/plugin/tenant-auth',
        summary: '批量设置租户插件授权',
        tags: ['平台-租户授权'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'tenant_id', description: '租户ID', type: 'string'),
                        new OA\Property(property: 'plugin_keys', description: '插件标识列表', type: 'array', items: new OA\Items(type: 'string')),
                        new OA\Property(property: 'auth_status', description: '授权状态: authorized/trial', type: 'string'),
                    ]
                )
            )
        )
    )]
    #[Permission('platform:plugin:tenant-auth:store')]
    #[SimpleResponse(example: '{"code": 0,"msg": "授权设置成功"}')]
    public function store(Request $request): \support\Response
    {
        // 支持批量:tenant_ids(数组/逗号分隔),向后兼容单租户 tenant_id
        $rawTenantIds = $request->input('tenant_ids', []);
        if (empty($rawTenantIds)) {
            $single = $request->input('tenant_id', '');
            $rawTenantIds = $single ? [$single] : [];
        }
        $tenantIds = is_array($rawTenantIds) ? $rawTenantIds : explode(',', (string)$rawTenantIds);
        $tenantIds = array_values(array_filter(array_map('strval', $tenantIds)));

        $pluginKeys = $request->input('plugin_keys', []);
        $authStatus = $request->input('auth_status', 'authorized');

        if (empty($tenantIds)) {
            return Json::fail('租户ID不能为空');
        }
        if (empty($pluginKeys)) {
            return Json::fail('插件列表不能为空');
        }

        $success = 0;
        $failed  = [];
        foreach ($tenantIds as $tid) {
            try {
                $this->tenantAuthService->setAuth($tid, $pluginKeys, $authStatus);
                $success++;
            } catch (\Throwable $e) {
                $failed[] = ['tenant_id' => $tid, 'error' => $e->getMessage()];
            }
        }
        return Json::success('批量授权完成', [
            'success'   => $success,
            'total'     => count($tenantIds),
            'failed'    => $failed,
        ]);
    }

    // ============================================================
    // WP7: 矩阵 / 统计 / 占用清单 / 批量操作 / 级联卸载
    // ============================================================

    #[OA\Get(
        path: '/plugin/tenant-auth/matrix',
        summary: '平台×租户授权矩阵',
        tags: ['平台-租户授权'],
        parameters: [
            new OA\Parameter(name: 'plugin_keys', description: '筛选插件列表(逗号分隔)', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tenant_ids', description: '筛选租户列表(逗号分隔)', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('platform:plugin:tenant-auth:matrix')]
    #[SimpleResponse(example: '{"code":0,"msg":"ok","data":{"plugins":[],"tenants":[],"matrix":{}}}')]
    public function matrix(Request $request): \support\Response
    {
        $pluginKeys = $this->csvParam($request, 'plugin_keys');
        $tenantIds  = $this->csvParam($request, 'tenant_ids');
        return Json::success('ok', $this->tenantAuthService->getMatrix($pluginKeys, $tenantIds));
    }

    #[OA\Get(
        path: '/plugin/tenant-auth/stats',
        summary: '升级统计',
        tags: ['平台-租户授权'],
        parameters: [
            new OA\Parameter(name: 'plugin_keys', description: '筛选插件列表(逗号分隔)', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('platform:plugin:tenant-auth:stats')]
    #[SimpleResponse(example: '{"code":0,"msg":"ok","data":[],"total":0}')]
    public function stats(Request $request): \support\Response
    {
        $pluginKeys = $this->csvParam($request, 'plugin_keys');
        $list = $this->tenantAuthService->getStats($pluginKeys);
        return Json::success('ok', [
            'items' => $list,
            'total' => count($list),
        ]);
    }

    #[OA\Get(
        path: '/plugin/tenant-auth/occupying',
        summary: '插件占用清单',
        tags: ['平台-租户授权'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('platform:plugin:tenant-auth:occupying')]
    #[SimpleResponse(example: '{"code":0,"msg":"ok","data":{"plugin_key":"","count":0,"items":[]}}')]
    public function occupying(Request $request): \support\Response
    {
        $key = (string)$request->input('key', '');
        if ($key === '') {
            return Json::fail('插件标识不能为空');
        }
        return Json::success('ok', $this->tenantAuthService->getOccupyingTenants($key));
    }

    #[OA\Get(
        path: '/plugin/tenant-auth/dependencies',
        summary: '依赖项检查',
        tags: ['平台-租户授权'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('platform:plugin:tenant-auth:dependencies')]
    #[SimpleResponse(example: '{"code":0,"msg":"ok","data":{"missing":[],"satisfied":[]}}')]
    public function dependencies(Request $request): \support\Response
    {
        $key = (string)$request->input('key', '');
        if ($key === '') {
            return Json::fail('插件标识不能为空');
        }
        $pluginService = \support\Container::make(\app\service\core\plugin\PluginService::class);
        $config = $pluginService->getConfig($key);
        $requires = (array)($config['require'] ?? []);
        $satisfied = [];
        $missing = [];
        foreach ($requires as $dep) {
            $ok = $pluginService->isInstalled($dep);
            if ($ok) {
                $satisfied[] = $dep;
            } else {
                $missing[] = $dep;
            }
        }
        return Json::success('ok', [
            'plugin_key' => $key,
            'requires'   => $requires,
            'satisfied'  => $satisfied,
            'missing'    => $missing,
        ]);
    }

    #[OA\Post(
        path: '/plugin/tenant-auth/batch-update',
        summary: '批量升级(扇出到多个租户)',
        tags: ['平台-租户授权'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'code', description: '插件标识', type: 'string'),
                        new OA\Property(property: 'version', description: '目标版本', type: 'string'),
                        new OA\Property(property: 'tenant_ids', description: '目标租户ID列表', type: 'array', items: new OA\Items(type: 'string')),
                        new OA\Property(property: 'force', description: '是否强制', type: 'boolean'),
                    ]
                )
            )
        )
    )]
    #[Permission('platform:plugin:tenant-auth:batch-update')]
    #[SimpleResponse(example: '{"code":0,"msg":"ok","data":{"mode":"inline","processed":0,"total":0}}')]
    public function batchUpdate(Request $request): \support\Response
    {
        $code      = (string)$request->input('code', '');
        $version   = (string)$request->input('version', '');
        $tenantIds = (array)$request->input('tenant_ids', []);
        $force     = (bool)$request->input('force', false);

        if ($code === '' || $version === '' || empty($tenantIds)) {
            return Json::fail('参数不完整');
        }

        $executor = \support\Container::make(\app\service\core\plugin\PluginBatchExecutor::class);
        $opts = [
            'force'       => $force,
            'requestedBy' => (string)($request->user->id ?? ''),
        ];

        // 走 SSE 流(可同时返回最终结果)
        $sseBuf = [];
        $result = $executor->dispatch($code, $version, 'update', $tenantIds, $opts, function ($msg, $pct, $extra) use (&$sseBuf) {
            $sseBuf[] = ['msg' => $msg, 'pct' => $pct, 'extra' => $extra];
        });

        return Json::success('ok', $result + ['progress' => $sseBuf]);
    }

    #[OA\Post(
        path: '/plugin/tenant-auth/cascade-uninstall',
        summary: '强制级联卸载(先租户后平台, 支持指定租户)',
        tags: ['平台-租户授权'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'code', description: '插件标识(兼容 plugin_key)', type: 'string'),
                        new OA\Property(property: 'version', description: '版本, 留空按插件当前版本兜底', type: 'string'),
                        new OA\Property(property: 'force', description: '是否强制, 默认 true', type: 'boolean'),
                        new OA\Property(property: 'tenant_ids', description: '指定租户ID列表, 仅卸载这些租户(菜单+数据); 留空=卸载该插件全部租户侧安装. 本接口只清理租户侧, 不卸载平台层插件包', type: 'array', items: new OA\Items(type: 'string')),
                    ]
                )
            )
        )
    )]
    #[Permission('platform:plugin:tenant-auth:cascade-uninstall')]
    #[SimpleResponse(example: '{"code":0,"msg":"ok","data":{"stage":"cascade_uninstall","tenants":{}}}')]
    public function cascadeUninstall(Request $request): \support\Response
    {
        $code    = (string)($request->input('code') ?: $request->input('plugin_key', ''));
        $version = (string)$request->input('version', '');
        $force   = (bool)$request->input('force', true);

        if ($code === '') {
            return Json::fail('参数不完整');
        }

        // 版本兜底: 取插件当前版本
        if ($version === '') {
            try {
                /** @var \app\service\core\plugin\PluginService $pluginService */
                $pluginService = \support\Container::make(\app\service\core\plugin\PluginService::class);
                $config  = $pluginService->getConfig($code);
                $version = (string)($config['version'] ?? '1.0.0');
            } catch (\Throwable $e) {
                $version = '1.0.0';
            }
        }

        $rawTenantIds = (array)$request->input('tenant_ids', []);
        $tenantIds    = array_values(array_filter(array_map('strval', $rawTenantIds)));

        $orchestrator = \support\Container::make(\app\service\core\plugin\PluginLifecycleOrchestrator::class);
        $opts = [
            'force'       => $force,
            'requestedBy' => (string)($request->user->id ?? ''),
            'tenant_ids'  => $tenantIds,
        ];

        try {
            $result = $orchestrator->cascadeUninstall($code, $version, $opts);
            return Json::success('ok', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/platform/plugin/tenant-auth/upgrade-policy',
        summary: '平台端设置租户插件是否允许升级',
        tags: ['插件租户授权'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'tenant_id', description: '租户ID', type: 'string'),
                    new OA\Property(property: 'code', description: '插件标识', type: 'string'),
                    new OA\Property(property: 'allowed', description: '是否允许升级', type: 'boolean'),
                ]
            )
        )
    )]
    #[Permission('platform:plugin:tenant-auth:store')]
    public function upgradePolicy(Request $request): \support\Response
    {
        $tenantId = (string)$request->input('tenant_id', '');
        $code     = (string)$request->input('code', '');
        $allowed  = (bool)$request->input('allowed', true);

        if ($tenantId === '' || $code === '') {
            return Json::fail('参数不完整');
        }

        $service = \support\Container::make(\app\service\admin\plugin\TenantPluginService::class);

        try {
            $service->setUpgradePolicy($tenantId, $code, $allowed);
            return Json::success('ok', [
                'tenant_id'     => $tenantId,
                'plugin_key'    => $code,
                'allow_upgrade' => $allowed ? 1 : 0,
            ]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/plugin/tenant-auth/upgrade-stream',
        summary: '批量升级(SSE流式)',
        tags: ['平台-租户授权'],
        parameters: [
            new OA\Parameter(name: 'code', description: '插件标识', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'version', description: '目标版本', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tenant_ids', description: '目标租户ID(逗号分隔); 留空=全部可升级租户', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'exclude_ids', description: '排除的租户ID(逗号分隔)', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'force', description: '是否强制升级', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ]
    )]
    #[Permission('platform:plugin:tenant-auth:batch-update')]
    public function upgradeStream(Request $request): void
    {
        $code    = (string)$request->input('code', '');
        $version = (string)$request->input('version', '');
        $force   = (bool)$request->input('force', false);

        $connection = $request->connection;
        $this->sendSseHeaders($connection);

        try {
            set_time_limit(0);

            if ($code === '' || $version === '') {
                $connection->send(Sse::error('插件标识或目标版本不能为空'));
                return;
            }

            $targets = $this->tenantAuthService->resolveUpgradeTargets(
                $code,
                $version,
                $this->csvParam($request, 'tenant_ids'),
                $this->csvParam($request, 'exclude_ids')
            );

            if (empty($targets)) {
                $connection->send(Sse::completed('没有需要升级的租户', ['total' => 0]));
                return;
            }

            /** @var PluginBatchExecutor $executor */
            $executor = Container::make(PluginBatchExecutor::class);
            foreach ($executor->streamUpdate($code, $version, $targets, ['force' => $force]) as $chunk) {
                $connection->send($chunk);
            }
        } catch (\Throwable $e) {
            $connection->send(Sse::error('升级失败：' . $e->getMessage()));
        }
    }

    #[OA\Get(
        path: '/plugin/tenant-auth/install',
        summary: '远程安装插件到指定租户(SSE流式)',
        tags: ['平台-租户授权'],
        parameters: [
            new OA\Parameter(name: 'name', description: '插件标识', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tenant_id', description: '目标租户ID', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'with_database', description: '1=菜单+数据库(默认), 0=仅菜单', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[Permission('platform:plugin:tenant-auth:install')]
    public function installStream(Request $request): void
    {
        $name = (string)$request->input('name', '');
        $tenantId = (string)$request->input('tenant_id', '');
        $withDatabase = (bool)$request->input('with_database', 1);

        $connection = $request->connection;
        $this->sendSseHeaders($connection);

        try {
            set_time_limit(0);

            if ($name === '' || $tenantId === '') {
                $connection->send($this->makeInstallEvent('error', 100, ['msg' => '插件标识和目标租户ID不能为空']));
                return;
            }
            if (!is_dir(base_path('plugin/' . $name))) {
                $connection->send($this->makeInstallEvent('error', 100, ['msg' => "平台未安装插件：{$name}"]));
                return;
            }

            $scopeLabel = $withDatabase ? '菜单+数据库' : '仅菜单';
            // 与本地安装控制器(PluginInstallController::sseEmit)保持一致的开始事件, 仅前端展示用
            $connection->send($this->makeInstallEvent('start', 0, ['msg' => "🚀 开始为租户 {$tenantId} 远程安装 {$name}（{$scopeLabel}）"]));

            /** @var TenantPluginService $svc */
            $svc = Container::make(TenantPluginService::class);
            foreach ($svc->install($name, $tenantId, $withDatabase) as $sseString) {
                $connection->send($this->translateInstallSse($sseString));
            }
        } catch (\Throwable $e) {
            $connection->send($this->makeInstallEvent('error', 100, ['msg' => '安装失败：' . $e->getMessage()]));
        }
    }

    /**
     * 构造与本地安装控制器(PluginInstallController::sseEmit)一致的自定义 SSE 事件
     * data 结构固定为 { event, pct, data: { msg } }, 供前端 InstallDrawer 的 EventSource 直接消费。
     */
    private function makeInstallEvent(string $event, int $pct, array $data): string
    {
        $payload = json_encode([
            'event' => $event,
            'pct'   => $pct,
            'data'  => $data,
        ], JSON_UNESCAPED_UNICODE);
        return "event: {$event}\ndata: {$payload}\n\n";
    }

    /**
     * 将 TenantPluginService::install 产出的 Sse::make 格式(progress/completed/error/warning)
     * 转换为前端 InstallDrawer 期望的自定义格式(start/progress/heartbeat/done/error + {event,pct,data:{msg}})。
     */
    private function translateInstallSse(string $sseString): string
    {
        $event = 'progress';
        $pct = 0;
        $msg = '';
        if (preg_match('/event:\s*(\w+)/', $sseString, $m)) {
            $event = $m[1];
        }
        if (preg_match('/data:\s*(\{.*\})/s', $sseString, $m)) {
            $decoded = json_decode($m[1], true) ?: [];
            $inner = $decoded['data'] ?? [];
            $msg = $inner['message'] ?? '';
            $pct = (int)($inner['progress'] ?? 0);
        }

        return match ($event) {
            'error'     => $this->makeInstallEvent('error', 100, ['msg' => $msg]),
            'completed' => $this->makeInstallEvent('done', 100, ['msg' => $msg]),
            'warning'   => $this->makeInstallEvent('progress', $pct, ['msg' => $msg]),
            'heartbeat' => $this->makeInstallEvent('heartbeat', $pct, ['msg' => $msg]),
            default     => $this->makeInstallEvent('progress', $pct, ['msg' => $msg]),
        };
    }

    /**
     * 解析 CSV 查询参数
     */
    private function csvParam(Request $request, string $key): array
    {
        $raw = (string)$request->input($key, '');
        if ($raw === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
