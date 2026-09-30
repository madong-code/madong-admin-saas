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
namespace app\api\controller\system;

use app\api\controller\Base;
use app\service\api\system\WechatService;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * 微信控制器
 */
#[OA\Tag(name: '微信模块')]
final class WechatController extends Base
{
    public function __construct(WechatService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/wechat/auth-code',
        summary: '获取微信授权码',
        tags: ['微信模块'],
        parameters: [
            new OA\Parameter(name: 'redirect_url', description: '回调URL', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
            new OA\Response(response: 400, description: '参数错误'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getWechatAuthCode(Request $request): Response
    {
        $params = $request->get();
        $result = $this->service->getWechatAuthCode($params);
        return json($result);
    }

    /**
     * 同步微信用户信息
     */
    #[OA\Get(
        path: '/wechat/sync',
        summary: '同步微信用户信息',
        tags: ['微信模块'],
        parameters: [
            new OA\Parameter(name: 'code', description: '微信授权码', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '同步成功'),
            new OA\Response(response: 400, description: '参数错误'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function syncWechatUserInfo(Request $request): Response
    {
        $params = $request->get();
        $result = $this->service->syncWechatUserInfo($params);
        return json($result);
    }

    #[OA\Get(
        path: '/wechat/jssdk-config',
        summary: '获取微信 JSSDK 配置',
        tags: ['微信模块'],
        parameters: [
            new OA\Parameter(name: 'url', description: '当前页面URL', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
            new OA\Response(response: 400, description: '参数错误'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getWechatJssdkConfig(Request $request): Response
    {
        $params = $request->get();
        $result = $this->service->getWechatJssdkConfig($params);
        return json($result);
    }
}