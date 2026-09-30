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
use app\service\api\site\LinkService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use support\Request;
use Webman\Http\Response;

#[OA\Tag(name: '友情链接模块')]
final class LinkController extends Base
{
    public function __construct(LinkService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取链接列表
     *
     * @param Request $request
     *
     * @return Response
     */
    #[OA\Get(
        path: '/site/link',
        summary: '获取友情链接',
        tags: ['友情链接模块'],
        parameters: [
            new OA\Parameter(
                name: 'type',
                description: '链接类型（footer-页脚）',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', default: 'footer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
        ]
    )]
    #[SimpleResponse(schema: [], example: '[]')]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function index(Request $request): Response
    {
        try {
            $type   = $request->get('type', 'footer');
            $result = $this->service->getLinksByType($type);
            return Json::success('获取成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 获取所有链接
     *
     * @return Response
     */
    #[OA\Get(
        path: '/site/link/all',
        summary: '获取所有链接',
        tags: ['友情链接模块'],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
        ]
    )]
    #[SimpleResponse(schema: [], example: '[]')]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getAll(): Response
    {
        try {
            $result = $this->service->getAllLinks();
            return Json::success('获取成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }
}
