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
namespace app\platform\controller\ops;

use app\platform\controller\Base;
use app\service\platform\system\DbSettingConfigService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;

#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class DbSettingConfigController extends Base
{
    public function __construct(DbSettingConfigService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/db-configs/options',
        summary: '获取数据源配置选项',
        tags: ['数据源配置']
    )]
    #[SimpleResponse]
    public function options(Request $request): \support\Response
    {
        try {
            return Json::success('ok', $this->service->getConfigOptions());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/db-configs/{dataSourceId}/tables',
        summary: '获取数据源下的表列表',
        tags: ['数据源配置'],
        parameters: [
            new OA\Parameter(name: 'dataSourceId', description: '数据源ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[SimpleResponse]
    public function tables(Request $request): \support\Response
    {
        try {
            $dataSourceId = (int)$request->route->param('dataSourceId');
            if ($dataSourceId <= 0) {
                return Json::fail('数据源ID不能为空', [], 400);
            }
            return Json::success('ok', $this->service->getTables($dataSourceId));
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/db-configs/{dataSourceId}/tables/{tableName}',
        summary: '获取表结构信息',
        tags: ['数据源配置'],
        parameters: [
            new OA\Parameter(name: 'dataSourceId', description: '数据源ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tableName', description: '表名', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[SimpleResponse]
    public function tableInfo(Request $request): \support\Response
    {
        try {
            $dataSourceId = (int)$request->route->param('dataSourceId');
            $tableName = $request->route->param('tableName');
            
            if ($dataSourceId <= 0 || empty($tableName)) {
                return Json::fail('参数错误', [], 400);
            }
            
            return Json::success('ok', $this->service->getTableInfo($dataSourceId, $tableName));
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/db-configs/bind',
        summary: '绑定数据源到租户',
        tags: ['数据源配置'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['dataSourceId', 'tenantId'],
                properties: [
                    new OA\Property(property: 'dataSourceId', description: '数据源ID', type: 'integer'),
                    new OA\Property(property: 'tenantId', description: '租户ID', type: 'integer'),
                ]
            )
        )
    )]
    #[SimpleResponse]
    public function bind(Request $request): \support\Response
    {
        try {
            $dataSourceId = (int)$request->post('dataSourceId', 0);
            $tenantId = (int)$request->post('tenantId', 0);
            
            if ($dataSourceId <= 0 || $tenantId <= 0) {
                return Json::fail('参数错误', [], 400);
            }
            
            $this->service->bindToTenant($dataSourceId, $tenantId);
            return Json::success('绑定成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/db-configs/batch-bind',
        summary: '批量绑定数据源到租户',
        tags: ['数据源配置'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['dataSourceIds', 'tenantId'],
                properties: [
                    new OA\Property(property: 'dataSourceIds', description: '数据源ID列表', type: 'array', items: new OA\Items(type: 'integer')),
                    new OA\Property(property: 'tenantId', description: '租户ID', type: 'integer'),
                ]
            )
        )
    )]
    #[SimpleResponse]
    public function batchBind(Request $request): \support\Response
    {
        try {
            $dataSourceIds = $request->post('dataSourceIds', []);
            $tenantId = (int)$request->post('tenantId', 0);
            
            if (empty($dataSourceIds) || $tenantId <= 0) {
                return Json::fail('参数错误', [], 400);
            }
            
            $this->service->batchBindToTenant($dataSourceIds, $tenantId);
            return Json::success('批量绑定成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/db-configs/{configId}',
        summary: '解绑数据源',
        tags: ['数据源配置'],
        parameters: [
            new OA\Parameter(name: 'configId', description: '配置ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[SimpleResponse]
    public function unbind(Request $request): \support\Response
    {
        try {
            $configId = (int)$request->route->param('configId');
            
            if ($configId <= 0) {
                return Json::fail('配置ID不能为空', [], 400);
            }
            
            $this->service->unbind($configId);
            return Json::success('解绑成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/db-configs/stats',
        summary: '获取绑定统计',
        tags: ['数据源配置']
    )]
    #[SimpleResponse]
    public function stats(Request $request): \support\Response
    {
        try {
            return Json::success('ok', $this->service->getStats());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/db-configs/test',
        summary: '测试数据源连接',
        tags: ['数据源配置'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['driver', 'host', 'port', 'database', 'username'],
                properties: [
                    new OA\Property(property: 'driver', description: '数据库驱动', type: 'string', enum: ['mysql', 'pgsql', 'sqlite', 'sqlsrv']),
                    new OA\Property(property: 'host', description: '数据库主机', type: 'string'),
                    new OA\Property(property: 'port', description: '数据库端口', type: 'integer'),
                    new OA\Property(property: 'database', description: '数据库名称', type: 'string'),
                    new OA\Property(property: 'username', description: '数据库用户名', type: 'string'),
                    new OA\Property(property: 'password', description: '数据库密码', type: 'string'),
                    new OA\Property(property: 'prefix', description: '表前缀', type: 'string'),
                ]
            )
        )
    )]
    #[SimpleResponse]
    public function testDataSource(Request $request): \support\Response
    {
        try {
            $data = [
                'driver' => $request->post('driver', 'mysql'),
                'host' => $request->post('host', '127.0.0.1'),
                'port' => (int)$request->post('port', 3306),
                'database' => $request->post('database', ''),
                'username' => $request->post('username', ''),
                'password' => $request->post('password', ''),
                'prefix' => $request->post('prefix', ''),
            ];
            
            return Json::success('ok', $this->service->testConnection($data));
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/db-configs/{dataSourceId}/sync',
        summary: '同步表结构',
        tags: ['数据源配置'],
        parameters: [
            new OA\Parameter(name: 'dataSourceId', description: '数据源ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tableName'],
                properties: [
                    new OA\Property(property: 'tableName', description: '表名', type: 'string'),
                ]
            )
        )
    )]
    #[SimpleResponse]
    public function syncStructure(Request $request): \support\Response
    {
        try {
            $dataSourceId = (int)$request->route->param('dataSourceId');
            $tableName = $request->post('tableName', '');
            
            if ($dataSourceId <= 0 || empty($tableName)) {
                return Json::fail('参数错误', [], 400);
            }
            
            $this->service->syncStructure($dataSourceId, $tableName);
            return Json::success('同步成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}
