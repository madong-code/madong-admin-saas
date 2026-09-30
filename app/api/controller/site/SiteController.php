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
namespace app\api\controller\site;

use app\api\controller\Base;
use app\service\api\site\SiteService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use Webman\Http\Response;

#[OA\Tag(name: '站点模块')]
final class SiteController extends Base
{
    public function __construct(SiteService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/site/site-data',
        summary: '获取站点首页数据',
        tags: ['站点模块'],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getSiteData(): Response
    {
        try {
            $result = $this->service->getSiteData();
            return Json::success('获取成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/site/routing-config',
        summary: '获取路由菜单模式配置',
        tags: ['站点模块'],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [
        'routing_mode'    => 'hybrid',
        'menus'           => [
            ['id' => 1, 'name' => '关于我们', 'category' => 1, 'pid' => 0, 'url' => '/page/about', 'icon' => ''],
            ['id' => 2, 'name' => '我的帖子', 'category' => 2, 'pid' => 0, 'url' => '/member/post', 'icon' => ''],
        ],
        'menu_visibility' => [
            '/page/example' => ['visible' => false],
            '/member/vip'   => ['permissions' => ['vip']],
        ],
        'max_nav_items'   => 6,
    ])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getRoutingConfig(): Response
    {
        try {
            $result = $this->service->getRoutingConfig();
            return Json::success('获取成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }
}
