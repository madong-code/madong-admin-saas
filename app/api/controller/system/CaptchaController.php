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
use app\service\api\system\CaptchaService;
use core\security\captcha\Captcha;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use support\Request;
use Webman\Http\Response;

/**
 * 验证码控制器
 */
#[OA\Tag(name: '验证码模块')]
final class CaptchaController extends Base
{
    public function __construct(CaptchaService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/captcha',
        summary: '获取验证码图片',
        tags: ['验证码模块'],
        parameters: [
            new OA\Parameter(name: 'time', description: '时间戳，用于防止缓存', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function captcha(Request $request): \support\Response
    {
        try {
            $captcha = new Captcha();
            $type    = $request->input('type', 'web-login');
            $result  = $captcha->captcha($request, $type);
            return Json::success('ok', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 发送短信验证码
     */
    #[OA\Post(
        path: '/sms/{type}',
        summary: '发送短信验证码',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['mobile'],
                properties: [
                    new OA\Property(property: 'mobile', description: '手机号', type: 'string'),
                ]
            )
        ),
        tags: ['验证码模块'],
        parameters: [
            new OA\Parameter(name: 'type', description: '验证码类型（login, register, bind等）', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '发送成功'),
            new OA\Response(response: 400, description: '参数错误'),
            new OA\Response(response: 429, description: '发送过于频繁'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function sendSmsCode(string $type): Response
    {
        $result = $this->service->sendSmsCode($type);
        return json($result);
    }
}