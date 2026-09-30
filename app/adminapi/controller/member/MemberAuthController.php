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

namespace app\adminapi\controller\member;

use app\adminapi\controller\Crud;
use app\adminapi\CurrentUser;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\adminapi\validate\member\MemberAuthValidate;
use app\service\admin\member\MemberAuthService;
use app\service\admin\member\MemberService;
use app\service\admin\member\MemberTagPermissionService;
use app\service\admin\member\MemberTagRelationService;
use madong\swagger\attribute\Permission;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\Container;
use support\Request;
use support\annotation\Middleware;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class MemberAuthController extends Crud
{
    public function __construct(MemberAuthService $service, MemberAuthValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/member/auth/permissions',
        summary: '权限菜单-包含所有',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['权限相关-会员'],
    )]
    #[Permission(code: 'member:auth:permissions')]
    #[SimpleResponse(schema: [], example: [])]
    public function getPermissions(Request $request): \support\Response
    {
        try {
            $format          = input('format', 'table_tree');
            $collection      = $this->service->getAllMenus(true);
            $methods         = [
                'select'     => 'formatSelect',
                'tree'       => 'formatTree',
                'table_tree' => 'formatTableTree',
                'normal'     => 'formatNormal',
            ];
            $format_function = $methods[$format] ?? 'formatNormal';
            $items           = $collection->toArray();
            return call_user_func([$this, $format_function], $collection, count($items));
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode());
        }
    }

    #[OA\Get(
        path: '/member/auth/{id}/tag-permissions',
        summary: '权限菜单-包含所有',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['权限相关-会员'],
    )]
    #[Permission(code: 'member:auth:tag_permissions')]
    #[SimpleResponse(example: '{"code": 0,"msg": "ok","data": []}')]
    public function getPermissionsByTag(Request $request, $id): \support\Response
    {
        try {
            $format          = input('format', 'tree');
            $collection      = $this->service->getMenusByMemberTags($id, true);
            $methods         = [
                'select'     => 'formatSelect',
                'tree'       => 'formatTree',
                'table_tree' => 'formatTableTree',
                'normal'     => 'formatNormal',
            ];
            $format_function = $methods[$format] ?? 'formatNormal';
            $items           = $collection->toArray();
            return call_user_func([$this, $format_function], $items, count($items));
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode());
        }
    }

    #[OA\Get(
        path: '/member/auth/perm-code',
        summary: '获取用户-权限码',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['权限相关-会员'],
    )]
    #[Permission(code: 'member:auth:perm_code')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => ['admin']])]
    public function getUserCodes(Request $request): \support\Response
    {
        try {
            /** @var CurrentUser $currUser */
            $currUser = Container::make(CurrentUser::class);
            $data     = $this->service->getCodesByUserRoles($currUser);
            return Json::success('ok', $data);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode());
        }
    }

    #[OA\Get(
        path: '/member/auth/tag-menu-ids',
        summary: '通过角色ID获取权限ID集合',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['权限相关-会员'],
    )]
    #[Permission(code: 'member:auth:role_menu_ids')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function tagMenuIds(Request $request): \support\Response
    {
        try {
            $tagId  = $request->input('tag_id');
            $result = $this->service->getMenusByTagId($tagId, true);
            if (empty($result)) {
                return Json::Success([]);
            }
            $data = [];
            foreach ($result as $item) {
                $data[] = $item->id;
            }
            return Json::success('ok', $data);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode());
        }
    }


    #[OA\Post(
        path: '/member/auth/save-tag-menu',
        summary: '保存角色菜单关系',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['权限相关-会员'],
    )]
    #[Permission(code: 'member:auth:role_menu')]
    #[SimpleResponse( example: '{"code": 0,"msg": "ok"}')]
    public function saveTagMenuRelation(Request $request): \support\Response
    {
        try {
            $data = $request->all();
            /** @var MemberTagPermissionService $systemRoleMenuService */
            $systemRoleMenuService = Container::make(MemberTagPermissionService::class);
            $systemRoleMenuService->save($data);
            return Json::success('success');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode());
        }
    }

    #[OA\Get(
        path: '/member/auth/user-list-by-tag-id',
        summary: '获取角色-关联用户列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['权限相关-会员'],
    )]
    #[OA\Parameter(
        name: 'tag_id',
        description: '标签ID',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[Permission(code: 'member:auth:tag_user_list')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function getUsersByTagId(Request $request): \support\Response
    {
        try {
            [$where, $format, $limit, $field, $order, $page] = $this->selectInput($request, [
                'allowed_fields' => ['username', 'nickname', 'email', 'mobile', 'tag_id'],  // 使用 null 表示不限制字段，允许所有模型字段
                'skip_fields'    => [],
                // 添加自定义参数会自动合并到 where 中
                'custom_fields'  => [],
            ]);
            /** @var MemberService $systemUserService */
            $systemUserService = Container::make(MemberService::class);
            $data              = $systemUserService->getUsersListByTagId($where, $field, $page, $limit);
            return Json::success('ok', $data);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode());
        }

    }

    #[OA\Post(
        path: '/member/auth/save-user-tag',
        summary: '保存用户-关联标签',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['权限相关-会员'],
    )]
    #[Permission(code: 'member:auth:save_user_tag')]
    #[SimpleResponse(example: '{"code": 0,"msg": "success"}')]
    public function saveUserTags(Request $request): \support\Response
    {
        try {
            $data = $request->all();
            /** @var MemberTagRelationService $memberTagRelationService */
            $memberTagRelationService = Container::make(MemberTagRelationService::class);
            $memberTagRelationService->saveUserTags($data);
            return Json::success('success');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode());
        }
    }

    #[OA\Post(
        path: '/member/auth/remove-user-tag',
        summary: '移除用户-关联角色',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['权限相关-会员'],
    )]
    #[Permission(code: 'member:auth:remove_user_tag')]
    #[SimpleResponse(example: '{"code": 0,"msg": "ok"}')]
    public function removeUserRole(Request $request): \support\Response
    {
        try {
            $data = $request->all();
            /** @var  MemberTagRelationService $MemberTagRelationService */
            $MemberTagRelationService = Container::make(MemberTagRelationService::class);
            $MemberTagRelationService->removeUserTag($data);
            return Json::success('ok');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode());
        }
    }

    #[OA\Get(
        path: '/member/auth/user-list-exclude-tag-id',
        summary: '获取用户列表-排除指定角色id',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['权限相关-会员'],
    )]
    #[OA\Parameter(
        name: 'tag_id',
        description: '标签ID',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[Permission(code: 'member:auth:user_list_exclude_tag')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function getUsersExcludingTag(Request $request): \support\Response
    {
        try {
            // 自定义参数
            $tagId = $request->input('tag_id');
            [$where, $format, $limit, $field, $order, $page] = $this->selectInput($request, [
                'allowed_fields' => null,  // 使用 null 表示不限制字段，允许所有模型字段
                'skip_fields'    => ['token', 'deleted_at'],
                // 添加自定义参数 tag_id，会自动合并到 where 中
                'custom_fields'  => [
                    'tag_id' => $tagId,
                ],
            ]);

            /** @var MemberService $memberUserService */
            $memberUserService = Container::make(MemberService::class);
            $data              = $memberUserService->getUsersExcludingTag($where, $field, $page, $limit);
            return Json::success('ok', $data);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode());
        }
    }

}

