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

    /**
     * 页脚申请友链（落库为待审核，后台「网站设置→友情链接」启用后前台展示）
     */
    #[OA\Post(
        path: '/site/link/apply',
        summary: '申请友情链接（页脚，待审核）',
        tags: ['友情链接模块'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'name', type: 'string', example: '示例站点'),
                new OA\Property(property: 'url', type: 'string', example: 'https://example.com'),
                new OA\Property(property: 'logo', type: 'string', example: ''),
                new OA\Property(property: 'description', type: 'string', example: ''),
                new OA\Property(property: 'contact', type: 'string', example: '邮箱 / QQ / 微信'),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: '提交成功'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function apply(Request $request): Response
    {
        try {
            $this->service->apply((array) $request->post());
            return Json::success('申请成功，等待审核');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }
}
