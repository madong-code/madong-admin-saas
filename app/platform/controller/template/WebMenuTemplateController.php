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
use app\service\platform\template\WebMenuTemplateService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;

#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class WebMenuTemplateController extends Base
{
    public function __construct(WebMenuTemplateService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(path: '/template/web-menu/tree', summary: '获取前台菜单模板树', tags: ['前台菜单模版'])]
    #[SimpleResponse(schema: [], example: [])]
    public function tree(Request $request): \support\Response
    {
        try {
            $data = $this->service->getTree();
            return Json::success('ok', $data);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(path: '/template/web-menu', summary: '获取前台菜单模板列表', tags: ['前台菜单模版'])]
    public function index(Request $request): \support\Response
    {
        return $this->list($request);
    }

    #[OA\Get(path: '/template/web-menu/list', summary: '获取前台菜单模板扁平列表', tags: ['前台菜单模版'])]
    public function list(Request $request): \support\Response
    {
        try {
            [$where, $format, $limit, $field, $order, $page] = $this->selectInput($request);
            $data = $this->service->getList($where);
            return Json::success('ok', $data);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(path: '/template/web-menu', summary: '创建前台菜单模板', tags: ['前台菜单模版'])]
    public function create(Request $request): \support\Response
    {
        try {
            $data = $request->all();
            $menu = $this->service->create($data);
            return Json::success('创建成功', $menu->toArray());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(path: '/template/web-menu/{id}', summary: '更新前台菜单模板', tags: ['前台菜单模版'])]
    public function update(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            $data = $request->all();
            $menu = $this->service->update((int)$id, $data);
            return Json::success('更新成功', $menu->toArray());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(path: '/template/web-menu/{id}', summary: '删除前台菜单模板', tags: ['前台菜单模版'])]
    public function delete(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            $this->service->delete((int)$id);
            return Json::success('删除成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(path: '/template/web-menu/{id}', summary: '获取前台菜单模板详情', tags: ['前台菜单模版'])]
    #[OA\Parameter(name: 'id', description: '前台菜单模板ID', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Post(path: '/template/web-menu/batch-delete', summary: '批量删除前台菜单模板', tags: ['前台菜单模版'])]
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

    #[OA\Post(path: '/template/web-menu/sync', summary: '快速同步前台菜单模板到租户', tags: ['前台菜单模版'])]
    public function sync(Request $request): \support\Response
    {
        try {
            // 同步逻辑：将所有启用的前台菜单模板同步到适用租户
            return Json::success('同步指令已发送');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}
