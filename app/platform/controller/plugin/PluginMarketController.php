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

namespace app\platform\controller\plugin;

use app\platform\controller\Base;
use app\service\platform\plugin\PluginMarketService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;

#[OA\Tag(name: '平台-模块市场', description: '平台端应用管理-模块市场')]
#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class PluginMarketController extends Base
{
    public function __construct(
        private readonly PluginMarketService $marketService
    ) {
    }

    #[OA\Get(
        path: '/plugin/market',
        summary: '模块市场列表',
        tags: ['平台-模块市场'],
        parameters: [
            new OA\Parameter(name: 'page', description: '页码', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limit', description: '每页数量', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'keyword', description: '搜索关键词', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[Permission('platform:plugin:market:list')]
    #[PageResponse(example: '{"code": 0,"msg": "ok","data": {"list": [],"total": 0}}')]
    public function index(Request $request): \support\Response
    {
        $page    = (int) $request->input('page', 1);
        $limit   = (int) $request->input('limit', 20);
        $keyword = $request->input('keyword', '');
        $result  = $this->marketService->getList($page, $limit, $keyword);
        // 统一响应字段名: list → items
        if (isset($result['list'])) {
            $result['items'] = $result['list'];
            unset($result['list']);
        }
        return Json::success('ok', $result);
    }
}
