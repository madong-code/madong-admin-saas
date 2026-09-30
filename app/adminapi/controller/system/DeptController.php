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
use app\adminapi\schema\request\system\org\DeptFormRequest;
use app\adminapi\schema\request\system\org\DeptQueryRequest;
use app\adminapi\schema\response\system\DeptResponse;
use app\adminapi\validate\system\org\DeptValidate;
use app\schema\request\BatchDeleteRequest;
use app\schema\request\IdRequest;
use app\service\admin\system\org\DeptService;
use core\foundation\exception\handler\AdminException;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\RequestBody;
use support\Request;
use support\annotation\Middleware;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class DeptController extends Crud
{
    public function __construct(DeptService $service, DeptValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/system/dept',
        summary: '列表',
        tags: ['部门管理'],
        x: [SchemaConstants::X_SCHEMA_REQUEST => DeptQueryRequest::class]
    )]
    #[Permission(code: 'org:dept:list')]
    #[PageResponse(DeptResponse::class)]
    public function index(Request $request): \support\Response
    {
        try {
            [$where, $format, $limit, $field, $order, $page] = $this->selectInput($request);
            $methods         = [
                'select'     => 'formatSelect',
                'tree'       => 'formatTree',
                'table_tree' => 'formatTableTree',
                'normal'     => 'formatNormal',
            ];
            $format_function = $methods[$format] ?? 'formatNormal';
            $total           = $this->service->getCount($where);
            $list            = $this->service->selectList($where, $field, $page, $limit, $order, [], false);
            return call_user_func([$this, $format_function], $list, $total);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/system/dept',
        summary: '创建',
        tags: ['部门管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => DeptFormRequest::class,
        ]
    )]
    #[Permission(code: 'org:dept:create')]
    #[SimpleResponse(DeptResponse::class, example: '{"code":0,"msg":"created","id":1}')]
    public function store(Request $request): \support\Response
    {
        try {
            $data = $this->inputFilter($request->all(), ['leader_id_list']);
            if (isset($this->validate) && $this->validate) {
                if (!$this->validate->scene('store')->check($data)) {
                    throw new \Exception($this->validate->getError());
                }
            }
            $model = $this->service->save($data);
            if (empty($model)) {
                throw new AdminException('插入失败');
            }
            $pk = $model->getPk();
            return Json::success('ok', [$pk => $model->getData($pk)]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/system/dept/{id}',
        summary: '删除',
        tags: ['部门管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'org:dept:delete')]
    #[SimpleResponse( example: '{"code":0,"msg":"success","data":null}')]
    public function destroy(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Delete(
        path: '/system/dept',
        summary: '批量删除',
        tags: ['部门管理'],
        x: [SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class]
    )]
    #[Permission(code: 'org:dept:delete')]
    #[SimpleResponse(example: '{"code":0,"msg":"success","data":null}')]
    public function batchDelete(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Put(
        path: '/system/dept/{id}',
        summary: '更新',
        tags: ['部门管理'],
    )]
    #[Permission(code: 'org:dept:update')]
    #[SimpleResponse( example: '{"code":0,"msg":"success","data":null}')]
    public function update(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $this->inputFilter($request->all(), ['leader_id_list']);
            if (isset($this->validate) && $this->validate) {
                if (!$this->validate->scene('update')->check($data)) {
                    throw new \Exception($this->validate->getError());
                }
            }
            $this->service->update($id, $data);
            return Json::success('ok');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/system/dept/{id}',
        summary: '详情',
        tags: ['部门管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'org:dept:read')]
    #[SimpleResponse(DeptResponse::class, example: '{"code":0,"msg":"success","data":null}')]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

}
