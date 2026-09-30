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

use app\adminapi\controller\Crud;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\adminapi\validate\plugin\PluginValidate;
use app\service\admin\plugin\PluginDevelopService;
use core\foundation\exception\handler\AdminException;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\DataResponse;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use support\Response;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;

#[OA\Tag(name: '插件开发', description: '应用管理-插件开发')]
#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class PluginDevelopController extends Crud
{
    public function __construct(PluginDevelopService $service, PluginValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    /**
     * 插件列表（扫描插件目录并同步到数据库）
     */
    #[OA\Get(
        path: '/plugin/develop',
        summary: '列表',
        tags: ['插件开发'],
        parameters: [
            new OA\Parameter(name: 'page', description: '页码', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limit', description: '每页数量', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'keyword', description: '搜索关键词', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'LIKE_title', description: '插件名称', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'LIKE_desc', description: '插件描述', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'EQ_status', description: '状态', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[Permission('plugin:develop:list')]
    #[PageResponse(example: '{"code": 0,"msg": "ok","data": {"list": [],"total": 0}}')]
    public function index(Request $request): \support\Response
    {
        try {
            // 解析查询参数
            [$where, , $limit, , , $page] = $this->selectInput($request);
            $result = $this->service->getList($where, $page, $limit);
            // 统一响应字段名: list → items
            if (isset($result['list'])) {
                $result['items'] = $result['list'];
                unset($result['list']);
            }
            return Json::success($result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 获取插件详情
     */
    #[OA\Get(
        path: '/plugin/develop/{id}',
        summary: '详情',
        tags: ['插件开发'],
        parameters: [
            new OA\Parameter(name: 'id', description: '插件ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[Permission('plugin:develop:read')]
    #[DataResponse(example: '{"code": 0,"msg": "ok","data": {}}')]
    public function show(Request $request): \support\Response
    {
        try {
            $id     = $request->route->param('id');
            $plugin = $this->service->show($id);
            if (!$plugin) {
                return Json::fail('插件不存在');
            }
            return Json::success($plugin->toArray());
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 创建插件
     */
    #[OA\Post(
        path: '/plugin/develop',
        summary: '创建',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'title', description: '插件名称', type: 'string'),
                        new OA\Property(property: 'key', description: '插件标识', type: 'string'),
                        new OA\Property(property: 'desc', description: '描述', type: 'string'),
                        new OA\Property(property: 'version', description: '版本号', type: 'string'),
                        new OA\Property(property: 'author', description: '作者', type: 'string'),
                        new OA\Property(property: 'type', description: '类型', type: 'string'),
                        new OA\Property(property: 'icon', description: '图标(base64)', type: 'string'),
                        new OA\Property(property: 'cover', description: '封面(base64)', type: 'string'),
                    ]
                )
            )
        ),
        tags: ['插件开发']
    )]
    #[Permission('plugin:develop:create')]
    #[SimpleResponse(example: '{"code": 0,"msg": "ok","data": []}')]
    public function store(Request $request): \support\Response
    {
        try {
            $data = $request->all();
            $this->validate->scene('store')->check($data);
            $result = $this->service->store($data);
            return Json::success('ok', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 编辑插件
     */
    #[OA\Put(
        path: '/plugin/develop/{id}',
        summary: '编辑',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'title', description: '插件名称', type: 'string'),
                        new OA\Property(property: 'desc', description: '描述', type: 'string'),
                        new OA\Property(property: 'version', description: '版本号', type: 'string'),
                        new OA\Property(property: 'author', description: '作者', type: 'string'),
                        new OA\Property(property: 'icon', description: '图标(base64)', type: 'string'),
                        new OA\Property(property: 'cover', description: '封面(base64)', type: 'string'),
                    ]
                )
            )
        ),
        tags: ['插件开发'],
        parameters: [
            new OA\Parameter(name: 'id', description: '插件ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[Permission('plugin:develop:update')]
    #[SimpleResponse(example: '{"code": 0,"msg": "更新成功"}')]
    public function update(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $request->all();
            $this->validate->scene('update')->check($data);
            $result = $this->service->update($id, $data);
            return Json::success('更新成功', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 删除插件
     */
    #[OA\Delete(
        path: '/plugin/develop/{id}',
        summary: '删除',
        tags: ['插件开发'],
        parameters: [
            new OA\Parameter(name: 'id', description: '插件ID（支持逗号分隔批量删除）', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[Permission('plugin:develop:delete')]
    #[SimpleResponse(example: '{"code": 0,"msg": "删除成功"}')]
    public function destroy(Request $request): Response
    {
        try {
            $ids = $this->getDeleteIds($request);
            if (empty($ids)) {
                throw new AdminException('删除参数不能为空');
            }
            $result = $this->service->transaction(function () use ($ids) {
                $ids        = is_array($ids) ? $ids : explode(',', $ids);
                $deletedIds = [];
                foreach ($ids as $id) {
                    $this->service->destroyPlugin($id);
                    $deletedIds[] = $id;
                }
                return $deletedIds;
            });
            return Json::success('删除成功', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/plugin/develop',
        summary: '批量删除',
        tags: ['插件开发'],
        parameters: [
            new OA\Parameter(name: 'id', description: '插件ID（支持逗号分隔批量删除）', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[Permission('plugin:develop:delete')]
    #[SimpleResponse(example: '{"code": 0,"msg": "删除成功"}')]
    public function batchDestroy(Request $request): Response
    {
        return $this->destroy($request);
    }

    /**
     * 打包插件
     */
    #[OA\Post(
        path: '/plugin/develop/{id}/build',
        summary: '打包',
        tags: ['插件开发'],
        parameters: [
            new OA\Parameter(name: 'id', description: '插件ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[Permission('plugin:develop:build')]
    #[SimpleResponse(example: '{"code": 0,"msg": "打包成功","data": {"file": ""}}')]
    public function build(Request $request): \support\Response
    {
        try {
            $id     = $request->route->param('id');
            $result = $this->service->buildPlugin($id);
            return Json::success('打包成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }
}
