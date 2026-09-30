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
use app\platform\validate\system\AdminValidate;
use app\service\admin\system\AdminRoleService;
use app\service\platform\system\AdminService;
use core\foundation\exception\handler\AdminException;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Container;
use support\Request;

/**
 * 平台端用户管理
 *
 * 与 adminapi 用户管理共享 AdminDao/AdminModel，
 * 但查询时绕过 TenantScope，支持查看和管理所有租户的用户。
 */
#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class AdminController extends Base
{
    public function __construct(AdminService $service, AdminValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/system/admin',
        summary: '用户列表（平台端：全部租户）',
        tags: ['平台用户管理'],
    )]
    #[PageResponse(schema: [], example: [])]
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
            [$total, $list] = $this->service->getList($where, $field, $page, $limit, $order, [], false);
            return call_user_func([$this, $format_function], $list, $total);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/system/admin/{id}',
        summary: '用户详情（平台端）',
        tags: ['平台用户管理'],
    )]
    #[OA\Parameter(name: 'id', description: '用户ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[SimpleResponse(schema: [], example: [])]
    public function show(Request $request): \support\Response
    {
        try {
            $id     = $request->route->param('id');
            $result = $this->service->getAdminById($id);
            if (!$result) {
                return Json::fail('用户不存在');
            }
            return Json::success('ok', $result->toArray());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/system/admin',
        summary: '新增用户（平台端）',
        tags: ['平台用户管理'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function store(Request $request): \support\Response
    {
        try {
            $data = $this->inputFilter($request->all(), ['post_id_list', 'role_id_list', 'dept_id_list', 'main_dept_id', 'main_post_id']);
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

    #[OA\Put(
        path: '/system/admin/{id}',
        summary: '更新用户（平台端）',
        tags: ['平台用户管理'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $this->inputFilter($request->all(), ['post_id_list', 'role_id_list', 'dept_id_list', 'main_dept_id', 'main_post_id']);
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

    #[OA\Delete(
        path: '/system/admin/{id}',
        summary: '删除用户（平台端）',
        tags: ['平台用户管理'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        try {
            $data = $this->getDeleteIds($request);
            $this->service->batchDelete($data);
            return Json::success('ok');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/system/admin',
        summary: '批量删除用户（平台端）',
        tags: ['平台用户管理'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function batchDelete(Request $request): \support\Response
    {
        try {
            $data = $this->getDeleteIds($request);
            $this->service->batchDelete($data);
            return Json::success('ok');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/system/admin/{id}/locked',
        summary: '冻结用户（平台端）',
        tags: ['平台用户管理'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function locked(Request $request): \support\Response
    {
        try {
            $data = $request->input('data', []);
            $id   = $request->input('id');
            if (!empty($data)) {
                $id = $data;
            } elseif (empty($id)) {
                return Json::fail('Either id or data must be provided.');
            }
            $this->service->locked($id);
            return Json::success('ok');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/system/admin/{id}/unlocked',
        summary: '解冻用户（平台端）',
        tags: ['平台用户管理'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function unLocked(Request $request): \support\Response
    {
        try {
            $data = $request->input('data', []);
            $id   = $request->input('id');
            if (!empty($data)) {
                $id = $data;
            } elseif (empty($id)) {
                return Json::fail('Either id or data must be provided.');
            }
            $this->service->unLocked($id);
            return Json::success('ok');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/system/admin/{id}/change-password',
        summary: '重置密码（平台端）',
        tags: ['平台用户管理'],
    )]
    #[SimpleResponse(example: '{"code": 0,"msg": "success"}')]
    public function changePassword(Request $request): \support\Response
    {
        try {
            $ids      = $request->input('ids');
            $password = $request->input('password', 123456);
            $data     = ['password' => password_hash($password, PASSWORD_DEFAULT)];
            $this->service->batchUpdate($ids, $data);
            return Json::success('ok');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/system/admin/grant-role',
        summary: '授权角色（平台端）',
        tags: ['平台用户管理'],
    )]
    #[SimpleResponse(schema: [], example: '{"code": 0,"msg": "ok"}')]
    public function grantRole(Request $request): \support\Response
    {
        try {
            $data = $this->inputFilter($request->all(), ['id', 'role_id_list']);
            /** @var AdminRoleService $systemUserRoleService */
            $systemUserRoleService = Container::make(AdminRoleService::class);
            $systemUserRoleService->save($data);
            return Json::success('ok');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }
}
