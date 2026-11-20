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
use app\platform\middleware\AccessTokenMiddleware;
use app\service\admin\system\dict\DictService;
use app\service\core\enum\EnumService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Container;
use support\Request;

#[Middleware(AccessTokenMiddleware::class)]
final class DictController extends Base
{
    public function __construct(DictService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/system/dict',
        summary: '列表',
        tags: ['字典管理'],
    )]
    #[PageResponse(schema: [], example: [])]
    public function index(Request $request): \support\Response
    {
        return parent::index($request);
    }

    #[OA\Post(
        path: '/system/dict',
        summary: '新增',
        tags: ['字典管理'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function store(Request $request): \support\Response
    {
        return parent::store($request);
    }

    #[OA\Put(
        path: '/system/dict/{id}',
        summary: '更新',
        tags: ['字典管理'],
    )]
    #[OA\Parameter(
        name: 'id',
        description: '字典ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', default: 0),
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        return parent::update($request);
    }

    #[OA\Delete(
        path: '/system/dict/{id}',
        summary: '删除',
        tags: ['字典管理'],
    )]
    #[OA\Parameter(
        name: 'id',
        description: '字典ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Delete(
        path: '/system/dict',
        summary: '批量删除',
        tags: ['字典管理'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function batchDelete(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Get(
        path: '/system/dict/info',
        summary: '详情',
        tags: ['字典管理'],
    )]
    #[OA\Parameter(
        name: 'id',
        description: '字典ID',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Get(
        path: '/system/dict/options/by-type',
        summary: '根据字典类型获取字典项',
        tags: ['字典管理'],
    )]
    #[OA\Parameter(
        name: 'dict_type',
        description: '字典类型',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string', default: 0),
    )]
    #[AllowAnonymous(requireToken: false, requirePermission: false)]
    #[SimpleResponse(example: '{"code": 0,"msg": "ok","data": [{"label": "是","value": 1,"color": "#4CAF50","ext": []},{"label": "否","value": 0,"color": "#FF5252","ext": []}]}')]
    public function getByDictType(Request $request): \support\Response
    {
        try {
            $dictType = $request->input('dict_type');
            $service  = Container::make(EnumService::class);
            $data     = $service->getEnumByCode($dictType);
            if (empty($data)) {
                $data = $this->service->findItemsByCode($dictType);
            }
            return Json::success('ok', $data);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/system/dict/enum-dict-list',
        summary: '枚举字典列表（分页）',
        tags: ['字典管理'],
    )]
    #[OA\Parameter(name: 'page', description: '页码', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', description: '每页数量', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'search', description: '搜索关键词', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'category', description: '分类筛选', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[AllowAnonymous(requireToken: false, requirePermission: false)]
    #[SimpleResponse(schema: [], example: [])]
    public function enumDictList(Request $request): \support\Response
    {
        $page     = (int)$request->input('page', 1);
        $limit    = (int)$request->input('limit', 10);
        $search   = $request->input('search', '');
        $category = $request->input('category', '');
        $service  = Container::make(EnumService::class);
        $result   = $service->getEnumsWithPagination($page, $limit, $search, $category);
        return Json::success('ok', $result);
    }

    #[OA\Get(
        path: '/system/dict/enum/list',
        summary: '枚举字典列表（不分页）',
        tags: ['字典管理'],
    )]
    #[AllowAnonymous(requireToken: false, requirePermission: false)]
    #[SimpleResponse(schema: [], example: [])]
    public function getAllDict(Request $request): \support\Response
    {
        $service = Container::make(EnumService::class);
        $data    = $service->getAllEnums();
        return Json::success('ok', $data);
    }
}
