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
use app\service\api\site\SiteConfigService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use Webman\Http\Response;

#[OA\Tag(name: '站点模块')]
final class SiteConfigController extends Base
{
    public function __construct(SiteConfigService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/site/site-config',
        summary: '站点全局配置（页脚版权/备案/入口开关/百度统计/会员开关）',
        tags: ['站点模块'],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [
        'site_name'          => 'Madong',
        'site_logo'          => '',
        'copyright'          => 'Copyright © 2026 madong.tech All Rights Reserved.',
        'icp_number'         => '',
        'icp_url'            => 'https://beian.miit.gov.cn/',
        'police_number'      => '',
        'police_url'         => 'https://beian.mps.gov.cn/',
        'show_apply'         => true,
        'show_powered'       => true,
        'contact_email'      => '',
        'tongji_enabled'     => false,
        'tongji_key'         => '',
        'membership_enabled' => false,
    ])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function index(): Response
    {
        try {
            return Json::success('获取成功', $this->service->get());
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }
}
