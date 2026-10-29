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
        path: '/site/nav-config',
        summary: '获取站点导航配置（登录态无关，全量输出；is_public 显隐由前端按登录态过滤）',
        tags: ['站点模块'],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [
        'routing_mode'  => 'hybrid',
        'max_nav_items' => 6,
        'codes'         => ['page.about'],
        'menus'         => [
            [
                'name'          => '关于我们',
                'url'           => '/page/about',
                'external'      => false,
                'active_prefix' => ['/page/about'],
                'icon'          => '',
                'is_public'     => true,
                'is_no_auth'    => false,
                'code'          => '',
                'children'      => [],
            ],
        ],
        'member_menus'   => [],
        'header_actions' => [],
        'footer_menus'   => [],
    ])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getNavConfig(): Response
    {
        try {
            $result = $this->service->getNavConfig();
            return Json::success('获取成功', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }
}
