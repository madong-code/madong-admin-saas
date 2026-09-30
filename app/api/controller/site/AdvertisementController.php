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
 * Official Website: https://madong.tech
 */

namespace app\api\controller\site;

use app\api\controller\Base;
use app\service\api\site\AdvertisementService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use support\Request;
use Webman\Http\Response;

#[OA\Tag(name: '广告模块')]
final class AdvertisementController extends Base
{
    public function __construct(AdvertisementService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取广告列表
     *
     * @return Response
     */
    #[OA\Get(
        path: '/site/ads',
        summary: '获取广告列表',
        tags: ['广告模块'],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
        ]
    )]
    #[SimpleResponse(schema: [], example: '[]')]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function index(): Response
    {
        try {
            $result = $this->service->getAds();
            return Json::success('获取成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 获取广告位信息
     *
     * @param Request $request
     *
     * @return Response
     */
    #[OA\Get(
        path: '/site/ads/info',
        summary: '获取广告位信息',
        tags: ['广告模块'],
        parameters: [
            new OA\Parameter(
                name: 'type',
                description: '广告类型',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
        ]
    )]
    #[SimpleResponse(schema: [], example: '[]')]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getInfo(Request $request): Response
    {
        try {
            $params = $request->get();
            $result = $this->service->getAdvertisementInfo($params);
            return Json::success('获取成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }
}
