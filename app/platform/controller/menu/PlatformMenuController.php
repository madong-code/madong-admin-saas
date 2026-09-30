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
namespace app\platform\controller\menu;

use app\platform\controller\Base;
use app\service\platform\menu\PlatformMenuService;
use core\foundation\tool\Json;
use madong\swagger\attribute\AllowAnonymous;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;

#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class PlatformMenuController extends Base
{
    public function __construct(PlatformMenuService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/platform-menu/tree',
        summary: '获取平台菜单树',
        tags: ['平台菜单'],
    )]
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

    #[OA\Get(
        path: '/platform-menu',
        summary: '获取平台菜单列表（扁平，给CRUD组件使用）',
        tags: ['平台菜单'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function index(Request $request): \support\Response
    {
        return $this->list($request);
    }

    #[OA\Get(
        path: '/platform-menu/list',
        summary: '获取平台菜单列表（扁平）',
        tags: ['平台菜单'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function list(Request $request): \support\Response
    {
        try {
            $data = $this->service->getList();
            return Json::success('ok', $data);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/platform-menu',
        summary: '创建平台菜单',
        tags: ['平台菜单'],
    )]
    #[SimpleResponse(schema: [], example: [])]
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

    #[OA\Put(
        path: '/platform-menu/{id}',
        summary: '更新平台菜单',
        tags: ['平台菜单'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $request->all();
            $menu = $this->service->update((int)$id, $data);
            return Json::success('更新成功', $menu->toArray());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/platform-menu/{id}',
        summary: '删除平台菜单',
        tags: ['平台菜单'],
    )]
    #[SimpleResponse(schema: [], example: [])]
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
}
