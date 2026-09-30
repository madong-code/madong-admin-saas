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
use app\service\api\auth\WechatService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use Webman\Http\Request;
use Webman\Http\Response;

#[OA\Tag(name: '微信认证')]
final class WechatController extends Base
{
    public function __construct(WechatService $service)
    {
        $this->service = $service;
    }

    #[OA\Post(
        path: '/auth/wechat',
        summary: '微信授权登录',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(property: 'code', description: '微信授权码', type: 'string'),
                    new OA\Property(property: 'encrypted_data', description: '加密数据', type: 'string'),
                    new OA\Property(property: 'iv', description: '加密向量', type: 'string'),
                ]
            )
        ),
        tags: ['微信认证'],
        responses: [
            new OA\Response(response: 200, description: '登录成功'),
            new OA\Response(response: 400, description: '参数错误'),
            new OA\Response(response: 401, description: '登录失败'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function wechatLogin(Request $request): Response
    {
        $data   = $request->post();
        $result = $this->service->wechatLogin($data);
        return json($result);
    }

    #[OA\Post(
        path: '/auth/weapp',
        summary: '微信小程序登录',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(property: 'code', description: '小程序登录code', type: 'string'),
                    new OA\Property(property: 'encrypted_data', description: '加密数据', type: 'string'),
                    new OA\Property(property: 'iv', description: '加密向量', type: 'string'),
                ]
            )
        ),
        tags: ['微信认证'],
        responses: [
            new OA\Response(response: 200, description: '登录成功'),
            new OA\Response(response: 400, description: '参数错误'),
            new OA\Response(response: 401, description: '登录失败'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function weappLogin(Request $request): Response
    {
        $data   = $request->post();
        $result = $this->service->weappLogin($data);
        return json($result);
    }

    #[OA\Post(
        path: '/auth/wechat/scan',
        summary: '生成微信扫码登录二维码',
        tags: ['微信认证']
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function generateWechatQrCode(): Response
    {
        $result = $this->service->generateWechatQrCode();
        return Json::success($result);
    }

    /**
     * 检查微信扫码状态
     */
    #[OA\Get(
        path: '/auth/wechat/scan/status',
        summary: '检查微信扫码状态',
        tags: ['微信认证'],
        parameters: [
            new OA\Parameter(name: 'scene_id', description: '场景ID', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function checkWechatScanStatus(Request $request): Response
    {
        try {
            $sceneId = $request->input('scene_id');
            if (empty($sceneId)) {
                return Json::fail('scene_id 不能为空');
            }
            $result = $this->service->checkWechatScanStatus($sceneId);
            return Json::success($result['msg'], $result['data']);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/auth/wechat/availability',
        summary: '检查微信登录可用性',
        tags: ['微信认证'],
        responses: [
            new OA\Response(response: 200, description: '查询成功'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function checkWechatAvailability(): Response
    {
        $result = $this->service->checkWechatAvailability();
        return Json::success('ok', $result);
    }
}