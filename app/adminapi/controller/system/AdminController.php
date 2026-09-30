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
use app\adminapi\schema\request\system\AdminFormRequest;
use app\adminapi\schema\request\system\AdminQueryRequest;
use app\adminapi\schema\response\system\AdminResponse;
use app\adminapi\validate\system\admin\AdminValidate;
use app\schema\request\BatchDeleteRequest;
use app\schema\request\IdRequest;
use app\service\admin\system\admin\AdminRoleService;
use app\service\admin\system\admin\AdminService;
use core\foundation\exception\handler\AdminException;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\Container;
use support\Request;
use support\annotation\Middleware;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class AdminController extends Crud
{

    public function __construct(AdminService $service, AdminValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/system/admin',
        summary: '列表',
        tags: ['用户管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => AdminQueryRequest::class,
        ]
    )]
    #[Permission(code: 'system:admin:list')]
    #[PageResponse(schema: AdminResponse::class, example: [
        'id'           => '290809605012324352',
        'user_name'    => 'test11',
        'real_name'    => '123456',
        'nick_name'    => null,
        'is_super'     => 0,
        'mobile_phone' => '18973598654',
        'email'        => '405784684@qq.com',
        'avatar'       => null,
        'signed'       => null,
        'dashboard'    => null,
        'dept_id'      => null,
        'enabled'      => 1,
        'login_ip'     => null,
        'login_time'   => null,
        'created_by'   => 1,
        'updated_by'   => null,
        'created_at'   => '2026-03-13T03:33:32.000000Z',
        'updated_at'   => '2026-03-13T03:45:28.000000Z',
        'deleted_at'   => null,
        'sex'          => 1,
        'remark'       => 'sadf ',
        'birthday'     => null,
        'tel'          => null,
        'is_locked'    => 0,
        'created_date' => '2026-03-13 11:33:32',
        'updated_date' => '2026-03-13 11:45:28',
        'depts'        => [],
        'posts'        => [
            [
                'id'           => '290793153412726784',
                'dept_id'      => '290786837193555968',
                'code'         => 'tex',
                'name'         => '测试题',
                'sort'         => 1,
                'enabled'      => 1,
                'created_by'   => 1,
                'updated_by'   => null,
                'created_at'   => '2026-03-13T02:28:10.000000Z',
                'updated_at'   => '2026-03-13T02:28:10.000000Z',
                'deleted_at'   => null,
                'remark'       => 'sadv ',
                'created_date' => '2026-03-13 10:28:10',
                'updated_date' => '2026-03-13 10:28:10',
                'pivot'        => [
                    'admin_id' => '290809605012324352',
                    'post_id'  => '290793153412726784',
                ],
            ],
        ],
        'roles'        => [],
    ])]
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

    #[OA\Post(
        path: '/system/admin',
        summary: '插入',
        tags: ['用户管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => AdminFormRequest::class,
        ]
    )]
    #[Permission(code: 'system:admin:create')]
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
        summary: '更新',
        tags: ['用户管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => AdminFormRequest::class,
        ]
    )]
    #[Permission(code: 'system:admin:update')]
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

    #[OA\Get(
        path: '/system/admin/{id}',
        summary: '详情',
        tags: ['用户管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'system:admin:read')]
    #[SimpleResponse(schema: AdminResponse::class, example: [
        'id'           => '290809605012324352',
        'user_name'    => 'test11',
        'real_name'    => '123456',
        'nick_name'    => null,
        'is_super'     => 0,
        'mobile_phone' => '18973598654',
        'email'        => '405784684@qq.com',
        'avatar'       => null,
        'signed'       => null,
        'dashboard'    => null,
        'dept_id'      => null,
        'enabled'      => 1,
        'login_ip'     => null,
        'login_time'   => null,
        'created_by'   => 1,
        'updated_by'   => null,
        'created_at'   => '2026-03-13T03:33:32.000000Z',
        'updated_at'   => '2026-03-13T03:45:28.000000Z',
        'deleted_at'   => null,
        'sex'          => 1,
        'remark'       => 'sadf ',
        'birthday'     => null,
        'tel'          => null,
        'is_locked'    => 0,
        'created_date' => '2026-03-13 11:33:32',
        'updated_date' => '2026-03-13 11:45:28',
        'depts'        => [],
        'posts'        => [
            [
                'id'           => '290793153412726784',
                'dept_id'      => '290786837193555968',
                'code'         => 'tex',
                'name'         => '测试题',
                'sort'         => 1,
                'enabled'      => 1,
                'created_by'   => 1,
                'updated_by'   => null,
                'created_at'   => '2026-03-13T02:28:10.000000Z',
                'updated_at'   => '2026-03-13T02:28:10.000000Z',
                'deleted_at'   => null,
                'remark'       => 'sadv ',
                'created_date' => '2026-03-13 10:28:10',
                'updated_date' => '2026-03-13 10:28:10',
                'pivot'        => [
                    'admin_id' => '290809605012324352',
                    'post_id'  => '290793153412726784',
                ],
            ],
        ],
        'roles'        => [],
    ])]
    public function show(Request $request): \support\Response
    {
        try {
            $id     = $request->route->param('id');
            $result = $this->service->getAdminById($id)->toArray();
            return Json::success('ok', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/system/admin/{id}/locked',
        summary: '禁用',
        tags: ['用户管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'system:admin:locked')]
    #[SimpleResponse(schema: [], example: [])]
    public function locked(Request $request): \support\Response
    {
        try {
            // 获取单个 ID 和多个 ID
            $data = $request->input('data', []);
            $id   = $request->input('id');

            // 如果提供了多个 ID，优先使用多个 ID
            if (!empty($data)) {
                $id = $data;
            } else if (empty($id)) {
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
        summary: '解除',
        tags: ['用户管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'system:admin:unlocked')]
    #[SimpleResponse(schema: [], example: [])]
    public function unLocked(Request $request): \support\Response
    {
        try {
            // 获取单个 ID 和多个 ID
            $data = $request->input('data', []);
            $id   = $request->input('id');

            // 如果提供了多个 ID，优先使用多个 ID
            if (!empty($data)) {
                $id = $data;
            } else if (empty($id)) {
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
        summary: '重置密码',
        tags: ['用户管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'system:admin:change_password')]
    #[SimpleResponse(example: '{"code": 0,"msg": "success"}')]
    public function changePassword(Request $request): \support\Response
    {
        try {
            $ids      = $request->input('ids');
            $password = $request->input('password', 123456);
            $data     = ['password' => password_hash($password, PASSWORD_DEFAULT)];
            $this->service->batchUpdate(['id' => $ids], $data);
            return Json::success('ok');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/system/admin/grant-role',
        summary: '授权角色',
        tags: ['用户管理'],
    )]
    #[Permission(code: 'system:admin:grant_role')]
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


    #[OA\Delete(
        path: '/system/admin/{id}',
        summary: '删除',
        tags: ['用户管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'system:admin:delete')]
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
        summary: '批量删除',
        tags: ['用户管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class,
        ]
    )]
    #[Permission(code: 'system:admin:delete')]
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

}
