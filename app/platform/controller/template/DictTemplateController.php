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
use app\service\platform\template\DictTemplateService;
use core\foundation\tool\Json;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;

#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class DictTemplateController extends Base
{
    public function __construct(DictTemplateService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(path: '/template/dict', summary: '获取字典模板列表（分页，给CRUD组件使用）', tags: ['字典模板'])]
    #[SimpleResponse(schema: [], example: [])]
    public function index(Request $request): \support\Response
    {
        return parent::index($request);
    }

    #[OA\Get(path: '/template/dict/list', summary: '获取字典模板列表（扁平）', tags: ['字典模板'])]
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

    #[OA\Post(path: '/template/dict', summary: '创建字典模板', tags: ['字典模板'])]
    #[SimpleResponse(schema: [], example: [])]
    public function create(Request $request): \support\Response
    {
        try {
            $data = $request->all();
            $template = $this->service->create($data);
            return Json::success('创建成功', $template->toArray());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(path: '/template/dict/{id}', summary: '更新字典模板', tags: ['字典模板'])]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            $data = $request->all();
            $template = $this->service->update((int)$id, $data);
            return Json::success('更新成功', $template->toArray());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(path: '/template/dict/{id}', summary: '删除字典模板', tags: ['字典模板'])]
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

    #[OA\Get(path: '/template/dict/{id}', summary: '获取字典模板详情', tags: ['字典模板'])]
    #[OA\Parameter(name: 'id', description: '字典模板ID', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Post(path: '/template/dict/batch-delete', summary: '批量删除字典模板', tags: ['字典模板'])]
    public function batchDelete(Request $request): \support\Response
    {
        try {
            $ids = $request->input('ids', []);
            if (empty($ids)) {
                return Json::fail('请选择要删除的字典');
            }
            $this->service->batchDelete($ids);
            return Json::success('批量删除成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}
