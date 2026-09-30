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
namespace app\platform\controller\template;
use app\platform\controller\Base;
use app\service\platform\template\MenuTemplateService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;
#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class MenuTemplateController extends Base
{
    public function __construct(MenuTemplateService $service)
    {
        $this->service = $service;
    }
    #[OA\Get(path: '/template/menu/tree', summary: '获取菜单模板树', tags: ['菜单模板'])]
    #[OA\Parameter(name: 'app', description: '菜单归属：admin=租户模板(默认)，platform=平台菜单', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'admin'))]
    #[SimpleResponse(schema: [], example: [])]
    public function tree(Request $request): \support\Response
    {
        try {
            $app = $request->input('app', 'admin');
            $data = $this->service->getTree($app);
            return Json::success('ok', $data);
        } catch (\Throwable $e) { return Json::fail($e->getMessage()); }
    }
    #[OA\Get(path: '/template/menu', summary: '获取菜单模板列表（扁平，给CRUD组件使用）', tags: ['菜单模板'])]
    #[OA\Parameter(name: 'app', description: '菜单归属：admin=租户模板(默认)，platform=平台菜单', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'admin'))]
    #[SimpleResponse(schema: [], example: [])]
    public function index(Request $request): \support\Response { return $this->list($request); }
    #[OA\Get(path: '/template/menu/list', summary: '获取菜单模板列表（扁平）', tags: ['菜单模板'])]
    #[OA\Parameter(name: 'app', description: '菜单归属：admin=租户模板(默认)，platform=平台菜单', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'admin'))]
    #[SimpleResponse(schema: [], example: [])]
    public function list(Request $request): \support\Response
    {
        try {
            $app = $request->input('app', 'admin');
            $data = $this->service->getList($app);
            return Json::success('ok', $data);
        } catch (\Throwable $e) { return Json::fail($e->getMessage()); }
    }
    #[OA\Post(path: '/template/menu', summary: '创建菜单模板', tags: ['菜单模板'])]
    #[SimpleResponse(schema: [], example: [])]
    public function create(Request $request): \support\Response
    {
        try {
            $data = $request->all();
            $menu = $this->service->create($data);
            return Json::success('创建成功', $menu->toArray());
        } catch (\Throwable $e) { return Json::fail($e->getMessage()); }
    }
    #[OA\Put(path: '/template/menu/{id}', summary: '更新菜单模板', tags: ['菜单模板'])]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            $data = $request->all();
            $menu = $this->service->update((int)$id, $data);
            return Json::success('更新成功', $menu->toArray());
        } catch (\Throwable $e) { return Json::fail($e->getMessage()); }
    }
    #[OA\Delete(path: '/template/menu/{id}', summary: '删除菜单模板', tags: ['菜单模板'])]
    #[SimpleResponse(schema: [], example: [])]
    public function delete(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            $this->service->delete((int)$id);
            return Json::success('删除成功');
        } catch (\Throwable $e) { return Json::fail($e->getMessage()); }
    }

    #[OA\Get(path: '/template/menu/{id}', summary: '获取菜单模板详情', tags: ['菜单模板'])]
    #[OA\Parameter(name: 'id', description: '菜单模板ID', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[SimpleResponse(schema: [], example: [])]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Post(path: '/template/menu/batch-delete', summary: '批量删除菜单模板', tags: ['菜单模板'])]
    #[SimpleResponse(schema: [], example: [])]
    public function batchDelete(Request $request): \support\Response
    {
        try {
            $ids = $request->input('ids', []);
            if (empty($ids)) {
                return Json::fail('请选择要删除的菜单');
            }
            $this->service->batchDelete($ids);
            return Json::success('批量删除成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/template/menu/batch-store',
        summary: '批量添加菜单（从接口选择器）',
        tags: ['菜单模板'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function batchStore(Request $request): \support\Response
    {
        try {
            $params = $request->input('menus', []);
            foreach ($params as $item) {
                $this->service->create($item);
            }
            return Json::success('ok');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}
