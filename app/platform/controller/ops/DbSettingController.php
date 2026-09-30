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

use app\dao\tenant\DbSettingDao;
use app\platform\controller\Base;
use app\platform\validate\ops\db\DbSettingValidate;
use app\service\platform\system\DbSettingService;
use core\infrastructure\cache\CacheService;
use core\foundation\exception\handler\DbSettingException;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use mysql_xdevapi\Exception;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;

#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class DbSettingController extends Base
{

    public function __construct(DbSettingService $service, DbSettingValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    /**
     * 重写父类 insertInput，跳过 password 哈希
     * 数据库连接密码需要明文保存以便 PDO 连接，不能像用户密码那样哈希
     */
    protected function insertInput(Request $request): array
    {
        return $this->inputFilter($request->all());
    }

    #[OA\Get(
        path: '/db-settings',
        summary: '获取数据源列表',
        tags: ['数据源管理'],
        parameters: [
            new OA\Parameter(name: 'page', description: '页码', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', description: '每页数量', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'name', description: '数据源名称', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'driver', description: '数据库驱动', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'enabled', description: '是否启用', in: 'query', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
        ]
    )]
    #[PageResponse]
    #[Permission(code: 'platform:database:setting:list')]
    public function index(Request $request): \support\Response
    {
        try {
            [$where, $format, $limit, $field, $order, $page] = $this->selectInput($request);
            $format_function = ['select' => 'formatSelect', 'tree' => 'formatTree', 'table_tree' => 'formatTableTree', 'normal' => 'formatNormal'][$format] ?? 'formatNormal';
            $total           = $this->service->getCount($where);
            $list            = $this->service->selectList($where, $field, $page, $limit, $order, [], false);
            return call_user_func([$this, $format_function], $list, $total);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/db-drivers',
        summary: '获取支持的数据库驱动列表',
        tags: ['数据源管理']
    )]
    #[SimpleResponse]
    public function drivers(Request $request): \support\Response
    {
        try {
            return Json::success('ok', $this->service->getDriverList());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/db-settings/{id}',
        summary: '获取数据源详情',
        tags: ['数据源管理'],
        parameters: [
            new OA\Parameter(name: 'id', description: '数据源ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[SimpleResponse]
    #[Permission(code: 'platform:database:setting:view')]
    public function show(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            if (empty($id)) {
                throw new DbSettingException('数据源ID不能为空');
            }
            $result = $this->service->get($id);
            if (empty($result)) {
                throw new DbSettingException('数据源不存在');
            }
            // 编辑详情时清空密码，避免前端预填导致回传原密码覆盖
            $data               = $result->toArray();
            $data['password']   = '';
            return Json::success('ok', $data);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: 400);
        }
    }

    #[OA\Post(
        path: '/db-settings',
        summary: '创建数据源',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'driver', 'host', 'port', 'database', 'username'],
                properties: [
                    new OA\Property(property: 'name', description: '数据源名称', type: 'string'),
                    new OA\Property(property: 'driver', description: '数据库驱动', type: 'string', enum: ['mysql', 'pgsql', 'sqlite', 'sqlsrv']),
                    new OA\Property(property: 'host', description: '数据库主机', type: 'string'),
                    new OA\Property(property: 'port', description: '数据库端口', type: 'integer'),
                    new OA\Property(property: 'database', description: '数据库名称', type: 'string'),
                    new OA\Property(property: 'username', description: '数据库用户名', type: 'string'),
                    new OA\Property(property: 'password', description: '数据库密码', type: 'string'),
                    new OA\Property(property: 'prefix', description: '表前缀', type: 'string'),
                    new OA\Property(property: 'charset', description: '字符集', type: 'string'),
                    new OA\Property(property: 'enabled', description: '是否启用', type: 'integer', enum: [0, 1]),
                    new OA\Property(property: 'is_existing', description: '是否使用现有数据库 0-新建(默认) 1-使用现有', type: 'integer', enum: [0, 1], default: 0),
                    new OA\Property(property: 'description', description: '描述', type: 'string'),
                ]
            )
        ),
        tags: ['数据源管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[Permission(code: 'platform:database:setting:create')]
    public function store(Request $request): \support\Response
    {
        try {
            $data = $this->insertInput($request);
            $this->validate->scene('create')->check($data);

            $model = $this->service->save($data);
            return Json::success('创建成功', ['id' => $model->id]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/db-settings/{id}',
        summary: '更新数据源',
        tags: ['数据源管理'],
        parameters: [
            new OA\Parameter(name: 'id', description: '数据源ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', description: '数据源名称', type: 'string'),
                    new OA\Property(property: 'driver', description: '数据库驱动', type: 'string', enum: ['mysql', 'pgsql', 'sqlite', 'sqlsrv']),
                    new OA\Property(property: 'host', description: '数据库主机', type: 'string'),
                    new OA\Property(property: 'port', description: '数据库端口', type: 'integer'),
                    new OA\Property(property: 'database', description: '数据库名称', type: 'string'),
                    new OA\Property(property: 'username', description: '数据库用户名', type: 'string'),
                    new OA\Property(property: 'password', description: '数据库密码', type: 'string'),
                    new OA\Property(property: 'prefix', description: '表前缀', type: 'string'),
                    new OA\Property(property: 'charset', description: '字符集', type: 'string'),
                    new OA\Property(property: 'enabled', description: '是否启用', type: 'integer', enum: [0, 1]),
                    new OA\Property(property: 'description', description: '描述', type: 'string'),
                ]
            )
        )
    )]
    #[SimpleResponse]
    #[Permission(code: 'platform:database:setting:update')]
    public function update(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            if (empty($id)) {
                throw new DbSettingException('数据源ID不能为空');
            }

            $data       = $this->insertInput($request);
            $data['id'] = $id;

            // 编辑时密码为空则不修改原密码
            if (empty($data['password'])) {
                unset($data['password']);
            }

            $this->validate->scene('update')->check($data);

            $this->service->update($id, $data);
            return Json::success('更新成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/db-settings/{id}',
        summary: '删除数据源',
        tags: ['数据源管理'],
        parameters: [
            new OA\Parameter(name: 'id', description: '数据源ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[SimpleResponse]
    #[Permission(code: 'platform:database:setting:delete')]
    public function destroy(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            if (empty($id)) {
                throw new DbSettingException('数据源ID不能为空');
            }

            $this->service->delete($id);
            return Json::success('删除成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/db-settings/{id}/toggle',
        summary: '切换数据源启用状态',
        tags: ['数据源管理'],
        parameters: [
            new OA\Parameter(name: 'id', description: '数据源ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[SimpleResponse]
    public function toggle(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            if (empty($id)) {
                throw new DbSettingException('数据源ID不能为空');
            }

            $this->service->toggleEnabled($id);
            return Json::success('操作成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/db-settings/test',
        summary: '测试连接（不保存，直接传入连接参数测试）',
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
                    new OA\Property(property: 'is_existing', description: '是否使用现有数据库 0-新建(默认) 1-使用现有', type: 'integer', enum: [0, 1], default: 0),
                ]
            )
        ),
        tags: ['数据源管理']
    )]
    #[SimpleResponse]
    #[Permission(code: 'platform:database:setting:test')]
    public function test(Request $request): \support\Response
    {
        try {
            // 支持两种模式：
            // 1. POST /db-settings/test   — 直接传入连接参数
            // 2. GET  /db-settings/{id}/test — 基于已有记录

            // 模式1：POST 传入连接参数
            $data = $request->all();
            if (!empty($data['driver']) && !empty($data['host'])) {
                $data['port'] = (int)($data['port'] ?? 3306);
                // 使用现有库模式：测试含 dbname，确保库已存在且可访问
                $isExisting = !empty($data['is_existing']);
                $result = $isExisting
                    ? $this->service->testConnectionWithDatabase($data)
                    : $this->service->testConnection($data);
                return Json::success($result['message'], $result);
            }

            return Json::fail('缺少连接参数');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/db-settings/{id}/test',
        summary: '测试数据源连接（基于已有记录）',
        tags: ['数据源管理'],
        parameters: [
            new OA\Parameter(name: 'id', description: '数据源ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[SimpleResponse]
    #[Permission(code: 'platform:database:setting:test')]
    public function testById(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            if (empty($id)) {
                throw new DbSettingException('数据源ID不能为空');
            }

            $data = $this->service->get($id);
            if (empty($data)) {
                throw new DbSettingException('数据源不存在');
            }

            $testData = [
                'driver'   => $data['driver'],
                'host'     => $data['host'],
                'port'     => (int)$data['port'],
                'database' => $data['database'],
                'username' => $data['username'],
                'password' => $data['password'],
                'prefix'   => $data['prefix'] ?? '',
            ];

            $result = $this->service->testConnection($testData);
            return Json::success('ok', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

}
