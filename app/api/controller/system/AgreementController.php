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
use app\service\api\system\AgreementService;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use Webman\Http\Response;

#[OA\Tag(name: '协议模块')]
final class AgreementController extends Base
{
    public function __construct(AgreementService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/agreement/{key}',
        summary: '获取协议内容',
        tags: ['协议模块'],
        parameters: [
            new OA\Parameter(name: 'key', description: '协议标识', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
            new OA\Response(response: 404, description: '协议不存在'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getAgreementContent(string $key): Response
    {
        $result = $this->service->getAgreementContent($key);
        return json($result);
    }
}