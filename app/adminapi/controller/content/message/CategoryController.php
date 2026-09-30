<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息分类控制器
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\adminapi\controller\content\message;

use app\adminapi\controller\Crud;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\adminapi\validate\content\message\category\CategoryValidate;
use app\service\admin\content\message\CategoryService;
use core\foundation\exception\handler\AdminException;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\DataResponse;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class CategoryController extends Crud
{
    public function __construct(CategoryService $service, CategoryValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/content/message/category',
        summary: '消息分类列表',
        tags: ['消息分类'],
    )]
    #[Permission(code: 'message:category:list')]
    #[PageResponse(example: [])]
    public function index(Request $request): \support\Response
    {
        return parent::index($request);
    }

    #[OA\Get(
        path: '/content/message/category/{id}',
        summary: '消息分类详情',
        tags: ['消息分类'],
    )]
    #[Permission(code: 'message:category:read')]
    #[DataResponse(example: [])]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Post(
        path: '/content/message/category',
        summary: '创建消息分类',
        tags: ['消息分类'],
    )]
    #[Permission(code: 'message:category:create')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function store(Request $request): \support\Response
    {
        try {
            $data = $this->insertInput($request);
            if (isset($this->validate) && $this->validate) {
                if (!$this->validate->scene('store')->check($data)) {
                    throw new \Exception($this->validate->getError());
                }
            }
            $result = $this->service->createCategory($data);
            return Json::success('ok', ['id' => $result['id'] ?? 0]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/content/message/category/{id}',
        summary: '更新消息分类',
        tags: ['消息分类'],
    )]
    #[OA\Parameter(
        name: 'id',
        description: '分类ID（雪花ID）',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: '123456789012345678')
    )]
    #[Permission(code: 'message:category:update')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function update(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $this->insertInput($request);
            // 路由模式兼容：如果没有 id 参数，尝试从请求体中获取
            if (empty($id)) {
                $model      = $this->service->getModel();
                $primaryKey = $model->getKeyName();
                if (!array_key_exists($primaryKey, $data)) {
                    throw new \Exception('参数异常缺少参数:' . $primaryKey);
                }
                $id = $data[$primaryKey];
            }
            if (isset($this->validate) && $this->validate) {
                if (!$this->validate->scene('update')->check(array_merge($data, ['id' => $id]))) {
                    throw new \Exception($this->validate->getError());
                }
            }
            $this->service->updateCategory($id, $data);
            return Json::success('ok', []);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/content/message/category/{id}',
        summary: '删除消息分类',
        tags: ['消息分类'],
    )]
    #[Permission(code: 'message:category:delete')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function destroy(Request $request): \support\Response
    {
        try {
            $data = $this->getDeleteIds($request);
            if (empty($data)) {
                throw new AdminException('删除参数不能为空');
            }
            foreach ($data as $id) {
                $this->service->deleteCategory($id);
            }
            return Json::success('ok', []);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/content/message/category',
        summary: '批量删除消息分类',
        tags: ['消息分类'],
    )]
    #[Permission(code: 'message:category:delete')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success'])]
    public function batchDelete(Request $request): \support\Response
    {
        return $this->destroy($request);
    }

    // ==== 兼容接口 ====

    /** 获取全部分类（不分页，给 notify 侧边栏等使用） */
    #[OA\Get(
        path: '/content/message/category/all',
        summary: '全部分类列表(不分页)',
        tags: ['消息分类'],
    )]
    #[Permission(code: 'message:category:list')]
    #[SimpleResponse(schema: [], example: [])]
    public function all(Request $request): \support\Response
    {
        return Json::success($this->service->getAllCategories());
    }

    #[OA\Get(
        path: '/content/message/category/{id}/definitions',
        summary: '分类下消息定义(按ID)',
        tags: ['消息分类'],
    )]
    #[Permission(code: 'message:category:definitions')]
    #[SimpleResponse(schema: [], example: [])]
    public function definitions(Request $request): \support\Response
    {
        $id       = $request->route->param('id');
        $service  = $this->service;
        return Json::success($service->getDefinitionsByCategory($id));
    }

    #[OA\Get(
        path: '/content/message/category/{id}/modules',
        summary: '分类下模块(兼容旧路由)',
        tags: ['消息分类'],
    )]
    #[Permission(code: 'message:category:modules')]
    #[SimpleResponse(schema: [], example: [])]
    public function modules(Request $request): \support\Response
    {
        $id       = $request->route->param('id');
        $service  = $this->service;
        return Json::success($service->getDefinitionsByCategory($id));
    }
}
