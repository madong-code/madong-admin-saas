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

namespace app\adminapi\controller\auth;

use app\adminapi\controller\Crud;
use app\adminapi\CurrentUser;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\adminapi\schema\request\auth\profile\ProfileUpdateRequest;
use app\adminapi\schema\request\auth\profile\PasswordUpdateRequest;
use app\service\admin\system\config\UploadService;
use app\adminapi\validate\profile\ProfileValidate;
use app\dao\ops\logs\LoginLogDao;
use app\schema\request\IdRequest;
use app\service\admin\ops\logs\LoginLogService;
use app\service\admin\system\admin\AdminService;
use core\business\tenant\context\TenantContext;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Container;
use support\Request;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[OA\Tag(name: '个人中心')]
#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class ProfileController extends Crud
{
    public function __construct(AdminService $service, ProfileValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/auth/profile',
        summary: '获取当前用户信息',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['个人中心']
    )]
    #[SimpleResponse(example: '{"id": 1, "user_name": "admin", "email": "test@example.com"}')]
    #[Permission(code: 'admin:profile:info')]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    public function show(Request $request): \support\Response
    {
        try {
            $currentUser = Container::make(CurrentUser::class);
            $data = $currentUser->admin(true);
            // 注入平台超管标识，与 AuthController::userInfo() 保持一致
            $data['is_platform_super'] = (int)$currentUser->isPlatformSuper();
            return Json::success('ok', $data);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/auth/profile',
        summary: '更新个人信息',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['个人中心']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: ProfileUpdateRequest::class)
    )]
    #[SimpleResponse(example: '{"code": 0, "msg": "success"}')]
    #[Permission(code: 'admin:profile:update_info')]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    public function update(Request $request): \support\Response
    {
        try {
            $data = $this->inputFilter($request->all());

            if (!$this->validate->scene('update-profile')->check($data)) {
                throw new \Exception($this->validate->getError());
            }

            $currentUser = Container::make(CurrentUser::class);
            $this->service->updateProfile($currentUser->id(), $data, $currentUser->admin()?->getConnectionName());
            return Json::success('个人信息更新成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/auth/profile/password',
        summary: '修改密码',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['个人中心']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: PasswordUpdateRequest::class)
    )]
    #[SimpleResponse(example: '{"code": 0, "msg": "密码修改成功"}')]
    #[Permission(code: 'admin:profile:password')]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    public function updatePassword(Request $request): \support\Response
    {
        try {
            $data = $this->inputFilter($request->all(), ['old_password', 'new_password', 'confirm_password']);

            if (!$this->validate->scene('update-password')->check($data)) {
                throw new \Exception($this->validate->getError());
            }

            $currentUser = Container::make(CurrentUser::class);
            $this->service->updateProfilePassword($currentUser->id(), $data['old_password'], $data['new_password'], $currentUser->admin()?->getConnectionName());
            return Json::success('密码修改成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/auth/profile/avatar',
        summary: '上传并更新头像',
        description: '上传头像文件到 avatar/ 目录并自动更新用户头像',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['个人中心'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'file',
                        description: '头像文件（支持 jpg/png/gif/webp）',
                        type: 'string',
                        format: 'binary',
                    ),
                ]
            )
        )
    )]
    #[SimpleResponse(schema: new OA\Schema(
        properties: [
            new OA\Property(property: 'avatar', description: '头像相对路径', type: 'string'),
        ]
    ), example: ['avatar' => '/upload/avatar/{tenant_id}/202606/abc123.jpg'])]
    #[Permission(code: 'admin:profile:avatar')]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    public function updateAvatar(Request $request): \support\Response
    {
        try {
            $uploadFile = $request->file('file');
            if (empty($uploadFile)) {
                return Json::fail('请选择要上传的头像文件');
            }

            $tenantId = TenantContext::getTenantIdOrDefault('default');
            $subDir = 'avatar/' . $tenantId . '/' . date('Ym');
            $result = Container::make(UploadService::class)->upload($subDir);

            $avatarUrl = $result->url ?? $result['url'] ?? '';
            if (empty($avatarUrl)) {
                throw new \Exception('头像上传失败，未获取到文件URL');
            }

            $currentUser = Container::make(CurrentUser::class);
            $this->service->updateProfileAvatar($currentUser->id(), $avatarUrl, $currentUser->admin()?->getConnectionName());

            $avatarPath = parse_url($avatarUrl, PHP_URL_PATH) ?? $avatarUrl;
            return Json::success('头像更新成功', ['avatar' => $avatarPath]);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/auth/profile/sessions',
        summary: '获取在线设备列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['个人中心']
    )]
    #[OA\Parameter(
        name: 'page',
        description: '页码',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        description: '每页数量',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 10)
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[Permission(code: 'admin:profile:sessions')]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    public function getSessions(Request $request): \support\Response
    {
        try {
            $page  = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 10);
            
            $currUser = Container::make(CurrentUser::class);
            $jwt = new \core\security\jwt\JwtToken();
            
            // 获取当前用户信息（用于兜底）
            $adminInfo = $currUser->admin(true) ?? [];
            $defaultUserName = $adminInfo['user_name'] ?? '';
            $defaultRealName = $adminInfo['real_name'] ?? '';
            $defaultAvatar = $adminInfo['avatar'] ?? '';
            
            // 从 JWT 获取真实的会话列表（存储层已自动按多租户过滤）
            $allSessions = $jwt->getSessions($currUser->id());

            // 按登录时间降序排序（最新登录在前面）
            usort($allSessions, function ($a, $b) {
                return ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0);
            });
            $total = count($allSessions);
            
            // 分页处理
            $offset = ($page - 1) * $limit;
            $sessions = array_slice($allSessions, $offset, $limit);
            
            // 格式化数据
            $items = array_map(function ($session) use ($defaultUserName, $defaultRealName, $defaultAvatar) {
                $extra = $session['extra'] ?? [];
                return [
                    'jti' => $session['jti'],
                    'client_type' => $session['client_type'] ?? 'admin',
                    'login_time' => $session['created_at'] ?? time(),
                    'ip' => $extra['ip'] ?? '',
                    'ip_location' => $extra['ip_location'] ?? '',
                    'os' => $extra['os'] ?? '',
                    'browser' => $extra['browser'] ?? '',
                    'status' => 1,
                    'user_name' => $extra['user_name'] ?? $defaultUserName,
                    'real_name' => $extra['real_name'] ?? $defaultRealName,
                    'avatar' => $extra['avatar'] ?? $defaultAvatar,
                ];
            }, $sessions);
            
            return Json::success('ok', compact('total', 'items'));
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * @throws \Throwable
     */
    #[OA\Delete(
        path: '/auth/profile/sessions/{jti}',
        summary: '强制下线指定设备',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['个人中心']
    )]
    #[OA\Parameter(
        name: 'jti',
        description: '会话JTI',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[Permission(code: 'admin:profile:kickout')]
    #[AllowAnonymous(requireToken: true, requirePermission: false)]
    public function kickoutSession(Request $request): \support\Response
    {
        try {
            $jti = $request->route->param('jti', null);
            $jwt = new \core\security\jwt\JwtToken();
            $result = $jwt->kickoutByJti($jti);
            
            if (!$result) {
                return Json::fail('会话不存在或已失效');
            }
            
            return Json::success('ok');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/auth/profile/{id}/update-preferences',
        summary: '更新用户前端偏好设置',
        tags: ['用户管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'preferences',
                    description: '偏好设置',
                    type: 'string',
                    example: '{"theme": "dark"}'
                ),
            ]
        )
    )]
    #[Permission(code: 'system:admin:update_preferences')]
    #[SimpleResponse(schema: [], example: [])]
    public function updatePreferences(Request $request): \support\Response
    {
        try {
            $uid  = Container::make(CurrentUser::class)->id();
            $data = $request->all();
            if (isset($this->validate) && $this->validate) {
                $data['id'] = $uid;
                if (!$this->validate->scene('update-preferences')->check($data)) {
                    throw new \Exception($this->validate->getError());
                }
            }
            $this->service->updateUserPreferences($uid, $data);
            return Json::success('ok');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}
