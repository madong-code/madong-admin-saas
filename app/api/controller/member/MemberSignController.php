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
use app\api\middleware\ApiAccessTokenMiddleware;
use app\service\api\member\MemberSignService;
use core\foundation\exception\handler\BadRequestHttpException;
use core\foundation\exception\handler\UnauthorizedHttpException;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Container;
use Webman\Http\Request;
use Webman\Http\Response;

#[OA\Tag(name: '会员签到')]
#[Middleware(ApiAccessTokenMiddleware::class)]
final class MemberSignController extends Base
{
    public function __construct(MemberSignService $service)
    {
        $this->service = $service;
    }

    #[OA\Post(
        path: '/member/sign',
        summary: '每日签到',
        tags: ['会员签到'],
        responses: [
            new OA\Response(response: 200, description: '签到成功'),
            new OA\Response(response: 401, description: '未登录'),
            new OA\Response(response: 400, description: '今日已签到'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function sign(): Response
    {
        try {
            /** @var CurrentMember $currentMember */
            $currentMember = Container::make(CurrentMember::class);
            $member        = $currentMember->user(true);
            if (empty($member)) {
                throw new UnauthorizedHttpException('用户凭证失效请重新登录');
            }

            $memberId = $member['id'];

            // 记录设备信息
            $deviceInfo = [
                'ip' => request()->getRealIp(),
                'ua' => request()->header('user-agent'),
            ];
            // 调用服务层执行签到
            $result = $this->service->sign($memberId, $deviceInfo);

            return Json::success('签到成功', [
                'points'          => $result['points'],
                'continuous_days' => $result['continuous_days'],
                'sign_date'       => $result['sign_date'],
            ]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/member/sign/status',
        summary: '获取签到状态',
        tags: ['会员签到'],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
            new OA\Response(response: 401, description: '未登录'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getStatus(): Response
    {
        try {
            /** @var CurrentMember $currentMember */
            $currentMember = Container::make(CurrentMember::class);
            $member        = $currentMember->user(true);

            if (empty($member)) {
                throw new UnauthorizedHttpException('用户凭证失效请重新登录');
            }
            $memberId = $member['id'];
            $result   = $this->service->getStatus($memberId);
            return Json::success('获取成功', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/member/sign/calendar',
        summary: '获取签到日历',
        tags: ['会员签到'],
        parameters: [
            new OA\Parameter(name: 'year', description: '年份', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'month', description: '月份', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
            new OA\Response(response: 401, description: '未登录'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getCalendar(Request $request): Response
    {
        try {
            /** @var CurrentMember $currentMember */
            $currentMember = Container::make(CurrentMember::class);
            $member        = $currentMember->user(true);

            if (empty($member)) {
                throw new UnauthorizedHttpException('用户凭证失效请重新登录');
            }

            $memberId = (int)$member['id'];
            $year     = (int)$request->input('year', date('Y'));
            $month    = (int)$request->input('month', date('m'));

            // 调用服务层获取签到日历
            $result = $this->service->getCalendar($memberId, $year, $month);

            return Json::success('获取成功', $result);

        } catch (UnauthorizedHttpException $e) {
            return Json::fail($e->getMessage(), null, 401);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/member/sign/statistics',
        summary: '获取签到统计',
        tags: ['会员签到'],
        parameters: [
            new OA\Parameter(name: 'type', description: '统计类型 (week/month/year)', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'year', description: '年份', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
            new OA\Response(response: 401, description: '未登录'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function getStatistics(Request $request): Response
    {
        try {
            /** @var CurrentMember $currentMember */
            $currentMember = Container::make(CurrentMember::class);
            $member        = $currentMember->user(true);

            if (empty($member)) {
                throw new UnauthorizedHttpException('用户凭证失效请重新登录');
            }

            $memberId = (int)$member['id'];
            $type     = $request->input('type', 'month');

            // 调用服务层获取签到统计
            $result = $this->service->getStatistics($memberId, $type);

            return Json::success('获取成功', $result);

        } catch (UnauthorizedHttpException $e) {
            return Json::fail($e->getMessage(), null, 401);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/member/sign/resign',
        summary: '补签',
        tags: ['会员签到'],
        parameters: [
            new OA\Parameter(name: 'sign_date', description: '补签日期 (Y-m-d)', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '补签成功'),
            new OA\Response(response: 401, description: '未登录'),
            new OA\Response(response: 400, description: '补签失败'),
        ]
    )]
    #[SimpleResponse(schema: [], example: [])]
    #[AllowAnonymous(requireToken: false, requirePermission: false, description: '公共接口')]
    public function resign(Request $request): Response
    {
        try {
            /** @var CurrentMember $currentMember */
            $currentMember = Container::make(CurrentMember::class);
            $member        = $currentMember->user(true);
            if (empty($member)) {
                throw new UnauthorizedHttpException('用户凭证失效请重新登录');
            }

            $memberId = $member['id'];
            $signDate = $request->input('sign_date', '');

            if (empty($signDate)) {
                throw new BadRequestHttpException('补签日期不能为空');
            }

            // 校验日期格式
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $signDate)) {
                throw new BadRequestHttpException('日期格式不正确，请使用 Y-m-d 格式');
            }

            $result = $this->service->reSign($memberId, $signDate);

            return Json::success('补签成功', [
                'points'          => $result['points'],
                'continuous_days' => $result['continuous_days'],
                'sign_date'       => $result['sign_date'],
            ]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}
