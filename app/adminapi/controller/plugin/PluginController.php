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
use app\service\core\plugin\PluginDownloadService;
use app\service\core\plugin\PluginInstallService;
use app\service\core\plugin\PluginRemoteService;
use app\service\core\plugin\PluginUninstallService;
use app\service\core\plugin\PluginService;
use core\business\plugin\traits\SseStreamTrait;
use core\foundation\tool\Sse;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use core\foundation\tool\Json;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Container;
use support\Request;
use support\Response;

#[OA\Tag(name: '模块市场', description: '应用管理-模块市场')]
#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class PluginController extends Base
{
    use SseStreamTrait;
    private PluginService $pluginService;
    private PluginRemoteService $remoteService;
    private PluginDownloadService $downloadService;

    public function __construct()
    {
        $this->pluginService  = Container::make(PluginService::class);
        $this->remoteService  = Container::make(PluginRemoteService::class);
        $this->downloadService = Container::make(PluginDownloadService::class);
    }

    #[OA\Get(
        path: '/plugin',
        summary: '列表',
        tags: ['模块市场'],
        parameters: [
            new OA\Parameter(name: 'page', description: '页码', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limit', description: '每页数量', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type', description: '分类(installed/un_installed/purchased/updatable/all)', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'keyword', description: '搜索关键词', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('plugin:market:list')]
    #[PageResponse(example: '{"code": 0,"msg": "ok","data": {"items": [],"total": 0,"page": 1,"limit": 50}}')]
    public function index(Request $request): Response
    {
        $page   = (int) $request->input('page', 1);
        $limit  = (int) $request->input('limit', 20);
        $type   = $request->input('type', 'all');
        $keyword = $request->input('keyword', '');
        $result = $this->pluginService->getList($type, null, $keyword, $page, $limit);
        return Json::success('ok', $result);
    }

    #[OA\Get(
        path: '/plugin/select',
        summary: '下拉选择列表',
        tags: ['模块市场'],
        parameters: [
            new OA\Parameter(name: 'keyword', description: '搜索', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('plugin:market:list')]
    #[SimpleResponse(example: '{"code": 0,"msg": "ok","data": []}')]
    public function select(Request $request): Response
    {
        $keyword = $request->input('keyword', '');
        $result = $this->pluginService->getSelectList($keyword);
        return Json::success('ok', $result);
    }

    #[OA\Get(
        path: '/plugin/{key}',
        summary: '详情',
        tags: ['模块市场'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('plugin:market:detail')]
    #[SimpleResponse(example: '{"code": 0,"msg": "ok","data": {}}')]
    public function detail(Request $request): Response
    {
        $key = $request->route->param('key');
        $result = $this->pluginService->getDetail($key);
        return Json::success('ok', $result);
    }

    #[OA\Get(
        path: '/plugin/{key}/download',
        summary: '获取下载链接',
        tags: ['模块市场'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('plugin:market:download')]
    #[SimpleResponse(example: '{"code": 0,"msg": "ok","data": {"download_url": ""}}')]
    public function download(Request $request): Response
    {
        $key = $request->route->param('key');
        $result = $this->downloadService->getDownloadInfo($key);
        return Json::success('ok', $result);
    }

    #[OA\Get(
        path: '/plugin/{key}/upgrade-logs',
        summary: '升级日志',
        tags: ['模块市场'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('plugin:market:upgrade-logs')]
    #[SimpleResponse(example: '{"code": 0,"msg": "ok","data": []}')]
    public function upgradeLogs(Request $request): Response
    {
        $key = $request->route->param('key');
        $result = $this->pluginService->getUpgradeLogs($key);
        return Json::success('ok', $result);
    }

    #[OA\Get(
        path: '/plugin/check-environment',
        summary: '环境检测',
        tags: ['模块市场'],
        parameters: [
            new OA\Parameter(name: 'code', description: '插件标识', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'name', description: '插件名称', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('plugin:market:install')]
    #[SimpleResponse(example: '{"code": 0,"msg": "ok","data": {"paths": []}}')]
    public function checkEnvironment(Request $request): Response
    {
        $code = $request->input('code');
        $result = $this->pluginService->checkEnvironment($code);
        return Json::success('ok', $result);
    }

    #[OA\Get(
        path: '/plugin/{key}/install',
        summary: '安装(SSE流式)',
        tags: ['模块市场'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'source', description: '安装源(remote/local)', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('plugin:market:install')]
    public function install(Request $request): void
    {
        $key    = $request->route->param('key');
        $source = $request->input('source', 'remote');

        $connection = $request->connection;

        // 发送 SSE 响应头
        $this->sendSseHeaders($connection);

        try {
            // 延长执行时间，防止长时间安装被中断
            set_time_limit(0);

            $connection->send(Sse::progress('开始安装插件「' . $key . '」', 0));

            $installService = Container::make(PluginInstallService::class);
            $generator      = $installService->install($key, $source);
            foreach ($generator as $chunk) {
                $connection->send($chunk);
            }
        } catch (\Throwable $e) {
            $connection->send(Sse::error('安装失败：' . $e->getMessage()));
        }
    }

    #[OA\Get(
        path: '/plugin/{key}/uninstall',
        summary: '卸载(SSE流式)',
        tags: ['模块市场'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('plugin:market:uninstall')]
    public function uninstall(Request $request): void
    {
        $key = $request->route->param('key');

        $connection = $request->connection;

        // 发送 SSE 响应头
        $this->sendSseHeaders($connection);

        try {
            set_time_limit(0);
            $uninstallService = Container::make(PluginUninstallService::class);
            $generator        = $uninstallService->uninstall($key);
            foreach ($generator as $chunk) {
                $connection->send($chunk);
            }
        } catch (\Throwable $e) {
            $connection->send(Sse::error('卸载失败：' . $e->getMessage()));
        }
    }

    #[OA\Delete(
        path: '/plugin/{key}',
        summary: '删除插件',
        tags: ['模块市场'],
        parameters: [
            new OA\Parameter(name: 'key', description: '插件标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('plugin:market:destroy')]
    #[SimpleResponse(example: '{"code": 0,"msg": "删除成功"}')]
    public function destroy(Request $request): Response
    {
        try {
            $key = $request->route->param('key');
            $uninstallService = Container::make(PluginUninstallService::class);
            $uninstallService->delete($key);
            return Json::success('删除成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}
