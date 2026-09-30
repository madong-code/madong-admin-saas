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

namespace app\api\controller\member;

use app\api\controller\Base;
use app\api\CurrentMember;
use app\service\api\member\MemberPointsService;
use core\foundation\exception\handler\UnauthorizedHttpException;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use support\Container;
use Webman\Http\Request;
use Webman\Http\Response;

#[OA\Tag(name: '积分模块')]
#
final class MemberPointsController extends Base
{
    public function __construct(MemberPointsService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/member/points/record',
        summary: '获取积分流水记录',
        tags: ['积分模块'],
        parameters: [
            new OA\Parameter(name: 'page', description: '页码', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limit', description: '每页数量', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type', description: '积分类型', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
            new OA\Response(response: 401, description: '未登录'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function record(Request $request): Response
    {
        $params = $request->all();
        try {
            $currentMember = Container::make(CurrentMember::class);
            $member        = $currentMember->user(true);
            if (empty($member)) {
                throw new UnauthorizedHttpException('用户凭证失效请重新登录');
            }
            $params['member_id'] = $member['id'];
            $result              = $this->service->getPointsRecord($params);
            return Json::success($result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), []);
        }
    }

    #[OA\Get(
        path: '/member/points/total',
        summary: '获取会员积分总额',
        tags: ['积分模块'],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
            new OA\Response(response: 401, description: '未登录'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function total(): Response
    {
        try {
            $currentMember = Container::make(CurrentMember::class);
            $member        = $currentMember->user(true);
            if (empty($member)) {
                throw new UnauthorizedHttpException('用户凭证失效请重新登录');
            }
            $result = $this->service->getPointsTotal($member['id']);
            return Json::success($result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), []);
        }
    }

    #[OA\Get(
        path: '/member/points/level',
        summary: '获取会员等级信息',
        tags: ['积分模块'],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
            new OA\Response(response: 401, description: '未登录'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getMemberLevel(): Response
    {
        try {
            $currentMember = Container::make(CurrentMember::class);
            $member        = $currentMember->user(true);
            if (empty($member)) {
                throw new UnauthorizedHttpException('用户凭证失效请重新登录');
            }
            $result = $this->service->getMemberLevel($member['id']);
            return Json::success($result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), []);
        }
    }

    #[OA\Post(
        path: '/member/points/sign',
        summary: '会员签到',
        tags: ['积分模块'],
        responses: [
            new OA\Response(response: 200, description: '签到成功'),
            new OA\Response(response: 401, description: '未登录'),
            new OA\Response(response: 400, description: '今日已签到'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function memberSign(): Response
    {
        try {
            $currentMember = Container::make(CurrentMember::class);
            $member        = $currentMember->user(true);
            if (empty($member)) {
                throw new UnauthorizedHttpException('用户凭证失效请重新登录');
            }
            $result = $this->service->memberSign($member['id']);
            return Json::success($result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), []);
        }
    }
}
