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

namespace app\adminapi\controller\system;

use app\adminapi\controller\Crud;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\adminapi\schema\request\system\dict\DictFormRequest;
use app\adminapi\schema\request\system\dict\DictQueryRequest;
use app\adminapi\schema\response\system\DictResponse;
use app\adminapi\validate\system\dict\DictValidate;
use app\schema\request\BatchDeleteRequest;
use app\schema\request\IdRequest;
use app\service\admin\system\dict\DictService;
use app\service\core\enum\EnumService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Container;
use support\Request;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class DictController extends Crud
{
    public function __construct(DictService $service, DictValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/system/dict',
        summary: '列表',
        tags: ['字典管理'],
        x: [SchemaConstants::X_SCHEMA_REQUEST => DictQueryRequest::class]
    )]
    #[Permission(code: 'dict:dict:list')]
    #[PageResponse(schema: DictResponse::class, example: [])]
    public function index(Request $request): \support\Response
    {
        return parent::index($request);
    }

    #[OA\Post(
        path: '/system/dict',
        summary: '新增',
        tags: ['字典管理'],
        x: [SchemaConstants::X_SCHEMA_REQUEST => DictFormRequest::class]
    )]
    #[Permission(code: 'dict:dict:create')]
    #[SimpleResponse(schema: [], example: [])]
    public function store(Request $request): \support\Response
    {
        return parent::store($request);
    }

    #[OA\Put(
        path: '/system/dict/{id}',
        summary: '更新',
        tags: ['字典管理'],
        x: [SchemaConstants::X_SCHEMA_REQUEST => DictFormRequest::class]
    )]
    #[OA\Parameter(
        name: 'id',
        description: '字典ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', default: 0),
    )]
    #[Permission(code: 'dict:dict:update')]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        return parent::update($request);
    }

    #[OA\Delete(
        path: '/system/dict/{id}',
        summary: '删除',
        tags: ['字典管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'dict:dict:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Delete(
        path: '/system/dict',
        summary: '批量删除',
        tags: ['字典管理'],
        x: [SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class]
    )]
    #[Permission(code: 'dict:dict:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function batchDelete(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Get(
        path: '/system/dict/{id}',
        summary: '详情',
        tags: ['字典管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'dict:dict:read')]
    #[SimpleResponse(schema: DictResponse::class, example: [])]
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
    #[Permission(code: 'dict:dict:options')]
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
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/system/dict/enum/list',
        summary: '枚举字典列表',
        tags: ['字典管理'],
    )]
    #[Permission(code: 'dict:dict:enum_list')]
    #[SimpleResponse(schema: [], example: [
        'name'           => 'CategoryEnum',
        'namespace'      => 'app\\enum\\common\\CategoryEnum',
        'category'       => 'common',
        'category_label' => '通用枚举',
        'code'           => 'common.categoryenum',
        'options'        => [
            [
                'label' => '通用枚举',
                'value' => 'common',
                'color' => 'blue',
                'ext'   => [],
            ],
            [
                'label' => '系统枚举',
                'value' => 'system',
                'color' => 'green',
                'ext'   => [],
            ],
            [
                'label' => '开发枚举',
                'value' => 'dev',
                'color' => 'orange',
                'ext'   => [],
            ],
            [
                'label' => '监控枚举',
                'value' => 'monitor',
                'color' => 'red',
                'ext'   => [],
            ],
            [
                'label' => '业务枚举',
                'value' => 'business',
                'color' => 'purple',
                'ext'   => [],
            ],
        ],
        'count'          => 5,
    ])]
    public function enumDictList(Request $request): \support\Response
    {
        $page     = $request->input('page', 1);
        $limit    = $request->input('limit', 10);
        $search   = $request->input('search', '');
        $category = $request->input('category', '');
        $service  = Container::make(EnumService::class);
        $result   = $service->getEnumsWithPagination($page, $limit, $search, $category);
        return Json::success('ok', $result);
    }

}
