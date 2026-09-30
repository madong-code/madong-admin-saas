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
namespace app\platform\controller\tenant;

use app\platform\controller\Base;
use app\service\platform\tenant\TenantMemberByTenantService;
use core\foundation\tool\Json;
use OpenApi\Attributes as OA;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use support\Request;
use support\Container;

/**
 * 按租户隔离的成员管理 Controller
 *
 * 从租户列表"更多"进入时，传入 tenant_id，后端自动切换到对应库表操作。
 * 路径格式: /tenant/{id}/member/...
 */
final class TenantMemberByTenantController extends Base
{
    /**
     * 获取租户专属的 Service 实例
     */
    private function getService(int $tenantId): TenantMemberByTenantService
    {
        $service = Container::make(TenantMemberByTenantService::class);
        $service->initWithTenant($tenantId);
        $this->service = $service;
        return $service;
    }

    /**
     * 列表
     */
    #[OA\Get(
        path: '/tenant/{id}/member',
        summary: '租户成员列表',
        tags: ['租户管理']
    )]
    #[PageResponse(schema: [], example: [])]
    public function index(Request $request): \support\Response
    {
        try {
            $tenantId = (int)$request->route->param('id');
            $service = $this->getService($tenantId);

            [$where, $format, $limit, $field, $order, $page] = $this->selectInput($request);
            $format_function = ['select' => 'formatSelect', 'normal' => 'formatNormal'][$format] ?? 'formatNormal';
            $total = $service->getCount($where);
            $list  = $service->selectList($where, $field, $page, $limit, $order);
            return call_user_func([$this, $format_function], $list, $total);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 新增（通过路由ID获取租户）
     */
    #[OA\Post(
        path: '/tenant/{id}/member',
        summary: '添加租户成员（路由ID）',
        tags: ['租户管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function store(Request $request): \support\Response
    {
        try {
            $tenantId = (int)$request->route->param('id');
            $service = $this->getService($tenantId);

            $data = $this->insertInput($request);
            $model = $service->save($data);
            if (empty($model)) throw new \RuntimeException('创建失败');
            return Json::success('创建成功', ['id' => $model->id]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 新增（通过请求体 tenant_id 获取租户，独立路径不含 URL ID）
     */
    #[OA\Post(
        path: '/tenant-member',
        summary: '添加租户成员',
        tags: ['租户管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function storeByBody(Request $request): \support\Response
    {
        try {
            $tenantId = (int)$request->post('tenant_id');
            $service = $this->getService($tenantId);

            $data = $this->insertInput($request);
            $data['tenant_id'] = $tenantId;
            $model = $service->save($data);
            if (empty($model)) throw new \RuntimeException('创建失败');
            return Json::success('创建成功', ['id' => $model->id]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 详情
     */
    #[OA\Get(
        path: '/tenant/{id}/member/{memberId}',
        summary: '租户成员详情',
        tags: ['租户管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function show(Request $request): \support\Response
    {
        try {
            $tenantId = (int)$request->route->param('id');
            $memberId = $request->route->param('memberId');
            $service = $this->getService($tenantId);

            $result = $service->get($memberId);
            if (empty($result)) throw new \RuntimeException('用户不存在');
            return Json::success('ok', $result->toArray());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 更新
     */
    #[OA\Put(
        path: '/tenant/{id}/member/{memberId}',
        summary: '更新租户成员',
        tags: ['租户管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        try {
            $tenantId = (int)$request->route->param('id');
            $memberId = $request->route->param('memberId');
            $service = $this->getService($tenantId);

            $data = $this->insertInput($request);
            if (empty($request->input('password'))) {
                unset($data['password']);
            }
            $service->update($memberId, $data);
            return Json::success('更新成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 删除
     */
    #[OA\Delete(
        path: '/tenant/{id}/member/{memberId}',
        summary: '删除租户成员',
        tags: ['租户管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        try {
            $tenantId = (int)$request->route->param('id');
            $memberId = $request->route->param('memberId');
            $service = $this->getService($tenantId);

            $service->batchDelete([$memberId]);
            return Json::success('删除成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 批量删除
     */
    #[OA\Delete(
        path: '/tenant/{id}/member',
        summary: '批量删除租户成员',
        tags: ['租户管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function batchDelete(Request $request): \support\Response
    {
        try {
            $tenantId = (int)$request->route->param('id');
            $service = $this->getService($tenantId);

            $ids = $request->input('ids', []);
            if (empty($ids)) {
                return Json::fail('请选择要删除的成员');
            }
            $service->batchDelete($ids);
            return Json::success('删除成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 修改状态
     */
    #[OA\Put(
        path: '/tenant/{id}/member/{memberId}/chang-status',
        summary: '修改租户成员状态',
        tags: ['租户管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function changStatus(Request $request): \support\Response
    {
        try {
            $tenantId = (int)$request->route->param('id');
            $memberId = (int)$request->route->param('memberId');
            $service = $this->getService($tenantId);

            $data = $this->insertInput($request);
            $service->changStatus($memberId, (int)$data['enabled']);
            return Json::success('操作成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 锁定
     */
    #[OA\Put(
        path: '/tenant/{id}/member/{memberId}/locked',
        summary: '锁定租户成员',
        tags: ['租户管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function locked(Request $request): \support\Response
    {
        try {
            $tenantId = (int)$request->route->param('id');
            $memberId = (int)$request->route->param('memberId');
            $service = $this->getService($tenantId);

            $service->locked([$memberId]);
            return Json::success('锁定成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 解锁
     */
    #[OA\Put(
        path: '/tenant/{id}/member/{memberId}/unLocked',
        summary: '解锁租户成员',
        tags: ['租户管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function unLocked(Request $request): \support\Response
    {
        try {
            $tenantId = (int)$request->route->param('id');
            $memberId = (int)$request->route->param('memberId');
            $service = $this->getService($tenantId);

            $service->unLocked([$memberId]);
            return Json::success('解锁成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 重置密码
     */
    #[OA\Put(
        path: '/tenant/{id}/member/{memberId}/resetPassword',
        summary: '重置租户成员密码',
        tags: ['租户管理']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function resetPassword(Request $request): \support\Response
    {
        try {
            $tenantId = (int)$request->route->param('id');
            $memberId = (int)$request->route->param('memberId');
            $service = $this->getService($tenantId);

            $service->resetPassword([$memberId]);
            return Json::success('密码已重置为123456');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}
