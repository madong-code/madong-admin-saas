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

namespace app\platform\controller\system;

use app\platform\controller\Base;
use app\service\admin\system\role\RuleService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;


#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class RuleController extends Base
{
    public function __construct(RuleService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/system/rule-cate',
        summary: '获取接口分类（基于tags）',
        tags: ['接口管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function cate(Request $request): \support\Response
    {
        try {
            $result = $this->service->getCategories();
            return Json::success('ok', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/system/rule',
        summary: '获取接口列表（支持分类和搜索）',
        tags: ['接口管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function list(Request $request): \support\Response
    {
        try {
            $cateId = $request->input('cate_id');
            $keyword = $request->input('keyword');
            $result = $this->service->getRoutesByCategory($cateId, $keyword);
            return Json::success('ok', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/system/rule/refresh',
        summary: '刷新接口扫描缓存',
        tags: ['接口管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function refresh(Request $request): \support\Response
    {
        try {
            $this->service->refresh();
            return Json::success('刷新成功', []);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }
}
