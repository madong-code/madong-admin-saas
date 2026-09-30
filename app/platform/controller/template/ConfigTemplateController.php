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
use app\service\platform\template\ConfigTemplateService;
use core\foundation\tool\Json;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;

#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class ConfigTemplateController extends Base
{
    public function __construct(ConfigTemplateService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(path: '/template/config', summary: '获取配置模板列表', tags: ['配置模版'])]
    #[OA\Parameter(name: 'group_code', description: '配置分组编码', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    public function index(Request $request): \support\Response
    {
        return parent::index($request);
    }

    #[OA\Post(path: '/template/config', summary: '创建配置模板', tags: ['配置模版'])]
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

    #[OA\Put(path: '/template/config/{id}', summary: '更新配置模板', tags: ['配置模版'])]
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

    #[OA\Delete(path: '/template/config/{id}', summary: '删除配置模板', tags: ['配置模版'])]
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

    #[OA\Get(path: '/template/config/{id}', summary: '获取配置模板详情', tags: ['配置模版'])]
    #[OA\Parameter(name: 'id', description: '配置模板ID', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Post(path: '/template/config/batch-delete', summary: '批量删除配置模板', tags: ['配置模版'])]
    public function batchDelete(Request $request): \support\Response
    {
        try {
            $ids = $request->input('ids', []);
            if (empty($ids)) {
                return Json::fail('请选择要删除的配置');
            }
            $this->service->batchDelete($ids);
            return Json::success('批量删除成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(path: '/template/config/sync', summary: '快速同步配置模板到租户', tags: ['配置模版'])]
    public function sync(Request $request): \support\Response
    {
        try {
            // 触发所有启用的配置模板同步到 field 模式租户
            $templates = $this->service->getList();
            foreach ($templates as $template) {
                if (!empty($template['is_sync'])) {
                    $this->service->update((int)$template['id'], $template);
                }
            }
            return Json::success('同步成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}
