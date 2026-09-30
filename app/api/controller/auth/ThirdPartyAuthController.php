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
namespace app\api\controller\auth;

use app\api\controller\Base;
use app\api\validate\auth\ThirdPartyAuthValidate;
use app\service\api\auth\ThirdPartyAuthService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use support\Request;
use support\Response;

/**
 * 第三方认证控制器
 */
class ThirdPartyAuthController extends Base
{
    public function __construct(ThirdPartyAuthService $service, ThirdPartyAuthValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[\OpenApi\Attributes\Get(
        path: '/auth/qq/callback',
        summary: 'QQ回调',
        tags: ['第三方认证'],
        parameters: [
            new \OpenApi\Attributes\Parameter(name: 'scene', description: '场景ID', in: 'query', required: true),
            new \OpenApi\Attributes\Parameter(name: 'code', description: '授权码', in: 'query', required: true),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function qqCallback(Request $request): Response
    {
        try {
            $scene = $request->input('scene');
            $code  = $request->input('code');

            if (empty($scene) || empty($code)) {
                throw new \Exception('参数错误', 400);
            }

            $this->service->handleQqCallback($scene, $code);
            return Json::success('绑定成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: -1);
        }
    }

    #[\OpenApi\Attributes\Get(
        path: '/auth/wechat/callback',
        summary: '微信回调',
        tags: ['第三方认证'],
        parameters: [
            new \OpenApi\Attributes\Parameter(name: 'scene', description: '场景ID', in: 'query', required: true),
            new \OpenApi\Attributes\Parameter(name: 'code', description: '授权码', in: 'query', required: true),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function wechatCallback(Request $request): Response
    {
        try {
            $scene = $request->input('scene');
            $code  = $request->input('code');

            if (empty($scene) || empty($code)) {
                throw new \Exception('参数错误', 400);
            }

            $this->service->handleWechatCallback($scene, $code);
            return Json::success('绑定成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: -1);
        }
    }

    #[\OpenApi\Attributes\Get(
        path: '/auth/weibo/callback',
        summary: '微博回调',
        tags: ['第三方认证'],
        parameters: [
            new \OpenApi\Attributes\Parameter(name: 'scene', description: '场景ID', in: 'query', required: true),
            new \OpenApi\Attributes\Parameter(name: 'code', description: '授权码', in: 'query', required: true),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function weiboCallback(Request $request): Response
    {
        try {
            $scene = $request->input('scene');
            $code  = $request->input('code');

            if (empty($scene) || empty($code)) {
                throw new \Exception('参数错误', 400);
            }

            $this->service->handleWeiboCallback($scene, $code);
            return Json::success('绑定成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: -1);
        }
    }

    #[\OpenApi\Attributes\Get(
        path: '/auth/douyin/callback',
        summary: '抖音回调',
        tags: ['第三方认证'],
        parameters: [
            new \OpenApi\Attributes\Parameter(name: 'scene', description: '场景ID', in: 'query', required: true),
            new \OpenApi\Attributes\Parameter(name: 'code', description: '授权码', in: 'query', required: true),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function douyinCallback(Request $request): Response
    {
        try {
            $scene = $request->input('scene');
            $code  = $request->input('code');
            if (empty($scene) || empty($code)) {
                throw new \Exception('参数错误', 400);
            }
            $this->service->handleDouyinCallback($scene, $code);
            return Json::success('绑定成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: -1);
        }
    }

    #[\OpenApi\Attributes\Get(
        path: '/auth/third-party/list',
        summary: '获取第三方绑定列表',
        tags: ['第三方认证'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getThirdPartyList(): Response
    {
        try {
            $result = $this->service->getThirdPartyList();
            return Json::success('获取成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: -1);
        }
    }

    #[\OpenApi\Attributes\Post(
        path: '/auth/third-party/bind',
        summary: '绑定第三方账号',
        requestBody: new \OpenApi\Attributes\RequestBody(
            required: true,
            content: new \OpenApi\Attributes\MediaType(
                mediaType: 'application/json',
                schema: new \OpenApi\Attributes\Schema(
                    properties: [
                        new \OpenApi\Attributes\Property(property: 'platform', description: '平台类型', type: 'integer'),
                        new \OpenApi\Attributes\Property(property: 'openid', description: 'OpenID', type: 'string'),
                        new \OpenApi\Attributes\Property(property: 'unionid', description: 'UnionID', type: 'string'),
                        new \OpenApi\Attributes\Property(property: 'nickname', description: '昵称', type: 'string'),
                        new \OpenApi\Attributes\Property(property: 'avatar', description: '头像', type: 'string'),
                        new \OpenApi\Attributes\Property(property: 'gender', description: '性别', type: 'integer'),
                        new \OpenApi\Attributes\Property(property: 'country', description: '国家', type: 'string'),
                        new \OpenApi\Attributes\Property(property: 'province', description: '省份', type: 'string'),
                        new \OpenApi\Attributes\Property(property: 'city', description: '城市', type: 'string'),
                        new \OpenApi\Attributes\Property(property: 'access_token', description: '访问令牌', type: 'string'),
                        new \OpenApi\Attributes\Property(property: 'refresh_token', description: '刷新令牌', type: 'string'),
                    ]
                )
            )
        ),
        tags: ['第三方认证']
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function bindThirdParty(Request $request): Response
    {
        try {
            $validate = new ThirdPartyAuthValidate();
            $data     = $request->all();
            if (!$validate->scene('bind')->check($data)) {
                throw new \Exception($validate->getError());
            };
            $this->service->bindThirdParty($data);
            return Json::success('绑定成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: -1);
        }
    }

    #[\OpenApi\Attributes\Delete(
        path: '/auth/third-party/unbind/{platform}',
        summary: '解绑第三方账号',
        tags: ['第三方认证'],
        parameters: [
            new \OpenApi\Attributes\Parameter(name: 'platform', description: '平台类型', in: 'path', required: true),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function unbindThirdParty(Request $request, int $platform): Response
    {
        try {
            $this->service->unbindThirdParty($platform);
            return Json::success('解绑成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: -1);
        }
    }

    #[\OpenApi\Attributes\Post(
        path: '/auth/third-party/qr-code',
        summary: '生成第三方绑定二维码',
        requestBody: new \OpenApi\Attributes\RequestBody(
            required: true,
            content: new \OpenApi\Attributes\MediaType(
                mediaType: 'application/json',
                schema: new \OpenApi\Attributes\Schema(
                    properties: [
                        new \OpenApi\Attributes\Property(property: 'platform', description: '平台类型', type: 'integer'),
                    ]
                )
            )
        ),
        tags: ['第三方认证'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function generateBindQrCode(Request $request): Response
    {
        try {
            $platform = $request->post('platform');
            if (empty($platform)) {
                throw new \Exception('平台类型不能为空', 400);
            }

            $result = $this->service->generateBindQrCode(['platform' => $platform]);
            return Json::success('生成成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: -1);
        }
    }

    #[\OpenApi\Attributes\Get(
        path: '/auth/third-party/qr-status',
        summary: '检查绑定二维码状态',
        tags: ['第三方认证'],
        parameters: [
            new \OpenApi\Attributes\Parameter(name: 'scene_id', description: '场景ID', in: 'query', required: true),
        ],
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function checkBindQrStatus(Request $request): Response
    {
        try {
            $sceneId = $request->input('scene_id');
            if (empty($sceneId)) {
                throw new \Exception('场景ID不能为空', 400);
            }

            $result = $this->service->checkBindQrStatus($sceneId);
            return Json::success('检查成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: -1);
        }
    }
}
