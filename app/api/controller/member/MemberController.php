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
namespace app\api\controller\member;

use app\api\controller\Base;
use app\api\CurrentMember;
use app\api\event\member\MemberInfoFetchedEvent;
use app\service\api\member\MemberBalanceService;
use app\service\api\member\MemberBindService;
use app\service\api\member\MemberService;
use core\foundation\exception\handler\UnauthorizedHttpException;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use support\Container;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Http\UploadFile as WebmanUploadFile;

/**
 * 会员用户控制器
 */
#[OA\Tag(name: '会员模块')]
final class MemberController extends Base
{
    protected MemberBalanceService $balanceService;
    protected MemberBindService $bindService;

    public function __construct(
        MemberService $service,
        MemberBalanceService $balanceService,
        MemberBindService $bindService
    ) {
        $this->service = $service;
        $this->balanceService = $balanceService;
        $this->bindService = $bindService;
    }

    #[OA\Get(
        path: '/member/user-info',
        summary: '获取会员基本信息',
        tags: ['会员模块'],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
            new OA\Response(response: 401, description: '未登录'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getProfile(): Response
    {
        try {
            /** @var CurrentMember $currentMember */
            $currentMember = Container::make(CurrentMember::class);
            $data          = $currentMember->user(true);
            if (empty($data)) {
                throw new UnauthorizedHttpException('用户凭证失效请重新登录');
            }

            $permissions = [];
            $extra       = [];

            $event = new MemberInfoFetchedEvent($data['id'], $data, $permissions, $extra);
            $event->dispatch();

            // 将修改后的数据回写到原变量
            $data['permissions'] = $event->getPermissions();

            if (!empty($event->getExtra())) {
                $data = array_merge($data, $event->getExtra());
            }

            return Json::success('ok', $data);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), null, 401);
        }
    }

    #[OA\Put(
        path: '/member/user/update',
        summary: '更新会员信息',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nickname', description: '昵称', type: 'string'),
                    new OA\Property(property: 'avatar', description: '头像', type: 'string'),
                    new OA\Property(property: 'gender', description: '性别', type: 'integer'),
                    new OA\Property(property: 'birthday', description: '生日', type: 'string'),
                ]
            )
        ),
        tags: ['会员模块'],
        responses: [
            new OA\Response(response: 200, description: '更新成功'),
            new OA\Response(response: 401, description: '未登录'),
            new OA\Response(response: 400, description: '参数错误'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function update(Request $request): Response
    {
        try {
            $data = $request->all();
            $this->service->updateInfo($data);
            return Json::success();
        } catch (\Exception $exception) {
            return Json::fail($exception->getMessage());
        }

    }

    #[OA\Post(
        path: '/member/user/avatar',
        summary: '上传头像',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'file',
                            description: '头像文件',
                            type: 'string',
                            format: 'binary'
                        ),
                    ]
                )
            )
        ),
        tags: ['会员模块'],
        responses: [
            new OA\Response(response: 200, description: '上传成功'),
            new OA\Response(response: 401, description: '未登录'),
            new OA\Response(response: 400, description: '参数错误'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function uploadAvatar(Request $request): Response
    {
        try {
            $file = $request->file('file');
            if (!$file || !$file instanceof WebmanUploadFile) {
                throw new \Exception('请上传头像文件', 400);
            }

            $result = $this->service->uploadAvatar($file);
            return Json::success('上传成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: -1);
        }
    }

    #[OA\Put(
        path: '/member/user/bind-phone',
        summary: '绑定会员手机号',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['phone', 'verify_code'],
                properties: [
                    new OA\Property(property: 'phone', description: '手机号', type: 'string'),
                    new OA\Property(property: 'verify_code', description: '验证码', type: 'string'),
                ]
            )
        ),
        tags: ['会员模块'],
        responses: [
            new OA\Response(response: 200, description: '绑定成功'),
            new OA\Response(response: 401, description: '未登录'),
            new OA\Response(response: 400, description: '参数错误'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function bindPhone(Request $request): Response
    {
        try {
            $data   = $request->post();
            $result = $this->service->bindPhone($data);
            return Json::success($result['msg'] ?? '绑定成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/member/user/change-password',
        summary: '修改密码',
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
        tags: ['会员模块'],
        responses: [
            new OA\Response(response: 200, description: '修改成功'),
            new OA\Response(response: 401, description: '未登录'),
            new OA\Response(response: 400, description: '参数错误'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function changePassword(Request $request): Response
    {
        try {
            $data = $request->all();
            $this->service->changePassword($data);
            return Json::success('密码修改成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: -1);
        }
    }

    #[OA\Get(
        path: '/member/user/address/list',
        summary: '获取地址列表',
        tags: ['会员模块'],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
            new OA\Response(response: 401, description: '未登录'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getAddressList(): Response
    {
        try {
            $result = $this->service->getAddressList();
            return Json::success('获取成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/member/user/address/create',
        summary: '创建地址',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'phone', 'province', 'city', 'district', 'address'],
                properties: [
                    new OA\Property(property: 'name', description: '收件人姓名', type: 'string'),
                    new OA\Property(property: 'phone', description: '手机号', type: 'string'),
                    new OA\Property(property: 'province', description: '省份', type: 'string'),
                    new OA\Property(property: 'city', description: '城市', type: 'string'),
                    new OA\Property(property: 'district', description: '区域', type: 'string'),
                    new OA\Property(property: 'address', description: '详细地址', type: 'string'),
                    new OA\Property(property: 'is_default', description: '是否默认地址', type: 'boolean'),
                ]
            )
        ),
        tags: ['会员模块'],
        responses: [
            new OA\Response(response: 200, description: '创建成功'),
            new OA\Response(response: 401, description: '未登录'),
            new OA\Response(response: 400, description: '参数错误'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function createAddress(Request $request): Response
    {
        try {
            $data   = $request->post();
            $result = $this->service->createAddress($data);
            return Json::success('创建成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/member/user/address/update/{id}',
        summary: '更新地址',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', description: '收件人姓名', type: 'string'),
                    new OA\Property(property: 'phone', description: '手机号', type: 'string'),
                    new OA\Property(property: 'province', description: '省份', type: 'string'),
                    new OA\Property(property: 'city', description: '城市', type: 'string'),
                    new OA\Property(property: 'district', description: '区域', type: 'string'),
                    new OA\Property(property: 'address', description: '详细地址', type: 'string'),
                    new OA\Property(property: 'is_default', description: '是否默认地址', type: 'boolean'),
                ]
            )
        ),
        tags: ['会员模块'],
        parameters: [
            new OA\Parameter(name: 'id', description: '地址ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: '更新成功'),
            new OA\Response(response: 401, description: '未登录'),
            new OA\Response(response: 400, description: '参数错误'),
            new OA\Response(response: 404, description: '地址不存在'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function updateAddress(int $id, Request $request): Response
    {
        try {
            $data   = $request->post();
            $result = $this->service->updateAddress($id, $data);
            return Json::success('更新成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/member/user/address/delete/{id}',
        summary: '删除地址',
        tags: ['会员模块'],
        parameters: [
            new OA\Parameter(name: 'id', description: '地址ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功'),
            new OA\Response(response: 401, description: '未登录'),
            new OA\Response(response: 404, description: '地址不存在'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: true, requirePermission: false, description: '公共接口')]
    public function deleteAddress(int $id): Response
    {
        try {
            $result = $this->service->deleteAddress($id);
            return Json::success('删除成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/member/balance/record',
        summary: '获取余额流水记录',
        tags: ['会员模块'],
        parameters: [
            new OA\Parameter(name: 'page', description: '页码', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page_size', description: '每页数量', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
            new OA\Response(response: 401, description: '未登录'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getBalanceRecords(Request $request): Response
    {
        $params = $request->all();
        try {
            $result = $this->balanceService->getBalanceRecords($params);
            return Json::success('获取成功', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/member/balance/all',
        summary: '获取所有余额流水记录',
        tags: ['会员模块'],
        parameters: [
            new OA\Parameter(name: 'page', description: '页码', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page_size', description: '每页数量', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
            new OA\Response(response: 401, description: '未登录'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getAllBalanceRecords(Request $request): Response
    {
        $params = $request->all();
        try {
            $result = $this->balanceService->getAllBalanceRecords($params);
            return Json::success('获取成功', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/member/user/update-email',
        summary: '更新会员邮箱',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'verify_code'],
                properties: [
                    new OA\Property(property: 'email', description: '邮箱', type: 'string'),
                    new OA\Property(property: 'verify_code', description: '验证码', type: 'string'),
                ]
            )
        ),
        tags: ['会员模块'],
        responses: [
            new OA\Response(response: 200, description: '更新成功'),
            new OA\Response(response: 401, description: '未登录'),
            new OA\Response(response: 400, description: '参数错误'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function updateEmail(Request $request): Response
    {
        try {
            $data = $request->post();
            $this->service->updateEmail($data);
            return Json::success('更新成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

}
