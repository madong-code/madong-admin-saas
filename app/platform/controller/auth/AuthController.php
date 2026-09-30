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
namespace app\platform\controller\auth;

use app\platform\controller\Base;
use app\service\platform\auth\AuthService;
use core\foundation\tool\Json;
use core\io\upload\UploadFile;
use core\io\upload\UploadScene;
use core\security\jwt\JwtToken;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;

#[OA\Tag(name: '认证', description: '用户认证与个人中心')]
#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class AuthController extends Base
{
    public function __construct(AuthService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/auth/user-info',
        summary: '获取当前用户信息',
        security: [['ApiKey' => []]],
        tags: ['认证'],
    )]
    #[SimpleResponse(schema: [], example: ['id' => '1', 'user_name' => 'admin', 'real_name' => '管理员'])]
    public function getUserInfo(Request $request): \support\Response
    {
        try {
            $jwtToken = new JwtToken();
            $userId = $jwtToken->id();

            if (empty($userId)) {
                return Json::fail('用户未登录', [], 401);
            }

            $userInfo = $this->service->getUserInfo($userId);
            if (empty($userInfo)) {
                return Json::fail('用户不存在', [], 404);
            }

            return Json::success('ok', $userInfo);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: 400);
        }
    }

    #[OA\Get(
        path: '/auth/menus',
        summary: '获取动态菜单',
        security: [['ApiKey' => []]],
        tags: ['认证'],
        parameters: [
            new OA\Parameter(name: 'include_buttons', description: '是否包含按钮权限', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function getMenus(Request $request): \support\Response
    {
        try {
            $jwtToken = new JwtToken();
            $userId = $jwtToken->id();

            if (empty($userId)) {
                return Json::fail('用户未登录', [], 401);
            }

            $includeButtons = (bool)$request->input('include_buttons', false);
            $menus = $this->service->getMenusByUser($userId, $includeButtons);

            return Json::success('ok', $menus);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: 400);
        }
    }

    #[OA\Put(
        path: '/auth/user-info',
        summary: '更新当前用户基本信息',
        security: [['ApiKey' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'real_name', description: '姓名', type: 'string'),
                    new OA\Property(property: 'nick_name', description: '昵称', type: 'string'),
                    new OA\Property(property: 'email', description: '邮箱', type: 'string'),
                    new OA\Property(property: 'mobile_phone', description: '手机号', type: 'string'),
                    new OA\Property(property: 'avatar', description: '头像', type: 'string'),
                ]
            )
        ),
        tags: ['认证'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function updateUserInfo(Request $request): \support\Response
    {
        try {
            $jwtToken = new JwtToken();
            $userId = $jwtToken->id();

            if (empty($userId)) {
                return Json::fail('用户未登录', [], 401);
            }

            $data = $request->all();
            $userInfo = $this->service->updateUserInfo($userId, $data);

            return Json::success('更新成功', $userInfo);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: 400);
        }
    }

    #[OA\Put(
        path: '/auth/profile/avatar',
        summary: '上传并更新头像',
        description: '上传头像文件并自动更新用户头像字段，一步完成',
        security: [['ApiKey' => []]],
        tags: ['认证'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'file',
                        description: '头像文件',
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
    ), example: ['avatar' => '/upload/avatar/202606/abc123.jpg'])]
    public function updateAvatar(Request $request): \support\Response
    {
        try {
            $jwtToken = new JwtToken();
            $userId = $jwtToken->id();

            if (empty($userId)) {
                return Json::fail('用户未登录', [], 401);
            }

            $uploadFile = $request->file('file');
            if (empty($uploadFile)) {
                return Json::fail('请选择要上传的头像文件');
            }

            // 头像按年月存储
            $subDir = 'avatar/' . date('Ym');
            $disk = UploadFile::disk(null, true, UploadScene::platform());
            $result = $disk->uploadFile(['sub_dir' => $subDir]);
            $data = $result[0];

            $avatarUrl = str_replace('\\', '/', $data['url'] ?? '');
            if (empty($avatarUrl)) {
                throw new \Exception('头像上传失败');
            }

            $avatarPath = parse_url($avatarUrl, PHP_URL_PATH) ?? $avatarUrl;

            // 直接更新用户头像
            $this->service->updateUserInfo($userId, ['avatar' => $avatarPath]);

            return Json::success('头像更新成功', ['avatar' => $avatarPath]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: 400);
        }
    }

    #[OA\Put(
        path: '/auth/password',
        summary: '修改当前用户密码',
        security: [['ApiKey' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['old_password', 'new_password'],
                properties: [
                    new OA\Property(property: 'old_password', description: '旧密码', type: 'string'),
                    new OA\Property(property: 'new_password', description: '新密码', type: 'string'),
                ]
            )
        ),
        tags: ['认证'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function changePassword(Request $request): \support\Response
    {
        try {
            $jwtToken = new JwtToken();
            $userId = $jwtToken->id();

            if (empty($userId)) {
                return Json::fail('用户未登录', [], 401);
            }

            $oldPassword = $request->input('old_password', '');
            $newPassword = $request->input('new_password', '');

            if (empty($oldPassword) || empty($newPassword)) {
                return Json::fail('旧密码和新密码不能为空');
            }

            $this->service->changePassword($userId, $oldPassword, $newPassword);

            return Json::success('密码修改成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: 400);
        }
    }

    #[OA\Get(
        path: '/auth/permissions',
        summary: '获取用户权限码列表',
        security: [['ApiKey' => []]],
        tags: ['认证'],
    )]
    #[SimpleResponse(schema: [], example: ['permissions' => ['permission_code_1', 'permission_code_2']])]
    public function getPermissions(Request $request): \support\Response
    {
        try {
            $jwtToken = new JwtToken();
            $userId = $jwtToken->id();

            if (empty($userId)) {
                return Json::fail('用户未登录', [], 401);
            }

            $permissions = $this->service->getPermissions($userId);

            return Json::success('ok', $permissions);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: 400);
        }
    }
}
