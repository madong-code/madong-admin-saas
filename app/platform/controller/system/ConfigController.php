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
namespace app\platform\controller\system;

use app\platform\controller\Base;
use app\service\platform\system\ConfigService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;

#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class ConfigController extends Base
{
    public function __construct(ConfigService $service)
    {
        $this->service = $service;
    }

    #[OA\Put(
        path: '/system/config/{code}',
        summary: '保存平台配置（如站点设置）',
        tags: ['系统配置'],
    )]
    #[OA\Parameter(
        name: 'code', description: '配置编码', in: 'path', required: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        try {
            $data = $request->all();
            $code = $request->route->param('code');

            if (empty($data['content'])) {
                return Json::fail('配置内容不能为空');
            }

            $options = [
                'name'       => $data['name'] ?? $code,
                'group_code' => $data['group_code'] ?? 'platform',
                'enabled'    => $data['enabled'] ?? 1,
            ];
            $this->service->update($code, $data['content'], $options);

            return Json::success('保存成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/system/config/code/{code}',
        summary: '按 code 获取配置（网站设置、系统参数等）',
        tags: ['系统配置'],
    )]
    #[OA\Parameter(
        name: 'code', description: '配置编码', in: 'path', required: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[AllowAnonymous(requireToken: false, requirePermission: false)]
    #[SimpleResponse(schema: [], example: '{"site_open": "1","site_url": "http://127.0.0.1:8500","site_name": "madong-admin"}')]
    public function getByCode(Request $request, string $code): \support\Response
    {
        try {
            if (empty($code)) {
                return Json::fail('配置编码不能为空');
            }
            // 平台端强制默认 group_code = 'platform'，与 admin 端 'system' 隔离
            $groupCode = $request->input('group_code', 'platform');
            $options   = [];
            if (!empty($groupCode)) {
                $options['group_code'] = $groupCode;
            }
            $result = $this->service->config($code, [], $options);
            return Json::success('操作成功', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/system/config/group/{group_code}',
        summary: '按分组获取配置',
        tags: ['系统配置'],
    )]
    #[OA\Parameter(
        name: 'group_code', description: '分组编码', in: 'path', required: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[AllowAnonymous(requireToken: false, requirePermission: false)]
    #[SimpleResponse(schema: [], example: '{"site_open": "1","site_url": "http://127.0.0.1:8001"}')]
    public function getByGroup(Request $request, string $group_code): \support\Response
    {
        try {
            if (empty($group_code)) {
                return Json::fail('分组编码不能为空');
            }
            $options = [
                'enabled_only'  => $request->input('enabled_only', true),
                'with_metadata' => $request->input('with_metadata', false),
            ];
            $result = $this->service->getByGroup($group_code, [], $options);
            return Json::success('操作成功', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/system/config/items',
        summary: '获取配置项列表',
        tags: ['系统配置'],
    )]
    #[OA\Parameter(
        name: 'groupCode', description: '分组代码', in: 'query', required: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[SimpleResponse(schema: [], example: ['items'=>[],'total'=>0])]
    public function getItems(Request $request): \support\Response
    {
        try {
            $page      = (int) $request->input('page', 1);
            $pageSize  = (int) $request->input('pageSize', 20);
            $groupCode = $request->input('groupCode', '');
            $keyword   = $request->input('keyword', '');
            if (empty($groupCode)) {
                return Json::fail('分组代码不能为空');
            }
            $result = $this->service->getItems($page, $pageSize, $groupCode, $keyword);
            return Json::success('操作成功', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}
